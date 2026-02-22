@extends('admin.layouts.main')
@section('title', 'User WebApp - {{ $user->name }}')

@section('content')
<div class="container-fluid p-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1 custom-text-heading">User WebApp View</h1>
            <p class="text-muted mb-0">
                <strong>User:</strong> {{ $user->name }} ({{ $user->email }}) | 
                <strong>Account:</strong> {{ $account->account_name }} ({{ $account->account_number }})
            </p>
        </div>
        <div>
            <!-- Monitor Button -->
            <button id="monitor-toggle-btn" class="btn btn-primary mr-2" data-session-id="">
                <i class="fas fa-play"></i> <span id="monitor-btn-text">Start Monitoring</span>
            </button>
            <a href="{{ route('user-accounts.manager') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Manager
            </a>
        </div>
    </div>

    <!-- WebApp Embed -->
    <div class="card shadow mb-4" style="height: calc(100vh - 200px);">
        <div class="card-body p-0" style="height: 100%; position: relative;">
            <!-- Loading indicator -->
            <div id="iframe-loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1;">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading webapp...</span>
                </div>
                <p class="mt-2">Loading user dashboard...</p>
            </div>
            
            <!-- Direct link for testing -->
            <div class="p-3 border-bottom">
                <p class="mb-2"><strong>WebApp URL:</strong></p>
                <a href="{{ url('/web-app') }}#/dashboard?accountId={{ $account->id }}&adminView=true&userId={{ $user->id }}" target="_blank" class="btn btn-sm btn-primary">
                    Open in New Tab (for testing)
                </a>
            </div>
            
            <iframe 
                id="webapp-iframe"
                src="{{ url('/web-app') }}#/dashboard?accountId={{ $account->id }}&adminView=true&userId={{ $user->id }}"
                style="width: 100%; height: calc(100% - 60px); border: 1px solid #ddd; position: relative; z-index: 2;"
                title="User WebApp Dashboard"
                allow="fullscreen"
                onload="document.getElementById('iframe-loading').style.display='none';"
                onerror="document.getElementById('iframe-loading').innerHTML='<p class=\"text-danger\">Failed to load webapp. Check browser console.</p>';"
            ></iframe>
        </div>
    </div>

    <!-- Monitoring Timeline (Shown when monitoring is active) -->
    <div id="monitoring-timeline-card" class="card shadow mb-4" style="display: none;">
        <div class="card-header py-3 bg-primary text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-chart-line"></i> Monitoring Timeline
                <span id="monitoring-session-id" class="badge badge-light ml-2"></span>
            </h6>
        </div>
        <div class="card-body">
            <div id="monitoring-timeline-content">
                <p class="text-muted">Loading events...</p>
            </div>
        </div>
    </div>

    <!-- Debug Info (Collapsible) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <a class="text-decoration-none" data-toggle="collapse" href="#debugInfo" role="button">
                    <i class="fas fa-info-circle"></i> Debug Information
                </a>
            </h6>
        </div>
        <div class="collapse" id="debugInfo">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Account Details</h6>
                        <ul class="list-unstyled">
                            <li><strong>ID:</strong> {{ $account->id }}</li>
                            <li><strong>Name:</strong> {{ $account->account_name }}</li>
                            <li><strong>Number:</strong> {{ $account->account_number }}</li>
                            <li><strong>Tariff:</strong> {{ $account->tariffTemplate->template_name ?? 'N/A' }}</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>User Details</h6>
                        <ul class="list-unstyled">
                            <li><strong>ID:</strong> {{ $user->id }}</li>
                            <li><strong>Name:</strong> {{ $user->name }}</li>
                            <li><strong>Email:</strong> {{ $user->email }}</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>WebApp URL</h6>
                    <code>{{ url('/web-app') }}#/dashboard?accountId={{ $account->id }}&adminView=true&userId={{ $user->id }}</code>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page-level-scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ url('js/monitoring-listener.js') }}"></script>
