<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Simulate an authenticated request  
$request = Illuminate\Http\Request::create('/admin/calculator', 'GET');
$app->instance('request', $request);

// Boot the app
$app->boot();

// Check vite helper
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
$entry = $manifest['resources/js/inertia-app.js'] ?? null;
echo "Manifest inertia-app entry: " . ($entry ? $entry['file'] : 'NOT FOUND') . PHP_EOL;
echo "File exists: " . (file_exists(public_path('build/' . ($entry['file'] ?? ''))) ? 'YES' : 'NO') . PHP_EOL;
