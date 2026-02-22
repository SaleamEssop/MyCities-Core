<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Intelligence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .si-container { 
            padding: 20px; 
            max-width: 1400px;
            margin: 0 auto;
        }
        .header-bar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 15px 20px;
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
        }
    </style>
</head>
<body>
    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Admin Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if(session('alert-message'))
                        <div class="alert alert-{{ session('alert-class', 'info') }}">
                            {{ session('alert-message') }}
                        </div>
                    @endif
                    <form method="POST" action="{{ route('admin.login') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="si-container">
        <div class="header-bar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-0">System Intelligence</h1>
                    <small class="text-light" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle"></i> Monitoring ALL requests from ALL browser windows/tabs
                        <span id="auto-refresh-status" class="badge bg-success ms-2">Auto-refresh: ON (Event-driven)</span>
                        <button class="btn btn-sm btn-outline-light ms-2" onclick="toggleAutoRefresh()" title="Toggle auto-refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </small>
                </div>
                <div>
                    @if(auth()->check())
                        <span class="badge bg-success me-2">Logged in as: {{ auth()->user()->email }}</span>
                        <a href="{{ route('admin.logout') }}" class="btn btn-sm btn-outline-light">Logout</a>
                    @else
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    @endif
                    @if(isset($currentSessionId))
                        <span class="badge bg-primary ms-2" title="Current Session ID">SI Session: {{ substr($currentSessionId, 0, 25) }}...</span>
                    @endif
                    @if(isset($activeSessions) && count($activeSessions) > 0)
                        <span class="badge bg-success ms-2" title="Active Sessions (last 5 min)">
                            <i class="fas fa-circle"></i> {{ count($activeSessions) }} Active
                        </span>
                    @endif
                    <span class="badge bg-info ms-2">Timezone: Africa/Johannesburg</span>
                </div>
            </div>
        </div>

        <!-- Session Management -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="toggleSessionManagement()">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chevron-down" id="session-chevron"></i> 
                    <i class="fas fa-window-restore"></i> Session Management
                    @if(isset($activeSessions) && count($activeSessions) > 0)
                        <span class="badge bg-success ms-2">{{ count($activeSessions) }} Active</span>
                    @endif
                </h6>
                <div>
                    <form method="POST" action="{{ route('system.intelligence.new-session') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Start a new monitoring session? Current session will be preserved.')">
                            <i class="fas fa-plus"></i> New Session
                        </button>
                    </form>
                    <form method="POST" action="{{ route('system.intelligence.clear-all') }}" class="d-inline ms-2">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('⚠️ WARNING: This will DELETE ALL logs permanently. Are you sure?')">
                            <i class="fas fa-trash"></i> Clear All Logs
                        </button>
                    </form>
                    @if(isset($currentSessionId) && count($logs ?? []) > 0)
                        <button type="button" class="btn btn-sm btn-info ms-2" onclick="copySessionToClipboard()">
                            <i class="fas fa-copy"></i> Copy All ({{ count($logs) }} logs)
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body" id="session-management-body">
                @if(isset($allSessions) && count($allSessions) > 0)
                    <!-- Active Sessions Display -->
                    @if(isset($activeSessions) && count($activeSessions) > 0)
                        <div class="alert alert-info mb-3">
                            <strong><i class="fas fa-info-circle"></i> Active Session IDs (last 5 minutes):</strong>
                            <div class="mt-2">
                                @foreach($activeSessions as $activeSessionId)
                                    @php
                                        $sessionInfo = collect($allSessions)->firstWhere('id', $activeSessionId);
                                    @endphp
                                    <span class="badge bg-success me-2 mb-1" style="font-size: 0.9em;" title="Last activity: {{ $sessionInfo['last_log'] ?? 'unknown' }} | First log: {{ $sessionInfo['first_log'] ?? 'unknown' }}">
                                        <i class="fas fa-circle"></i> {{ $activeSessionId }}
                                        @if(isset($sessionInfo['count']))
                                            <span class="badge bg-light text-dark ms-1">{{ $sessionInfo['count'] }} logs</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-lightbulb"></i> Tip: Use "Exclude System Intelligence requests" checkbox below to filter out SI window noise and see only Admin window requests.
                            </small>
                        </div>
                    @else
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle"></i> No active sessions in the last 5 minutes.
                        </div>
                    @endif
                    
                    <form method="GET" action="{{ route('system.intelligence') }}" class="form-inline">
                        <div class="mb-2">
                            <label class="me-2">View Session:</label>
                            <select name="session" class="form-control me-2" onchange="this.form.submit()">
                                <option value="">All Sessions</option>
                                @foreach($allSessions as $session)
                                    <option value="{{ $session['id'] }}" {{ request('session') == $session['id'] ? 'selected' : '' }}>
                                        {{ $session['id'] }} 
                                        @if($session['is_active'] ?? false)
                                            <span class="text-success">● ACTIVE</span>
                                        @endif
                                        ({{ $session['count'] }} logs, {{ $session['last_log'] ?? $session['first_log'] }})
                                    </option>
                                @endforeach
                            </select>
                            @if(request('session'))
                                <a href="{{ route('system.intelligence') }}" class="btn btn-sm btn-secondary">Show All Sessions</a>
                            @endif
                        </div>
                        <div class="mt-2">
                            <label class="form-check-label me-2">
                                <input type="checkbox" name="exclude_si" value="1" {{ request('exclude_si') ? 'checked' : '' }} onchange="this.form.submit()">
                                Exclude System Intelligence requests (filter out SI window noise)
                            </label>
                        </div>
                    </form>
                @else
                    <p class="text-muted mb-0">No sessions found. Start monitoring to create a session.</p>
                @endif
            </div>
        </div>

        <!-- Statistics Dashboard -->
        @if(isset($stats))
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Requests</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Errors (4xx/5xx)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['errors'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Critical Issues</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['critical_issues'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Slow Requests (>1s)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['slow_requests'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Filters -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('system.intelligence') }}" class="form-inline">
                    <select name="status" class="form-control me-2">
                        <option value="">All Status Codes</option>
                        <option value="200" {{ request('status') == '200' ? 'selected' : '' }}>200 OK</option>
                        <option value="400" {{ request('status') == '400' ? 'selected' : '' }}>400 Bad Request</option>
                        <option value="422" {{ request('status') == '422' ? 'selected' : '' }}>422 Validation Error</option>
                        <option value="500" {{ request('status') == '500' ? 'selected' : '' }}>500 Server Error</option>
                    </select>
                    <select name="issues" class="form-control me-2">
                        <option value="">All Requests</option>
                        <option value="yes" {{ request('issues') == 'yes' ? 'selected' : '' }}>With Issues Only</option>
                    </select>
                    <select name="slow" class="form-control me-2">
                        <option value="">All Requests</option>
                        <option value="yes" {{ request('slow') == 'yes' ? 'selected' : '' }}>Slow Requests Only</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('system.intelligence') }}" class="btn btn-secondary ms-2">Clear</a>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3" style="cursor: pointer;" onclick="toggleLogTable()">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chevron-down" id="log-chevron"></i> Forensic Traffic Log (Latest First)
                </h6>
            </div>
            <div class="card-body" id="log-table-body">
                @if(empty($logs))
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Intelligence Log is SILENT. No requests recorded yet. 
                        Start using the application to see logs appear here.
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Status</th>
                                <th>Issues</th>
                                <th>Method</th>
                                <th>URL</th>
                                <th>Duration</th>
                                <th>Queries</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $index => $log)
                            @php
                                $hasIssues = !empty($log['issues'] ?? []);
                                $criticalIssues = 0;
                                $highIssues = 0;
                                if ($hasIssues) {
                                    foreach ($log['issues'] as $issue) {
                                        if (($issue['severity'] ?? '') === 'critical') $criticalIssues++;
                                        if (($issue['severity'] ?? '') === 'high') $highIssues++;
                                    }
                                }
                            @endphp
                            <tr class="{{ ($log['response']['status'] ?? 200) >= 400 ? 'table-danger' : ($hasIssues ? 'table-warning' : '') }}">
                                <td>{{ $log['timestamp'] ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ ($log['response']['status'] ?? 200) >= 400 ? 'bg-danger' : 'bg-success' }}">
                                        {{ $log['response']['status'] ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    @if($hasIssues)
                                        @if($criticalIssues > 0)
                                            <span class="badge bg-danger" title="Critical Issues Detected">⚠️ {{ $criticalIssues }}</span>
                                        @endif
                                        @if($highIssues > 0)
                                            <span class="badge bg-warning" title="High Priority Issues">🔴 {{ $highIssues }}</span>
                                        @endif
                                        <span class="badge bg-info" title="Total Issues">📋 {{ count($log['issues']) }}</span>
                                        <button class="btn btn-xs btn-secondary mt-1" onclick="showIssues({{ $index }})">View</button>
                                        <div id="issues-{{ $index }}" style="display:none;" class="mt-2 p-2 bg-light border rounded">
                                            <strong>Detected Issues:</strong>
                                            <ul class="mb-0">
                                                @foreach($log['issues'] as $issue)
                                                <li class="{{ ($issue['severity'] ?? '') === 'critical' ? 'text-danger' : (($issue['severity'] ?? '') === 'high' ? 'text-warning' : '') }}">
                                                    <strong>{{ ucfirst($issue['type'] ?? 'unknown') }}</strong> ({{ $issue['severity'] ?? 'unknown' }}): 
                                                    {{ $issue['message'] ?? 'No message' }}
                                                </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <span class="badge bg-success">✓ OK</span>
                                    @endif
                                </td>
                                <td><code>{{ $log['request']['method'] ?? 'N/A' }}</code></td>
                                <td class="small">{{ \Illuminate\Support\Str::limit($log['request']['url'] ?? 'N/A', 50) }}</td>
                                <td>
                                    {{ $log['response']['duration'] ?? 'N/A' }}
                                    @if(floatval(str_replace('s', '', $log['response']['duration'] ?? '0')) > 1.0)
                                        <span class="badge bg-warning">Slow</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $log['database']['query_count'] ?? 0 }}
                                    @if(($log['database']['query_count'] ?? 0) > 20)
                                        <span class="badge bg-warning">Many</span>
                                    @endif
                                    @if(!empty($log['database']['slow_queries'] ?? []))
                                        <span class="badge bg-danger">{{ count($log['database']['slow_queries']) }} Slow</span>
                                    @endif
                                    @if(!empty($log['database']['n_plus_one_detected'] ?? null))
                                        <span class="badge bg-danger" title="N+1 Query Detected">N+1</span>
                                    @endif
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-dark" onclick="copyToClipboard('log-{{ $index }}')">
                                        Copy for AI
                                    </button>
                                    <div id="log-{{ $index }}" style="display:none;">
                                        {{ json_encode($log, JSON_PRETTY_PRINT) }}
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    @php
        $logFile = storage_path('app/logs/intelligence.json');
        $initialLastModified = file_exists($logFile) ? filemtime($logFile) : 0;
    @endphp
    function copyToClipboard(elementId) {
        const text = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('Forensic details copied to clipboard. Ready for Yusuf or AI analysis.');
        });
    }

    function showIssues(index) {
        const issuesDiv = document.getElementById('issues-' + index);
        if (issuesDiv.style.display === 'none') {
            issuesDiv.style.display = 'block';
        } else {
            issuesDiv.style.display = 'none';
        }
    }

    function copySessionToClipboard() {
        const logs = [];
        @if(isset($logs) && !empty($logs))
            @foreach($logs as $log)
                logs.push(@json($log));
            @endforeach
        @endif
        
        const sessionData = {
            session_id: '{{ $currentSessionId ?? "unknown" }}',
            timestamp: new Date().toISOString(),
            total_logs: logs.length,
            logs: logs
        };
        
        const text = JSON.stringify(sessionData, null, 2);
        navigator.clipboard.writeText(text).then(() => {
            alert(`✅ Copied {{ count($logs ?? []) }} logs from current session to clipboard. Ready for AI analysis.`);
        }).catch(err => {
            console.error('Failed to copy:', err);
            alert('Failed to copy. Check console for details.');
        });
    }

    function toggleSessionManagement() {
        const body = document.getElementById('session-management-body');
        const chevron = document.getElementById('session-chevron');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            chevron.classList.remove('fa-chevron-right');
            chevron.classList.add('fa-chevron-down');
        } else {
            body.style.display = 'none';
            chevron.classList.remove('fa-chevron-down');
            chevron.classList.add('fa-chevron-right');
        }
    }

    function toggleLogTable() {
        const body = document.getElementById('log-table-body');
        const chevron = document.getElementById('log-chevron');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            chevron.classList.remove('fa-chevron-right');
            chevron.classList.add('fa-chevron-down');
        } else {
            body.style.display = 'none';
            chevron.classList.remove('fa-chevron-down');
            chevron.classList.add('fa-chevron-right');
        }
    }

    // Event-driven auto-refresh: Only refresh when new requests/responses are detected
    let autoRefreshInterval = null;
    let isAutoRefreshEnabled = true;
    let lastModifiedTime = {{ $initialLastModified }};
    
    function startAutoRefresh() {
        if (autoRefreshInterval || !isAutoRefreshEnabled) return; // Already running or disabled
        
        autoRefreshInterval = setInterval(function() {
            // Check if page is visible (don't check if user switched tabs)
            if (document.hidden) return;
            
            // Check for updates via API - only refresh if file was modified
            fetch('{{ route("system.intelligence.check-updates") }}?last_modified=' + lastModifiedTime)
                .then(response => response.json())
                .then(data => {
                    if (data.has_updates) {
                        // File was modified - new request/response detected, refresh the page
                        lastModifiedTime = data.last_modified;
                        window.location.reload();
                    }
                    // If no updates, do nothing - no unnecessary refresh
                })
                .catch(error => {
                    console.error('Error checking for updates:', error);
                });
        }, 1000); // Check every 1 second (but only refresh if file changed)
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
            const statusBadge = document.getElementById('auto-refresh-status');
            if (statusBadge) {
                statusBadge.textContent = 'Auto-refresh: OFF';
                statusBadge.className = 'badge bg-secondary ms-2';
            }
        }
    }
    
    function toggleAutoRefresh() {
        isAutoRefreshEnabled = !isAutoRefreshEnabled;
        const statusBadge = document.getElementById('auto-refresh-status');
        if (isAutoRefreshEnabled) {
            startAutoRefresh();
            if (statusBadge) {
                statusBadge.textContent = 'Auto-refresh: ON (Event-driven)';
                statusBadge.className = 'badge bg-success ms-2';
            }
        } else {
            stopAutoRefresh();
            if (statusBadge) {
                statusBadge.textContent = 'Auto-refresh: OFF';
                statusBadge.className = 'badge bg-secondary ms-2';
            }
        }
    }
    
    // Start auto-refresh on page load
    document.addEventListener('DOMContentLoaded', function() {
        startAutoRefresh();
        
        // Auto-show login modal if there's an error message and user is not logged in
        @if(session('alert-message') && !auth()->check())
            const loginModalElement = document.getElementById('loginModal');
            if (loginModalElement) {
                const loginModal = new bootstrap.Modal(loginModalElement);
                loginModal.show();
            }
        @endif
    });
    </script>
</body>
</html>

