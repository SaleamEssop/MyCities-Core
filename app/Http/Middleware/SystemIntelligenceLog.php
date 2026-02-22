<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SystemIntelligenceLog
{
    public function handle($request, Closure $next)
    {
        // Skip logging for System Intelligence routes to prevent circular dependency
        if ($request->is('admin/system-intelligence*') || $request->is('system-intelligence*')) {
            return $next($request);
        }

        $startTime = microtime(true);
        $traceId = uniqid('trace_', true);

        // Session Management: Get or create session ID
        // New session on each browser refresh OR if explicitly started via button
        // Check if this is a new session request (from button) or first visit
        // Note: Session is available here because we're in web/api middleware groups (after StartSession)
        try {
            $session = $request->session();
            if ($request->get('si_new_session') || !$session->has('si_session_id')) {
                $sessionId = 'session_' . uniqid();
                $session->put('si_session_id', $sessionId);
                $session->put('si_session_start', now()->toDateTimeString());
            } else {
                $sessionId = $session->get('si_session_id');
            }
            // Track last activity for this session
            $session->put('si_last_activity', now()->toDateTimeString());
        } catch (\Exception $e) {
            // Fallback if session unavailable (shouldn't happen in web/api groups, but safety check)
            $sessionId = $request->attributes->get('si_session_id', 'session_' . uniqid());
            $request->attributes->set('si_session_id', $sessionId);
        }

        // Store trace_id in request for exception handler to use
        $request->attributes->set('si_trace_id', $traceId);
        $request->attributes->set('si_start_time', $startTime);
        $request->attributes->set('si_session_id', $sessionId);

        // Track database queries
        DB::enableQueryLog();
        $queryCount = 0;
        $slowQueries = [];

        $exception = null;
        try {
            $response = $next($request);
        } catch (\Exception $e) {
            // Capture exceptions before they become 500 errors
            $exception = $e;
            $response = response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_id' => $traceId
            ], 500);
        } catch (\Throwable $e) {
            // Also catch Throwable (PHP 7+)
            $exception = $e;
            $response = response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_id' => $traceId
            ], 500);
        }

        $duration = round(microtime(true) - $startTime, 4);

        // Analyze database queries
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        $totalQueryTime = 0;

        foreach ($queries as $query) {
            $queryTime = $query['time'] ?? 0;
            $totalQueryTime += $queryTime;

            // Flag slow queries (>100ms)
            if ($queryTime > 100) {
                $slowQueries[] = [
                    'query' => $query['query'],
                    'bindings' => $query['bindings'] ?? [],
                    'time' => $queryTime . 'ms'
                ];
            }

            // Detect potential issues
            $issues = $this->detectQueryIssues($query);
        }

        // Extract error details
        $errorDetails = null;
        $statusCode = $response->getStatusCode();

        // If exception was caught, ensure status is 500
        if ($exception) {
            $statusCode = 500;
            if (!$response->getStatusCode() || $response->getStatusCode() === 200) {
                $response->setStatusCode(500);
            }
        }

        if ($statusCode >= 400) {
            $content = $response->getContent();
            $errorDetails = $this->analyzeError($content, $statusCode);

            // Add exception details if available
            if ($exception) {
                $errorDetails['exception'] = [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ];
            }
        }

        $logData = [
            'timestamp' => Carbon::now('Africa/Johannesburg')->toDateTimeString(),
            'trace_id' => $traceId,
            'session_id' => $sessionId,  // ADD: Session identifier
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
            'response' => [
                'status' => $statusCode,
                'duration' => $duration . 's',
                'body' => $statusCode >= 400 ? substr($response->getContent(), 0, 2000) : 'Success',
            ],
            'database' => [
                'query_count' => $queryCount,
                'total_query_time' => round($totalQueryTime, 2) . 'ms',
                'slow_queries' => $slowQueries,
                'n_plus_one_detected' => $this->detectNPlusOne($queries),
            ],
            'issues' => $this->detectIssues($request, $response, $queries, $errorDetails),
            'error_analysis' => $errorDetails,
        ];

        // Check if exception was already logged by exception handler
        $exceptionLogged = $request->attributes->get('si_exception_logged', false);

        // Log ALL requests (System Intelligence is a forensic tool - capture everything)
        // Exception handler logs exceptions, middleware logs all requests
        // Only skip if exception was already logged by handler (to prevent duplicates)
        $shouldLog = !$exceptionLogged;

        // If exception was logged by handler, don't log again (exception handler already logged it)
        if ($exceptionLogged) {
            // Don't log again - exception handler already logged it
        } elseif ($shouldLog) {
            try {
                // Ensure directory exists before writing
                $logPath = storage_path('app/logs');
                if (!is_dir($logPath)) {
                    mkdir($logPath, 0755, true);
                }
                Storage::disk('local')->append('logs/intelligence.json', json_encode($logData));
            } catch (\Exception $e) {
                // Don't let logging errors break the application
                Log::error('System Intelligence logging failed: ' . $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Detect common issues based on project history
     */
    private function detectIssues($request, $response, $queries, $errorDetails)
    {
        $issues = [];
        $statusCode = $response->getStatusCode();
        $responseContent = $response->getContent();

        // 1. JSON Structure Issues (water_in, water_out, etc.)
        $payload = $request->all();
        foreach (['water_in', 'water_out', 'electricity', 'fixed_costs', 'customer_costs'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $decoded = json_decode($payload[$field], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $issues[] = [
                        'type' => 'invalid_json',
                        'field' => $field,
                        'severity' => 'high',
                        'message' => "Field '{$field}' contains invalid JSON"
                    ];
                }
            }
        }

        // 2. Table Name Mismatches (tariff_templates vs regions_account_type_cost)
        foreach ($queries as $query) {
            $queryStr = $query['query'] ?? '';
            if (strpos($queryStr, 'tariff_templates') !== false && strpos($queryStr, 'exists:tariff_templates') !== false) {
                $issues[] = [
                    'type' => 'deprecated_table',
                    'severity' => 'critical',
                    'message' => 'Query uses deprecated table: tariff_templates (should use regions_account_type_cost)',
                    'query' => substr($queryStr, 0, 200)
                ];
            }
        }

        // 3. Field Name Inconsistencies
        if (strpos($responseContent, 'rate_per_unit') !== false && strpos($responseContent, 'rate') !== false) {
            $issues[] = [
                'type' => 'field_name_inconsistency',
                'severity' => 'medium',
                'message' => 'Response contains both rate_per_unit and rate - potential inconsistency'
            ];
        }

        // 4. Missing Return Values
        if ($statusCode === 200 && $request->route() && strpos($request->route()->getName() ?? '', 'compute-period') !== false) {
            $responseData = json_decode($responseContent, true);
            $requiredFields = ['opening_reading', 'tiered_charge', 'daily_usage'];
            $missing = [];
            foreach ($requiredFields as $field) {
                if (!isset($responseData[$field])) {
                    $missing[] = $field;
                }
            }
            if (!empty($missing)) {
                $issues[] = [
                    'type' => 'missing_return_values',
                    'severity' => 'high',
                    'message' => 'Missing return values: ' . implode(', ', $missing)
                ];
            }
        }

        // 5. Validation Errors
        if ($statusCode === 422 && $errorDetails) {
            $issues[] = [
                'type' => 'validation_error',
                'severity' => 'high',
                'message' => 'Validation failed',
                'details' => $errorDetails
            ];
        }

        // 6. Empty Data Returns
        if ($statusCode === 200) {
            $responseData = json_decode($responseContent, true);
            if (isset($responseData['data']) && empty($responseData['data'])) {
                $issues[] = [
                    'type' => 'empty_data_return',
                    'severity' => 'medium',
                    'message' => 'Response contains empty data array'
                ];
            }
        }

        // 7. Duplicate Method Detection (check response for "Cannot redeclare")
        if (strpos($responseContent, 'Cannot redeclare') !== false || strpos($responseContent, 'already been declared') !== false) {
            $issues[] = [
                'type' => 'duplicate_method',
                'severity' => 'critical',
                'message' => 'Duplicate method/function detected - check for duplicate definitions'
            ];
        }

        return $issues;
    }

    /**
     * Detect N+1 query problems
     */
    private function detectNPlusOne($queries)
    {
        $tableCounts = [];
        foreach ($queries as $query) {
            $queryStr = $query['query'] ?? '';
            // Extract table name from SELECT queries
            if (preg_match('/FROM\s+`?(\w+)`?/i', $queryStr, $matches)) {
                $table = $matches[1];
                $tableCounts[$table] = ($tableCounts[$table] ?? 0) + 1;
            }
        }

        // If same table queried > 5 times, likely N+1
        foreach ($tableCounts as $table => $count) {
            if ($count > 5) {
                return [
                    'table' => $table,
                    'count' => $count,
                    'message' => "Potential N+1 query detected: {$table} queried {$count} times"
                ];
            }
        }

        return null;
    }

    /**
     * Detect issues in individual queries
     */
    private function detectQueryIssues($query)
    {
        $issues = [];
        $queryStr = $query['query'] ?? '';

        // Check for missing WHERE clauses on large tables
        $largeTables = ['meter_readings', 'bills', 'payments'];
        foreach ($largeTables as $table) {
            if (strpos($queryStr, "FROM `{$table}`") !== false && strpos($queryStr, 'WHERE') === false) {
                $issues[] = "Query on {$table} without WHERE clause";
            }
        }

        return $issues;
    }

    /**
     * Analyze error response for patterns
     */
    private function analyzeError($content, $statusCode)
    {
        $analysis = [
            'status_code' => $statusCode,
            'patterns' => []
        ];

        // Common error patterns from project history
        $patterns = [
            'json_decode_error' => ['json_decode', 'JSON_ERROR', 'Malformed JSON'],
            'table_not_found' => ["doesn't exist", "Unknown table", "Base table or view not found"],
            'column_not_found' => ["Unknown column", "doesn't have a default value"],
            'foreign_key_error' => ["foreign key constraint", "Cannot add or update"],
            'validation_error' => ['Validation failed', 'The given data was invalid'],
            'syntax_error' => ['syntax error', 'Parse error', 'Unexpected'],
            'duplicate_method' => ['Cannot redeclare', 'already been declared'],
            'missing_method' => ['Call to undefined method', 'Method does not exist'],
            'type_error' => ['must be of type', 'TypeError', 'Return value must be'],
        ];

        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($content, $keyword) !== false) {
                    $analysis['patterns'][] = $type;
                    break;
                }
            }
        }

        // Extract specific error message
        if (preg_match('/"message":\s*"([^"]+)"/', $content, $matches)) {
            $analysis['message'] = $matches[1];
        } elseif (preg_match('/<title>([^<]+)<\/title>/', $content, $matches)) {
            $analysis['message'] = $matches[1];
        }

        return $analysis;
    }
}
