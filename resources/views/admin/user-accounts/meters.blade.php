@extends('admin.layouts.main')
@section('title', 'Manage Meters - ' . ($account->account_name ?? 'Unknown Account'))

@section('content')
<style>
    /* Mobile-first constraints - max width matches mobile viewport (414px = iPhone Pro Max) */
    .meters-container {
        max-width: 414px;
        width: 100%;
        margin: 0 auto;
        padding: 16px;
        background: #f8f9fa;
        min-height: 100vh;
        box-sizing: border-box;
    }
    
    @media (max-width: 414px) {
        .meters-container {
            max-width: 100%;
            padding: 12px;
        }
    }
    
    body {
        overflow-x: hidden;
    }
    
    * {
        box-sizing: border-box;
    }
    
    .unified-header {
        background: white;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .logo-text {
        font-size: 28px;
        font-style: italic;
    }
    
    .logo-my {
        color: #000;
        font-weight: 300;
    }
    
    .logo-cities {
        color: #3294B8;
        font-weight: 500;
    }
    
    .user-label {
        font-size: 14px;
        color: #000;
        margin-top: 4px;
    }
    
    .page-label {
        font-size: 14px;
        color: #000;
    }
    
    .header-divider {
        height: 1px;
        background: #e0e0e0;
        margin: 12px 0;
    }
    
    .page-title {
        font-size: 18px;
        font-weight: 700;
        color: #000;
        text-align: center;
        padding: 12px 0;
    }
    
    .meter-card {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .meter-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .meter-title {
        font-size: 16px;
        font-weight: 600;
        color: #000;
    }
    
    .meter-type-badge {
        font-size: 12px;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
    }
    
    .meter-type-badge.water {
        background: #3294B8;
        color: white;
    }
    
    .meter-type-badge.electricity {
        background: #FF9800;
        color: white;
    }
    
    .meter-info {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 4px;
    }
    
    .meter-value {
        font-size: 14px;
        font-weight: 600;
        color: #000;
    }
    
    .meter-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }
    
    .btn-small {
        flex: 1;
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        display: block;
    }
    
    .btn-edit {
        background: #3294B8;
        color: white;
    }
    
    .btn-delete {
        background: #dc3545;
        color: white;
    }
    
    .btn-add {
        width: 100%;
        padding: 12px;
        background: #3294B8;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: block;
        text-align: center;
        margin-bottom: 16px;
    }
    
    .btn-secondary {
        width: 100%;
        padding: 12px;
        background: #e0e0e0;
        color: #000;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: block;
        text-align: center;
        margin-bottom: 12px;
    }
    
    .empty-state {
        background: white;
        border-radius: 8px;
        padding: 32px 16px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 16px;
    }
    
    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }
    
    .empty-state-text {
        font-size: 14px;
        color: #6b7280;
    }
    
    .alert {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<div class="meters-container">
    <!-- Unified Header -->
    <div class="unified-header">
        <div class="header-top">
            <div>
                <div class="logo-text">
                    <span class="logo-my">My</span><span class="logo-cities">Cities</span>
                </div>
                <div class="user-label">{{ $account->site->user->name ?? 'Unknown User' }}</div>
            </div>
            <div class="page-label">Manage Meters</div>
        </div>
        <div class="header-divider"></div>
        <div class="page-title">{{ $account->account_name ?? 'Unknown Account' }}</div>
    </div>

    <!-- Alert Messages -->
    @if(session('alert-class') && session('alert-message'))
        <div class="alert {{ session('alert-class') }}">
            {{ session('alert-message') }}
        </div>
    @endif

    <!-- Add Meter Button -->
    <a href="{{ route('user-accounts.manager') }}" class="btn-add">
        ➕ Add New Meter
    </a>

    <!-- Meters List -->
    @if($meters && $meters->count() > 0)
        @foreach($meters as $meter)
            <div class="meter-card">
                <div class="meter-header">
                    <div>
                        <div class="meter-title">{{ $meter->meter_title ?? 'Untitled Meter' }}</div>
                        <div class="meter-info">Meter Number: {{ $meter->meter_number }}</div>
                    </div>
                    @if($meter->meterTypes)
                        <span class="meter-type-badge {{ strtolower($meter->meterTypes->title ?? '') }}">
                            {{ $meter->meterTypes->title ?? 'Unknown' }}
                        </span>
                    @endif
                </div>
                
                <div class="meter-info">Type: {{ $meter->meterTypes->title ?? 'Unknown' }}</div>
                
                @php
                    $latestReading = $meter->readings()->orderBy('reading_date', 'desc')->first();
                @endphp
                
                @if($latestReading)
                    <div class="meter-info">Latest Reading:</div>
                    <div class="meter-value">
                        {{ $latestReading->reading_value }} 
                        ({{ $latestReading->reading_date->format('d M Y') }})
                    </div>
                @else
                    <div class="meter-info">No readings yet</div>
                @endif
                
                <div class="meter-actions">
                    <a href="{{ route('user-accounts.readings.add', $account->id) }}?meter_id={{ $meter->id }}" class="btn-small btn-edit">
                        Add Reading
                    </a>
                    <button type="button" class="btn-small btn-delete" onclick="deleteMeter({{ $meter->id }})">
                        Delete
                    </button>
                </div>
            </div>
        @endforeach
    @else
        <div class="empty-state">
            <div class="empty-state-icon">📊</div>
            <div class="empty-state-text">No meters added yet</div>
        </div>
    @endif

    <!-- Navigation Buttons -->
    <a href="{{ route('user-accounts.dashboard', $account->id) }}" class="btn-secondary">
        Back to Dashboard
    </a>
    <a href="{{ route('user-accounts.edit', $account->id) }}" class="btn-secondary">
        Edit Account
    </a>
</div>

<script>
function deleteMeter(meterId) {
    if (!confirm('Are you sure you want to delete this meter? This will also delete all readings associated with it.')) {
        return;
    }
    
    // Use the existing API endpoint
    fetch(`/admin/user-accounts/manager/meter/${meterId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 200) {
            location.reload();
        } else {
            alert('Error deleting meter: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting meter. Please try again.');
    });
}
</script>
@endsection











