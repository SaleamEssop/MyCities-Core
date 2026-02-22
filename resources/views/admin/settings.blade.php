@extends('admin.layouts.main')
@section('title', 'Application Settings')

@section('content')
    <div class="container-fluid" data-component="settings-container" data-component-id="settings-container-1">
        <div class="cust-page-head mb-3" data-component="page-header" data-component-id="settings-page-header-1">
            <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="settings-page-title-1">Application Settings</h1>
        </div>

        @if(Session::has('alert-message'))
            <div class="alert {{ Session::get('alert-class') }}" data-component="alert-message" data-component-id="settings-alert-1">
                {{ Session::get('alert-message') }}
            </div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}" data-component="settings-form" data-component-id="settings-form-1">
            @csrf

            <!-- Demo Mode Card -->
            <div class="card shadow mb-4" data-component="settings-card" data-component-id="demo-mode-card-1">
                <div class="card-header py-3" data-component="card-header" data-component-id="demo-mode-header-1">
                    <h6 class="m-0 font-weight-bold" data-component="card-title" data-component-id="demo-mode-title-1">Demo / Production Mode</h6>
                </div>
                <div class="card-body" data-component="card-body" data-component-id="demo-mode-body-1">
                    <div class="form-group" data-component="form-group" data-component-id="demo-mode-form-group-1">
                        <label data-component="form-label" data-component-id="demo-mode-label-1"><strong>Application Mode</strong></label>
                        <div class="mt-2" data-component="radio-group" data-component-id="demo-mode-radio-group-1">
                            <div class="custom-control custom-radio mb-3" data-component="radio-control" data-component-id="production-radio-1">
                                <input type="radio" id="production_mode" name="demo_mode" value="0" 
                                       class="custom-control-input" {{ !($settings->demo_mode ?? true) ? 'checked' : '' }} data-component="radio-input" data-component-id="production-radio-input-1">
                                <label class="custom-control-label" for="production_mode" data-component="radio-label" data-component-id="production-radio-label-1">
                                    <strong>Production Mode</strong> (Live Data Only)
                                    <br>
                                    <small class="text-muted">Hides all demo/test users, ads, and pages. Only shows real production content.</small>
                                </label>
                            </div>

                            <div class="custom-control custom-radio" data-component="radio-control" data-component-id="demo-radio-1">
                                <input type="radio" id="demo_mode" name="demo_mode" value="1" 
                                       class="custom-control-input" {{ ($settings->demo_mode ?? true) ? 'checked' : '' }} data-component="radio-input" data-component-id="demo-radio-input-1">
                                <label class="custom-control-label" for="demo_mode" data-component="radio-label" data-component-id="demo-radio-label-1">
                                    <strong>Demo Mode</strong> (Include Test Content)
                                    <br>
                                    <small class="text-muted">Shows all content including seed users, test ads, and demo pages.</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Configuration Card -->
            <div class="card shadow mb-4" data-component="settings-card" data-component-id="database-card-1">
                <div class="card-header py-3 d-flex justify-content-between align-items-center" data-component="card-header" data-component-id="database-header-1">
                    <h6 class="m-0 font-weight-bold" data-component="card-title" data-component-id="database-title-1">Database Configuration</h6>
                    <span class="badge badge-{{ ($settings->db_mode ?? 'internal') === 'internal' ? 'primary' : 'success' }}" data-component="status-badge" data-component-id="database-status-badge-1">
                        Currently: {{ ($settings->db_mode ?? 'internal') === 'internal' ? 'Internal (Container)' : 'External' }}
                    </span>
                </div>
                <div class="card-body" data-component="card-body" data-component-id="database-body-1">
                    <div class="alert alert-warning" data-component="alert-warning" data-component-id="database-warning-1">
                        <strong><i class="fas fa-exclamation-triangle"></i> Important:</strong>
                        This controls which MySQL database Laravel connects to. Use <strong>Internal</strong> for testing with seed data. Switch to <strong>External</strong> for production with real users.
                    </div>

                    <div class="form-group" data-component="form-group" data-component-id="database-mode-form-group-1">
                        <label data-component="form-label" data-component-id="database-mode-label-1"><strong>Database Mode</strong></label>
                        <div class="mt-2" data-component="radio-group" data-component-id="database-mode-radio-group-1">
                            <div class="custom-control custom-radio mb-3" data-component="radio-control" data-component-id="db-internal-radio-1">
                                <input type="radio" id="db_mode_internal" name="db_mode" value="internal" 
                                       class="custom-control-input" {{ ($settings->db_mode ?? 'internal') === 'internal' ? 'checked' : '' }}
                                       onchange="toggleExternalDbFields()" data-component="radio-input" data-component-id="db-internal-radio-input-1">
                                <label class="custom-control-label" for="db_mode_internal" data-component="radio-label" data-component-id="db-internal-radio-label-1">
                                    <strong>Internal Database (Container MySQL)</strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-database text-primary"></i>
                                        Uses MySQL running inside Docker container. Perfect for testing and development.
                                        <br><strong>Data will be wiped</strong> when container is rebuilt with <code>docker compose down -v</code>
                                    </small>
                                </label>
                            </div>

                            <div class="custom-control custom-radio" data-component="radio-control" data-component-id="db-external-radio-1">
                                <input type="radio" id="db_mode_external" name="db_mode" value="external" 
                                       class="custom-control-input" {{ ($settings->db_mode ?? 'internal') === 'external' ? 'checked' : '' }}
                                       onchange="toggleExternalDbFields()" data-component="radio-input" data-component-id="db-external-radio-input-1">
                                <label class="custom-control-label" for="db_mode_external" data-component="radio-label" data-component-id="db-external-radio-label-1">
                                    <strong>External Database (Production)</strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-server text-success"></i>
                                        Connects to external MySQL server (e.g., separate DigitalOcean droplet). 
                                        <br><strong>Data is safe</strong> during container rebuilds. Use this for live production.
                                    </small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- External Database Credentials (shown only when external mode selected) -->
                    <div id="external_db_fields" style="display: {{ ($settings->db_mode ?? 'internal') === 'external' ? 'block' : 'none' }};">
                        <hr class="my-4">
                        <h6 class="font-weight-bold mb-3">External MySQL Configuration</h6>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="external_db_host">Database Host <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('external_db_host') is-invalid @enderror" 
                                           id="external_db_host" name="external_db_host" 
                                           value="{{ old('external_db_host', $settings->external_db_host) }}"
                                           placeholder="e.g., 157.245.123.45 or db.mycities.co.za">
                                    @error('external_db_host')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="external_db_port">Port <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control @error('external_db_port') is-invalid @enderror" 
                                           id="external_db_port" name="external_db_port" 
                                           value="{{ old('external_db_port', $settings->external_db_port ?? 3306) }}"
                                           placeholder="3306">
                                    @error('external_db_port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="external_db_database">Database Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('external_db_database') is-invalid @enderror" 
                                           id="external_db_database" name="external_db_database" 
                                           value="{{ old('external_db_database', $settings->external_db_database) }}"
                                           placeholder="mycities_production">
                                    @error('external_db_database')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="external_db_username">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('external_db_username') is-invalid @enderror" 
                                           id="external_db_username" name="external_db_username" 
                                           value="{{ old('external_db_username', $settings->external_db_username) }}"
                                           placeholder="mycities_user">
                                    @error('external_db_username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="external_db_password">Password 
                                @if($settings->external_db_password)
                                    <small class="text-muted">(leave blank to keep current password)</small>
                                @else
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <input type="password" class="form-control @error('external_db_password') is-invalid @enderror" 
                                   id="external_db_password" name="external_db_password" 
                                   placeholder="Enter MySQL password">
                            @error('external_db_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <strong><i class="fas fa-shield-alt"></i> Security:</strong>
                            Password is encrypted before storage. Connection is tested before saving.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deployment Status Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-rocket"></i> Deployment Status
                    </h6>
                    @if($deploymentInfo['version_display'])
                        <span class="badge badge-primary" style="font-size: 1.1em; padding: 0.5em 1em;">
                            {{ $deploymentInfo['version_display'] }}
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if($deploymentInfo['version'])
                        <!-- Version Display - Large and Prominent -->
                        <div class="text-center mb-4 p-4 bg-light rounded">
                            <h2 class="mb-0 text-primary">
                                <i class="fas fa-code-branch"></i> {{ $deploymentInfo['version_display'] ?? 'v' . $deploymentInfo['version'] }}
                            </h2>
                            <p class="text-muted mb-0 mt-2">Current Server Version</p>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <strong><i class="fas fa-clock"></i> Last Deployed:</strong><br>
                                        <span class="h5">
                                            @if($deploymentInfo['deployment_time'])
                                                {{ $deploymentInfo['deployment_time'] }}
                                            @else
                                                Time not available
                                            @endif
                                        </span>
                                        @if($deploymentInfo['deployment_id'])
                                            <br><small class="text-muted">Push ID: {{ $deploymentInfo['deployment_id'] }}</small>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <strong><i class="fas fa-flag"></i> Deployment Status:</strong><br>
                                        @if($deploymentInfo['deployment_status'] === 'SUCCESS')
                                            <span class="badge badge-success" style="font-size: 1.2em; padding: 0.5em 1em;">
                                                <i class="fas fa-check-circle"></i> SUCCESS
                                            </span>
                                        @elseif($deploymentInfo['deployment_status'] === 'FAILED')
                                            <span class="badge badge-danger" style="font-size: 1.2em; padding: 0.5em 1em;">
                                                <i class="fas fa-times-circle"></i> FAILED
                                            </span>
                                        @else
                                            <span class="badge badge-secondary" style="font-size: 1.2em; padding: 0.5em 1em;">
                                                <i class="fas fa-question-circle"></i> UNKNOWN
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Repository Commits -->
                        @if($deploymentInfo['infra_commit'] || $deploymentInfo['laravel_commit'] || $deploymentInfo['vue_commit'])
                            <hr>
                            <h6 class="font-weight-bold mb-3">
                                <i class="fas fa-code"></i> Deployed Commits
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Repository</th>
                                            <th>Commit Hash</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><i class="fas fa-cogs text-primary"></i> Infrastructure</td>
                                            <td>
                                                @if($deploymentInfo['infra_commit'])
                                                    <code>{{ substr($deploymentInfo['infra_commit'], 0, 8) }}</code>
                                                    <small class="text-muted d-none d-md-inline">({{ $deploymentInfo['infra_commit'] }})</small>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><i class="fab fa-laravel text-danger"></i> Laravel</td>
                                            <td>
                                                @if($deploymentInfo['laravel_commit'])
                                                    <code>{{ substr($deploymentInfo['laravel_commit'], 0, 8) }}</code>
                                                    <small class="text-muted d-none d-md-inline">({{ $deploymentInfo['laravel_commit'] }})</small>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><i class="fab fa-vuejs text-success"></i> Vue-Quasar</td>
                                            <td>
                                                @if($deploymentInfo['vue_commit'])
                                                    <code>{{ substr($deploymentInfo['vue_commit'], 0, 8) }}</code>
                                                    <small class="text-muted d-none d-md-inline">({{ $deploymentInfo['vue_commit'] }})</small>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>No deployment tracking information available.</strong><br>
                            <small>Deployment tracking will appear here after running <code>deploy.bat</code></small>
                        </div>
                    @endif
                    
                    <!-- Repository Sync Status -->
                    @if(!empty($deploymentInfo['repos_sync_status']))
                        <hr>
                        <h6 class="font-weight-bold mb-3">
                            <i class="fas fa-sync-alt"></i> GitHub Sync Status
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Repository</th>
                                        <th>Status</th>
                                        <th>Server</th>
                                        <th>GitHub</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deploymentInfo['repos_sync_status'] as $repo)
                                        <tr>
                                            <td><strong>{{ $repo['name'] }}</strong></td>
                                            <td>
                                                @if($repo['error'])
                                                    <span class="badge badge-secondary">
                                                        <i class="fas fa-question-circle"></i> Error
                                                    </span>
                                                @elseif($repo['in_sync'])
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-check-circle"></i> In Sync
                                                    </span>
                                                @elseif($repo['behind'])
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> Behind
                                                    </span>
                                                @else
                                                    <span class="badge badge-warning">
                                                        <i class="fas fa-info-circle"></i> Diverged
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($repo['server_commit'])
                                                    <code>{{ substr($repo['server_commit'], 0, 8) }}</code>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($repo['github_commit'])
                                                    <code>{{ substr($repo['github_commit'], 0, 8) }}</code>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        @php
                            $outOfSync = collect($deploymentInfo['repos_sync_status'])->filter(function($repo) {
                                return !$repo['error'] && !$repo['in_sync'];
                            })->count();
                        @endphp
                        
                        @if($outOfSync > 0)
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Warning:</strong> {{ $outOfSync }} repository(s) are out of sync with GitHub.
                                <br><small>Run <code>deploy.bat</code> to sync the server with the latest GitHub commits.</small>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Schema Sync Info Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-sync"></i> Database Schema Synchronization
                    </h6>
                </div>
                <div class="card-body">
                    <p>
                        <strong>Important:</strong> When switching between databases, ensure both have identical schemas.
                    </p>
                    <p class="mb-0">
                        <strong>To keep schemas synchronized:</strong><br>
                        Run this command after any database migration:
                    </p>
                    <pre class="bg-light p-3 mt-2"><code>docker exec mycities-laravel php artisan db:migrate-both --force</code></pre>
                    <small class="text-muted">
                        This command runs migrations on BOTH internal and external databases automatically.
                    </small>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="text-right mb-4" data-component="submit-section" data-component-id="settings-submit-section-1">
                <button type="submit" class="btn btn-primary btn-lg" data-component="submit-button" data-component-id="settings-submit-button-1">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
            </div>

        </form>

        <script>
        function toggleExternalDbFields() {
            const isExternal = document.getElementById('db_mode_external').checked;
            document.getElementById('external_db_fields').style.display = isExternal ? 'block' : 'none';
        }
        </script>
    </div>
@endsection
