<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class SystemIntelligenceController extends Controller
{
    public function index(Request $request)
    {
        // Simple: Get session ID if exists, otherwise null
        $currentSessionId = $request->session()->get('si_session_id');
        
        $path = 'logs/intelligence.json';
        $logs = [];
        $allSessions = [];
        $content = '';
        
        if (!Storage::disk('local')->exists($path)) {
            // No log file exists yet - return empty view
            $stats = [
                'total' => 0,
                'errors' => 0,
                'with_issues' => 0,
                'critical_issues' => 0,
                'slow_requests' => 0,
                'current_session' => $currentSessionId,
                'total_sessions' => 0,
            ];
            return view('system-intelligence.index', compact('logs', 'stats', 'allSessions', 'currentSessionId', 'activeSessions'));
        }

        $content = Storage::disk('local')->get($path);
        
        // Handle empty file
        if (empty(trim($content))) {
            $stats = [
                'total' => 0,
                'errors' => 0,
                'with_issues' => 0,
                'critical_issues' => 0,
                'slow_requests' => 0,
                'current_session' => $currentSessionId,
                'total_sessions' => 0,
            ];
            return view('system-intelligence.index', compact('logs', 'stats', 'allSessions', 'currentSessionId', 'activeSessions'));
        }
        
        // Each line is a JSON object
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $decoded = json_decode($line, true);
            if ($decoded) {
                $logs[] = $decoded;
            }
        }

        // Newest first (Forensic Priority)
        $logs = array_reverse($logs);

        // Filter by session if specified
        $filterSession = $request->get('session');
        $excludeSI = $request->get('exclude_si', false); // Exclude System Intelligence requests
        
        // Exclude System Intelligence requests FIRST (to filter out SI window noise)
        // This allows viewing only Admin window requests
        if ($excludeSI) {
            $logs = array_values(array_filter($logs, function($log) {
                $url = $log['request']['url'] ?? '';
                return strpos($url, 'system-intelligence') === false;
            }));
        }
        
        if ($filterSession) {
            // Filter to specific session (explicit selection from dropdown)
            $logs = array_values(array_filter($logs, function($log) use ($filterSession) {
                return ($log['session_id'] ?? '') === $filterSession;
            }));
        } else {
            // Show ALL logs by default (don't filter by current session)
            // This ensures new sessions can see all requests immediately
            $logs = array_values($logs);
        }

        // Get all unique sessions for dropdown and active session tracking
        $allLogs = [];
        $activeSessions = []; // Sessions with activity in last 5 minutes
        $now = \Carbon\Carbon::now();
        
        if (!empty($content)) {
            foreach (explode("\n", trim($content)) as $line) {
                if (empty(trim($line))) continue;
                $decoded = json_decode($line, true);
                if ($decoded) {
                    $allLogs[] = $decoded;
                    $sessionId = $decoded['session_id'] ?? 'unknown';
                    $logTime = isset($decoded['timestamp']) ? \Carbon\Carbon::parse($decoded['timestamp']) : null;
                    
                    if (!isset($allSessions[$sessionId])) {
                        $allSessions[$sessionId] = [
                            'id' => $sessionId,
                            'first_log' => $decoded['timestamp'] ?? '',
                            'last_log' => $decoded['timestamp'] ?? '',
                            'count' => 0
                        ];
                    }
                    $allSessions[$sessionId]['count']++;
                    
                    // Update last_log if this is newer
                    if ($logTime && isset($allSessions[$sessionId]['last_log'])) {
                        $currentLastLog = \Carbon\Carbon::parse($allSessions[$sessionId]['last_log']);
                        if ($logTime->gt($currentLastLog)) {
                            $allSessions[$sessionId]['last_log'] = $decoded['timestamp'];
                        }
                    }
                    
                    // Track active sessions (last 5 minutes)
                    if ($logTime && $logTime->gt($now->copy()->subMinutes(5))) {
                        if (!in_array($sessionId, $activeSessions)) {
                            $activeSessions[] = $sessionId;
                        }
                    }
                }
            }
        }
        
        // Sort sessions by last log time (newest first)
        $allSessions = array_values($allSessions);
        usort($allSessions, function($a, $b) {
            $aTime = $a['last_log'] ?? $a['first_log'] ?? '';
            $bTime = $b['last_log'] ?? $b['first_log'] ?? '';
            return strcmp($bTime, $aTime);
        });
        
        // Mark active sessions
        foreach ($allSessions as &$session) {
            $session['is_active'] = in_array($session['id'], $activeSessions);
        }
        unset($session);

        // Filtering options
        $filterStatus = $request->get('status');
        $filterIssues = $request->get('issues');
        $filterSlow = $request->get('slow');
        
        if ($filterStatus) {
            $logs = array_filter($logs, function($log) use ($filterStatus) {
                return $log['response']['status'] == $filterStatus;
            });
        }
        
        if ($filterIssues === 'yes') {
            $logs = array_filter($logs, function($log) {
                return !empty($log['issues'] ?? []);
            });
        }
        
        if ($filterSlow === 'yes') {
            $logs = array_filter($logs, function($log) {
                $duration = floatval(str_replace('s', '', $log['response']['duration'] ?? '0'));
                return $duration > 1.0 || !empty($log['database']['slow_queries'] ?? []);
            });
        }

        // Statistics (for current session/filter)
        $stats = [
            'total' => count($logs),
            'errors' => count(array_filter($logs, fn($l) => ($l['response']['status'] ?? 200) >= 400)),
            'with_issues' => count(array_filter($logs, fn($l) => !empty($l['issues'] ?? []))),
            'critical_issues' => count(array_filter($logs, function($l) {
                $issues = $l['issues'] ?? [];
                if (empty($issues)) return false;
                foreach ($issues as $issue) {
                    if (($issue['severity'] ?? '') === 'critical') {
                        return true;
                    }
                }
                return false;
            })),
            'slow_requests' => count(array_filter($logs, function($l) {
                $duration = floatval(str_replace('s', '', $l['response']['duration'] ?? '0'));
                return $duration > 1.0;
            })),
            'current_session' => $currentSessionId,
            'total_sessions' => count($allSessions),
        ];

        return view('system-intelligence.index', compact('logs', 'stats', 'allSessions', 'currentSessionId'));
    }

    /**
     * Start a new monitoring session
     */
    public function newSession(Request $request)
    {
        // Generate new session ID
        $newSessionId = 'session_' . uniqid();
        $request->session()->put('si_session_id', $newSessionId);
        
        return redirect()->route('system.intelligence', ['si_new_session' => true])
            ->with('success', 'New monitoring session started: ' . $newSessionId);
    }

    /**
     * Clear all logs
     */
    public function clearAll(Request $request)
    {
        $path = 'logs/intelligence.json';
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, '');
        }
        
        // Also clear current session
        $request->session()->forget('si_session_id');
        
        return redirect()->route('system.intelligence')
            ->with('success', 'All logs cleared and session reset.');
    }

    /**
     * Get logs for a specific session (JSON API)
     */
    public function getSessionLogs(Request $request, $sessionId)
    {
        $path = 'logs/intelligence.json';
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'No logs found'], 404);
        }

        $content = Storage::disk('local')->get($path);
        $lines = explode("\n", trim($content));
        
        $logs = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if ($decoded && ($decoded['session_id'] ?? '') === $sessionId) {
                $logs[] = $decoded;
            }
        }

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'count' => count($logs),
            'logs' => $logs
        ]);
    }

    /**
     * Check if log file has been modified since last check
     */
    public function checkForUpdates(Request $request)
    {
        $path = 'logs/intelligence.json';
        $lastModified = $request->get('last_modified', 0);
        
        if (!Storage::disk('local')->exists($path)) {
            return response()->json([
                'has_updates' => false,
                'last_modified' => 0
            ]);
        }
        
        $filePath = storage_path('app/' . $path);
        $currentModified = filemtime($filePath);
        
        return response()->json([
            'has_updates' => $currentModified > $lastModified,
            'last_modified' => $currentModified,
            'timestamp' => date('Y-m-d H:i:s', $currentModified)
        ]);
    }
}

