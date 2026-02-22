<?php
// Test if /admin/calculator returns proper Inertia HTML when authenticated
define('LARAVEL_START', microtime(true));
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create request with authenticated session
$request = Illuminate\Http\Request::create('/admin/calculator', 'GET');
$request->headers->set('Accept', 'text/html,application/xhtml+xml');

// Boot the application
$app->instance('request', $request);
Illuminate\Support\Facades\Facade::clearResolvedInstance('request');

$user = \App\Models\User::where('email', 'admin@mycities.co.za')->first();
if (!$user) { die("No admin user found\n"); }

// Fake auth
\Illuminate\Support\Facades\Auth::setUser($user);
$request->setLaravelSession($app['session']->driver());

$response = $kernel->handle($request);

echo "HTTP Status: " . $response->getStatusCode() . "\n";
$content = $response->getContent();
echo "Response size: " . strlen($content) . " bytes\n";

// Check for data-page
if (strpos($content, 'data-page=') !== false) {
    echo "✓ data-page IS present\n";
    preg_match('/"component":"([^"]+)"/', html_entity_decode($content), $m);
    echo "Component: " . ($m[1] ?? 'NOT FOUND') . "\n";
    preg_match('/"url":"([^"]+)"/', html_entity_decode($content), $u);
    echo "URL in page data: " . ($u[1] ?? 'NOT FOUND') . "\n";
} else {
    echo "✗ data-page IS MISSING\n";
    echo "First 500 chars:\n" . substr($content, 0, 500) . "\n";
}

// Check script src
preg_match_all('/src="([^"]+)"/', $content, $scripts);
echo "Script srcs:\n";
foreach ($scripts[1] as $s) echo "  $s\n";
