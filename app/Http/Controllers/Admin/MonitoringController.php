<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MonitoringSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * MonitoringController
 * 
 * Handles monitoring session management.
 * No middleware, no framework hooks - just simple endpoints.
 */
class MonitoringController extends Controller
{
    /**
     * Start a new monitoring session
     * 
     * POST /admin/monitoring/start
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $sessionId = MonitoringSessionService::createSession();

            return response()->json([
                'success' => true,
                'sessionId' => $sessionId,
                // Use date+time format without milliseconds: Y-m-d H:i:s
                'startedAt' => now()->format('Y-m-d H:i:s'),
                'message' => 'Monitoring session started',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start monitoring session',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Stop a monitoring session
     * 
     * POST /admin/monitoring/stop/{sessionId}
     */
    public function stop(string $sessionId): JsonResponse
    {
        try {
            $stopped = MonitoringSessionService::stopSession($sessionId);

            if (!$stopped) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Monitoring session stopped',
                // Use date+time format without milliseconds: Y-m-d H:i:s
                'stoppedAt' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop monitoring session',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Get all events for a session
     * 
     * GET /admin/monitoring/events/{sessionId}
     */
    public function getEvents(string $sessionId): JsonResponse
    {
        try {
            $session = MonitoringSessionService::getEvents($sessionId);

            if (empty($session)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'session' => $session,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve events',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Add an event to a session
     * 
     * POST /admin/monitoring/events/{sessionId}
     */
    public function addEvent(Request $request, string $sessionId): JsonResponse
    {
        try {
            // Validate session is active
            if (!MonitoringSessionService::isActive($sessionId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not active',
                ], 400);
            }

            // Validate required fields
            $request->validate([
                'source' => 'required|in:nginx,browser,container',
                'type' => 'required|string',
                'severity' => 'required|in:info,warning,error,critical',
                'data' => 'required|array',
            ]);

            // Sanitize data - remove sensitive information
            $data = $request->input('data');
            $sanitizedData = $this->sanitizeEventData($data);

            $event = [
                'source' => $request->input('source'),
                'type' => $request->input('type'),
                'severity' => $request->input('severity'),
                'data' => $sanitizedData,
            ];

            $added = MonitoringSessionService::addEvent($sessionId, $event);

            if (!$added) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add event',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Event added',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add event',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Clear a session
     * 
     * DELETE /admin/monitoring/session/{sessionId}
     */
    public function clear(string $sessionId): JsonResponse
    {
        try {
            $cleared = MonitoringSessionService::clearSession($sessionId);

            if (!$cleared) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Session cleared',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear session',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Get active sessions
     * 
     * GET /admin/monitoring/sessions
     */
    public function getActiveSessions(): JsonResponse
    {
        try {
            // Clean up expired sessions first
            MonitoringSessionService::cleanupExpiredSessions();

            $sessions = MonitoringSessionService::getActiveSessions();

            return response()->json([
                'success' => true,
                'sessions' => array_values($sessions),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sessions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Sanitize event data - remove sensitive information
     */
    private function sanitizeEventData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'token', 'secret', 'key', 'authorization',
            'cookie', 'csrf', 'session', 'credit_card', 'ssn',
            'email', 'phone', 'address', 'name', 'user_id',
        ];

        $sanitized = [];

        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);
            
            // Skip sensitive keys
            foreach ($sensitiveKeys as $sensitive) {
                if (strpos($keyLower, $sensitive) !== false) {
                    $sanitized[$key] = '[REDACTED]';
                    continue 2;
                }
            }

            // Recursively sanitize arrays
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeEventData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}





















