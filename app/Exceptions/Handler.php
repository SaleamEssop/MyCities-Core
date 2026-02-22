<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\Request;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Log to System Intelligence
            $this->logToSystemIntelligence($e);
        });
    }

    /**
     * Render an exception into an HTTP response.
     * Override to ensure exceptions are logged even during rendering.
     */
    public function render($request, Throwable $e)
    {
        // Log exception before rendering
        $this->logToSystemIntelligence($e);

        return parent::render($request, $e);
    }

    /**
     * Log exception to System Intelligence
     */
    protected function logToSystemIntelligence(Throwable $e)
    {
        try {
            $request = request();
            if (!$request) {
                return; // No request context (CLI, queue, etc.)
            }

            $traceId = $request->attributes->get('si_trace_id') ?? uniqid('trace_', true);
            $startTime = $request->attributes->get('si_start_time') ?? microtime(true);
            $sessionId = $request->attributes->get('si_session_id') ?? $request->session()->get('si_session_id', 'session_' . uniqid());

            // Mark that exception was logged to prevent duplicate logging in middleware
            $request->attributes->set('si_exception_logged', true);
            $request->attributes->set('si_exception', $e);

            $duration = round(microtime(true) - $startTime, 4);

            // Get database queries if available
            $queries = [];
            $queryCount = 0;
            try {
                if (\Illuminate\Support\Facades\DB::getQueryLog()) {
                    $queries = \Illuminate\Support\Facades\DB::getQueryLog();
                    $queryCount = count($queries);
                }
            } catch (\Exception $queryException) {
                // Query log might not be available
            }

            $logData = [
                'timestamp' => \Carbon\Carbon::now('Africa/Johannesburg')->toDateTimeString(),
                'trace_id' => $traceId,
                'session_id' => $sessionId,  // ADD: Session identifier
                'exception' => true,  // Flag this as an exception log
                'request' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                    'payload' => $request->except(['password', '_token', 'password_confirmation']),
                    'route' => $request->route() ? $request->route()->getName() : null,
                    'controller' => (function () use ($request) {
                        try {
                            return $request->route() && $request->route()->getController() ? get_class($request->route()->getController()) : null;
                        } catch (\Throwable $t) {
                            return 'Closure/Unknown';
                        }
                    })(),
                ],
                'exception_details' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'type' => get_class($e),
                    'trace' => substr($e->getTraceAsString(), 0, 2000), // Limit trace size
                ],
                'response' => [
                    'status' => 500,
                    'duration' => $duration . 's',
                    'body' => substr($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), 0, 2000),
                ],
                'database' => [
                    'query_count' => $queryCount,
                ],
                'issues' => [
                    [
                        'type' => 'exception',
                        'severity' => 'critical',
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]
                ],
            ];

            // Ensure directory exists before writing
            $logPath = storage_path('app/logs');
            if (!is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }

            \Illuminate\Support\Facades\Storage::disk('local')->append('logs/intelligence.json', json_encode($logData));
        } catch (\Exception $logException) {
            // Don't let logging errors break the application
            \Log::error('Failed to log to System Intelligence: ' . $logException->getMessage());
        }
    }
}