<script>
    // Handle iframe communication if needed
    window.addEventListener('message', function(event) {
        // Handle messages from the embedded webapp iframe
        console.log('Message from webapp:', event.data);
    });

    // Right-click debugging will be handled by the Vue app itself
    console.log('WebApp embedded for account:', {{ $account->id }}, 'user:', {{ $user->id }});
    
    // Monitoring toggle button handler
    (function() {
        const monitorBtn = document.getElementById('monitor-toggle-btn');
        const monitorBtnText = document.getElementById('monitor-btn-text');
        const timelineCard = document.getElementById('monitoring-timeline-card');
        const sessionIdSpan = document.getElementById('monitoring-session-id');
        let currentSessionId = null;
        let eventPollInterval = null;

        monitorBtn.addEventListener('click', async function() {
            const isActive = this.classList.contains('btn-danger');
            
            if (isActive) {
                // Stop monitoring
                if (currentSessionId) {
                    try {
                        const response = await fetch(`/admin/monitoring/stop/${currentSessionId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            // Stop polling
                            if (eventPollInterval) {
                                clearInterval(eventPollInterval);
                                eventPollInterval = null;
                            }
                            
                            // Stop browser listeners
                            if (window.monitoringListener) {
                                window.monitoringListener.stop();
                            }
                            
                            // Update button
                            this.classList.remove('btn-danger');
                            this.classList.add('btn-primary');
                            monitorBtnText.textContent = 'Start Monitoring';
                            this.querySelector('i').className = 'fas fa-play';
                            this.setAttribute('data-session-id', '');
                            
                            // Show timeline
                            await loadTimeline(currentSessionId);
                            timelineCard.style.display = 'block';
                            
                            currentSessionId = null;
                        }
                    } catch (error) {
                        console.error('Failed to stop monitoring:', error);
                        alert('Failed to stop monitoring. Check console for details.');
                    }
                }
            } else {
                // Start monitoring
                try {
                    const response = await fetch('/admin/monitoring/start', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        currentSessionId = data.sessionId;
                        this.setAttribute('data-session-id', currentSessionId);
                        sessionIdSpan.textContent = data.sessionId;
                        
                        // Update button
                        this.classList.remove('btn-primary');
                        this.classList.add('btn-danger');
                        monitorBtnText.textContent = 'Stop Monitoring';
                        this.querySelector('i').className = 'fas fa-stop';
                        
                        // Start browser listeners
                        if (window.monitoringListener) {
                            window.monitoringListener.start(currentSessionId);
                        }
                        
                        // Start polling for events
                        eventPollInterval = setInterval(async () => {
                            await loadTimeline(currentSessionId);
                        }, 2000); // Poll every 2 seconds
                        
                        // Hide timeline initially
                        timelineCard.style.display = 'none';
                    } else {
                        alert('Failed to start monitoring: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Failed to start monitoring:', error);
                    alert('Failed to start monitoring. Check console for details.');
                }
            }
        });

        async function loadTimeline(sessionId) {
            if (!sessionId) return;
            
            try {
                const response = await fetch(`/admin/monitoring/events/${sessionId}`);
                const data = await response.json();
                
                if (data.success && data.session) {
                    renderTimeline(data.session);
                }
            } catch (error) {
                console.error('Failed to load timeline:', error);
            }
        }

        // Format event timestamp - ensures no milliseconds displayed
        // Timestamp is in 'Y-m-d H:i:s' format from server
        function formatEventTime(timestamp) {
            if (!timestamp) return 'N/A';
            
            try {
                // Parse the timestamp (format: 'Y-m-d H:i:s')
                // Convert to Date object for formatting
                const date = new Date(timestamp.replace(' ', 'T'));
                
                // Format as date + time without milliseconds
                return date.toLocaleString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
            } catch (e) {
                // Fallback: return as-is if parsing fails
                return timestamp;
            }
        }

        function renderTimeline(session) {
            const container = document.getElementById('monitoring-timeline-content');
            const events = session.events || [];
            
            if (events.length === 0) {
                container.innerHTML = '<p class="text-muted">No events captured yet.</p>';
                return;
            }
            
            let html = `
                <div class="mb-3">
                    <strong>Session:</strong> ${session.session_id}<br>
                    <strong>Started:</strong> ${formatEventTime(session.started_at)}<br>
                    <strong>Status:</strong> <span class="badge badge-${session.status === 'active' ? 'success' : 'secondary'}">${session.status}</span><br>
                    <strong>Events:</strong> ${events.length}
                </div>
                <hr>
                <div class="timeline-events" style="max-height: 500px; overflow-y: auto;">
            `;
            
            events.forEach(event => {
                const severityClass = {
                    'info': 'info',
                    'warning': 'warning',
                    'error': 'danger',
                    'critical': 'dark'
                }[event.severity] || 'secondary';
                
                const sourceIcon = {
                    'nginx': 'fa-server',
                    'browser': 'fa-globe',
                    'container': 'fa-cube'
                }[event.source] || 'fa-circle';
                
                html += `
                    <div class="card mb-2 border-${severityClass}">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <small class="text-muted">${formatEventTime(event.timestamp)}</small>
                                    <span class="badge badge-${severityClass} ml-2">${event.severity}</span>
                                    <span class="badge badge-light ml-1"><i class="fas ${sourceIcon}"></i> ${event.source}</span>
                                    <strong class="ml-2">${event.type}</strong>
                                </div>
                            </div>
                            <div class="mt-2">
                                <pre class="mb-0" style="font-size: 0.85em; max-height: 150px; overflow: auto;">${JSON.stringify(event.data, null, 2)}</pre>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
    })();
</script>
@endpush
@endsection






















