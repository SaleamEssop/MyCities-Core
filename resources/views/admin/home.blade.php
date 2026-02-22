@extends('admin.layouts.main')
@section('title', 'Dashboard')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">

        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
            <div class="d-flex align-items-center">
                <!-- Monitor Button -->
                <button id="monitor-toggle-btn" class="btn btn-primary shadow-sm mr-2" data-session-id="" style="min-width: 160px;">
                    <i class="fas fa-play"></i> <span id="monitor-btn-text">Start Monitoring</span>
                </button>
                <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
                        class="fas fa-download fa-sm text-white-50"></i> Generate Report</a>
            </div>
        </div>

        <!-- Content Row -->
        {{--<div class="row">

            <!-- Earnings (Monthly) Card Example -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Earnings (Monthly)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">$40,000</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Earnings (Monthly) Card Example -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Earnings (Annual)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">$215,000</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Earnings (Monthly) Card Example -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Tasks
                                </div>
                                <div class="row no-gutters align-items-center">
                                    <div class="col-auto">
                                        <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">50%</div>
                                    </div>
                                    <div class="col">
                                        <div class="progress progress-sm mr-2">
                                            <div class="progress-bar bg-info" role="progressbar"
                                                 style="width: 50%" aria-valuenow="50" aria-valuemin="0"
                                                 aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Requests Card Example -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Pending Requests</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">18</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-comments fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>--}}

        <!-- Content Row -->

        <div class="row">

            <!-- Area Chart -->
            <div class="col-xl-8 col-lg-7">
                <div class="card shadow mb-4">
                    <!-- Card Header - Dropdown -->
                    <div
                        class="card-header py-3 d-flex flex-row align-items-center justify-content-between" data-component="card-header" data-component-id="earnings-header-1">
                        <h6 class="m-0 font-weight-bold text-primary" data-component="card-title" data-component-id="earnings-title-1">Earnings Overview</h6>
                        <div class="dropdown no-arrow" data-component="dropdown-menu" data-component-id="earnings-dropdown-1">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-component="dropdown-toggle" data-component-id="earnings-dropdown-toggle-1">
                                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                                 aria-labelledby="dropdownMenuLink" data-component="dropdown-menu-items" data-component-id="earnings-dropdown-menu-1">
                                <div class="dropdown-header" data-component="dropdown-header" data-component-id="earnings-dropdown-header-1">Dropdown Header:</div>
                                <a class="dropdown-item" href="#" data-component="dropdown-item" data-component-id="earnings-dropdown-action-1">Action</a>
                                <a class="dropdown-item" href="#" data-component="dropdown-item" data-component-id="earnings-dropdown-action-2">Another action</a>
                                <div class="dropdown-divider" data-component="dropdown-divider" data-component-id="earnings-dropdown-divider-1"></div>
                                <a class="dropdown-item" href="#" data-component="dropdown-item" data-component-id="earnings-dropdown-action-3">Something else here</a>
                            </div>
                        </div>
                    </div>
                    <!-- Card Body -->
                    <div class="card-body" data-component="card-body" data-component-id="earnings-card-body-1">
                        <div class="chart-area" data-component="chart-container" data-component-id="area-chart-container-1">
                            <canvas id="myAreaChart" data-component="chart-canvas" data-component-id="area-chart-canvas-1"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pie Chart -->
            <div class="col-xl-4 col-lg-5">
                <div class="card shadow mb-4">
                    <!-- Card Header - Dropdown -->
                    <div
                        class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Revenue Sources</h6>
                        <div class="dropdown no-arrow" data-component="dropdown-menu" data-component-id="revenue-dropdown-1">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-component="dropdown-toggle" data-component-id="revenue-dropdown-toggle-1">
                                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                                 aria-labelledby="dropdownMenuLink" data-component="dropdown-menu-items" data-component-id="revenue-dropdown-menu-1">
                                <div class="dropdown-header" data-component="dropdown-header" data-component-id="revenue-dropdown-header-1">Dropdown Header:</div>
                                <a class="dropdown-item" href="#" data-component="dropdown-item" data-component-id="revenue-dropdown-action-1">Action</a>
                                <a class="dropdown-item" href="#" data-component="dropdown-item" data-component-id="revenue-dropdown-action-2">Another action</a>
                                <div class="dropdown-divider" data-component="dropdown-divider" data-component-id="revenue-dropdown-divider-1"></div>
                                <a class="dropdown-item" href="#" data-component="dropdown-item" data-component-id="revenue-dropdown-action-3">Something else here</a>
                            </div>
                        </div>
                    </div>
                    <!-- Card Body -->
                    <div class="card-body" data-component="card-body" data-component-id="revenue-card-body-1">
                        <div class="chart-pie pt-4 pb-2" data-component="chart-container" data-component-id="pie-chart-container-1">
                            <canvas id="myPieChart" data-component="chart-canvas" data-component-id="pie-chart-canvas-1"></canvas>
                        </div>
                        <div class="mt-4 text-center small">
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-primary"></i> Direct
                                        </span>
                            <span class="mr-2">
                                            <i class="fas fa-circle text-success"></i> Social
                                        </span>
                            <span class="mr-2">
                                            <i class="fas fa-circle text-info"></i> Referral
                                        </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Row -->
        <div class="row">

            <!-- Content Column -->
            <div class="col-lg-6 mb-4">

                <!-- Project Card Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Projects</h6>
                    </div>
                    <div class="card-body">
                        <h4 class="small font-weight-bold">Server Migration <span
                                class="float-right">20%</span></h4>
                        <div class="progress mb-4">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 20%"
                                 aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <h4 class="small font-weight-bold">Sales Tracking <span
                                class="float-right">40%</span></h4>
                        <div class="progress mb-4">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 40%"
                                 aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <h4 class="small font-weight-bold">Customer Database <span
                                class="float-right">60%</span></h4>
                        <div class="progress mb-4">
                            <div class="progress-bar" role="progressbar" style="width: 60%"
                                 aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <h4 class="small font-weight-bold">Payout Details <span
                                class="float-right">80%</span></h4>
                        <div class="progress mb-4">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 80%"
                                 aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <h4 class="small font-weight-bold">Account Setup <span
                                class="float-right">Complete!</span></h4>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%"
                                 aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>

                <!-- Color System -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-primary text-white shadow">
                            <div class="card-body">
                                Primary
                                <div class="text-white-50 small">#4e73df</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-success text-white shadow">
                            <div class="card-body">
                                Success
                                <div class="text-white-50 small">#1cc88a</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-info text-white shadow">
                            <div class="card-body">
                                Info
                                <div class="text-white-50 small">#36b9cc</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-warning text-white shadow">
                            <div class="card-body">
                                Warning
                                <div class="text-white-50 small">#f6c23e</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-danger text-white shadow">
                            <div class="card-body">
                                Danger
                                <div class="text-white-50 small">#e74a3b</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-secondary text-white shadow">
                            <div class="card-body">
                                Secondary
                                <div class="text-white-50 small">#858796</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-light text-black shadow">
                            <div class="card-body">
                                Light
                                <div class="text-black-50 small">#f8f9fc</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-dark text-white shadow">
                            <div class="card-body">
                                Dark
                                <div class="text-white-50 small">#5a5c69</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-lg-6 mb-4">

                <!-- Illustrations -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Illustrations</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <img class="img-fluid px-3 px-sm-4 mt-3 mb-4" style="width: 25rem;"
                                 src="{{ url('img/undraw_posting_photo.svg') }}" alt="...">
                        </div>
                        <p>Add some quality, svg illustrations to your project courtesy of <a
                                target="_blank" rel="nofollow" href="https://undraw.co/">unDraw</a>, a
                            constantly updated collection of beautiful svg images that you can use
                            completely free and without attribution!</p>
                        <a target="_blank" rel="nofollow" href="https://undraw.co/">Browse Illustrations on
                            unDraw &rarr;</a>
                    </div>
                </div>

                <!-- Approach -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Development Approach</h6>
                    </div>
                    <div class="card-body">
                        <p>SB Admin 2 makes extensive use of Bootstrap 4 utility classes in order to reduce
                            CSS bloat and poor page performance. Custom CSS classes are used to create
                            custom components and custom utility classes.</p>
                        <p class="mb-0">Before working with this theme, you should become familiar with the
                            Bootstrap framework, especially the utility classes.</p>
                    </div>
                </div>

            </div>
        </div>

    </div>
    <!-- /.container-fluid -->

    <!-- Monitoring Timeline (Shown when monitoring is active) -->
    <div id="monitoring-timeline-card" class="card shadow mb-4" style="display: none;" data-component="monitoring-timeline-card" data-component-id="monitoring-timeline-card-1">
        <div class="card-header py-3 bg-primary text-white" data-component="timeline-header" data-component-id="timeline-header-1">
            <h6 class="m-0 font-weight-bold" data-component="timeline-title" data-component-id="timeline-title-1">
                <i class="fas fa-chart-line"></i> Monitoring Timeline
                <span id="monitoring-session-id" class="badge badge-light ml-2" data-component="session-id-badge" data-component-id="session-id-badge-1"></span>
            </h6>
        </div>
        <div class="card-body" data-component="timeline-body" data-component-id="timeline-body-1">
            <div id="monitoring-timeline-content" data-component="timeline-content" data-component-id="timeline-content-1">
                <p class="text-muted">Loading events...</p>
            </div>
        </div>
    </div>
@endsection

@section('script')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ url('js/monitoring-listener.js') }}"></script>
<script>
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
            console.log('[Monitoring] Button clicked, isActive:', isActive, 'sessionId:', currentSessionId);
            
            if (isActive) {
                // Stop monitoring
                if (!currentSessionId) {
                    console.warn('[Monitoring] No session ID to stop');
                    // Still reset button state
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-primary');
                    if (monitorBtnText) {
                        monitorBtnText.textContent = 'Start Monitoring';
                    }
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-play';
                    }
                    this.setAttribute('data-session-id', '');
                    return;
                }
                
                const sessionIdToStop = currentSessionId; // Save before clearing
                console.log('[Monitoring] Stopping session:', sessionIdToStop);
                
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfToken) {
                        alert('CSRF token not found. Please refresh the page.');
                        return;
                    }
                    
                    // Update button FIRST - immediate feedback
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-primary');
                    if (monitorBtnText) {
                        monitorBtnText.textContent = 'Start Monitoring';
                    }
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-play';
                    }
                    this.setAttribute('data-session-id', '');
                    
                    // Stop polling immediately
                    if (eventPollInterval) {
                        clearInterval(eventPollInterval);
                        eventPollInterval = null;
                        console.log('[Monitoring] Polling stopped');
                    }
                    
                    // Stop browser listeners immediately
                    if (window.monitoringListener) {
                        window.monitoringListener.stop();
                        console.log('[Monitoring] Browser listeners stopped');
                    }
                    
                    // Clear session ID immediately
                    currentSessionId = null;
                    
                    // Then stop on server (don't await - do in background)
                    fetch(`/admin/monitoring/stop/${sessionIdToStop}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken.content
                        }
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) {
                              console.log('[Monitoring] Session stopped on server');
                              // Load final timeline
                              loadTimeline(sessionIdToStop).then(() => {
                                  if (timelineCard) {
                                      timelineCard.style.display = 'block';
                                  }
                              });
                          } else {
                              console.warn('[Monitoring] Failed to stop on server:', data.message);
                          }
                      })
                      .catch(error => {
                          console.error('[Monitoring] Error stopping on server:', error);
                      });
                } catch (error) {
                    console.error('[Monitoring] Failed to stop monitoring:', error);
                    alert('Failed to stop monitoring. Check console for details.');
                }
            } else {
                // Start monitoring
                console.log('[Monitoring] Starting monitoring...');
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfToken) {
                        alert('CSRF token not found. Please refresh the page.');
                        return;
                    }
                    
                    const response = await fetch('/admin/monitoring/start', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken.content
                        }
                    });
                    
                    console.log('[Monitoring] Response status:', response.status);
                    const data = await response.json();
                    console.log('[Monitoring] Response data:', data);
                    
                    if (data.success) {
                        currentSessionId = data.sessionId;
                        console.log('[Monitoring] Session ID:', currentSessionId);
                        
                        this.setAttribute('data-session-id', currentSessionId);
                        if (sessionIdSpan) {
                            sessionIdSpan.textContent = data.sessionId;
                        }
                        
                        // Update button FIRST - before any async operations
                        this.classList.remove('btn-primary');
                        this.classList.add('btn-danger');
                        if (monitorBtnText) {
                            monitorBtnText.textContent = 'Stop Monitoring';
                        }
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.className = 'fas fa-stop';
                        }
                        
                        console.log('[Monitoring] Button updated to red');
                        
                        // Start browser listeners
                        if (window.monitoringListener) {
                            window.monitoringListener.start(currentSessionId);
                            console.log('[Monitoring] Browser listeners started');
                        } else {
                            console.warn('[Monitoring] monitoringListener not available');
                        }
                        
                        // Start polling for events
                        if (eventPollInterval) {
                            clearInterval(eventPollInterval);
                        }
                        eventPollInterval = setInterval(async () => {
                            if (currentSessionId) {
                                await loadTimeline(currentSessionId);
                            } else {
                                if (eventPollInterval) {
                                    clearInterval(eventPollInterval);
                                    eventPollInterval = null;
                                }
                            }
                        }, 2000);
                        
                        // Show timeline
                        if (timelineCard) {
                            timelineCard.style.display = 'block';
                        }
                        
                        // Initial load (don't await - let it happen in background)
                        loadTimeline(currentSessionId).catch(err => {
                            console.error('[Monitoring] Error loading initial timeline:', err);
                        });
                    } else {
                        alert('Failed to start monitoring: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('[Monitoring] Failed to start monitoring:', error);
                    alert('Failed to start monitoring. Check console for details.');
                }
            }
        });

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

        async function loadTimeline(sessionId) {
            if (!sessionId) return;
            
            try {
                const response = await fetch(`/admin/monitoring/events/${sessionId}`);
                const data = await response.json();
                
                if (data.success && data.session) {
                    renderTimeline(data.session);
                } else if (!data.success) {
                    console.warn('[Monitoring] Failed to load timeline:', data.message);
                }
            } catch (error) {
                console.error('Failed to load timeline:', error);
            }
        }
        
        // Persist monitoring state across page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && currentSessionId && eventPollInterval) {
                // Page is visible again - verify monitoring is still active
                loadTimeline(currentSessionId);
            }
        });

        function renderTimeline(session) {
            const container = document.getElementById('monitoring-timeline-content');
            if (!container) return;
            
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
@endsection
