@extends('admin.layouts.main')
@section('title', 'Dashboard Errors - ' . $account->account_name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                        Configuration Errors - {{ $account->account_name }}
                    </h6>
                    <div>
                        <a href="{{ route('user-accounts.dashboard', ['accountId' => $account->id]) }}" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="{{ route('user-accounts.manager') }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-wrench"></i> Fix in Account Manager
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(empty($errors))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> No errors found. All configuration is correct.
                        </div>
                    @else
                        @foreach($errors as $index => $error)
                            <div class="alert alert-danger mb-3">
                                <h5 class="alert-heading">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ $error['user_message'] ?? $error['message'] ?? 'Configuration Error' }}
                                </h5>
                                <hr>
                                <p class="mb-2"><strong>Error Code:</strong> {{ $error['code'] ?? 'UNKNOWN' }}</p>
                                @if(isset($error['details']))
                                    <p class="mb-2"><strong>Details:</strong></p>
                                    <pre class="bg-light p-3 rounded" style="font-size: 12px;">{{ $error['details'] }}</pre>
                                @endif
                                <p class="mb-0 mt-3">
                                    <strong>Action Required:</strong>
                                    @if($error['code'] === 'MISSING_BILL_DAY')
                                        Set the billing day in the account settings or tariff template.
                                    @elseif($error['code'] === 'MISSING_VAT_RATE')
                                        Set the VAT percentage in the tariff template.
                                    @elseif($error['code'] === 'INCOMPLETE_BILL_DATA')
                                        The tariff template is missing required fields. Check all tariff settings.
                                    @else
                                        Review the account and tariff template configuration.
                                    @endif
                                </p>
                            </div>
                        @endforeach
                        
                        <div class="mt-4">
                            <h6>How to Fix:</h6>
                            <ol>
                                <li>Go to <a href="{{ route('user-accounts.manager') }}">Account Manager</a></li>
                                <li>Find account: <strong>{{ $account->account_name }}</strong></li>
                                <li>Fix the configuration issues listed above</li>
                                <li>Return to dashboard - errors will clear automatically when fixed</li>
                            </ol>
                        </div>
                        
                        <div class="mt-4">
                            <form action="{{ route('dashboard-errors.clear', ['accountId' => $account->id]) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-check"></i> Mark as Fixed (Clear Errors)
                                </button>
                                <small class="text-muted d-block mt-2">
                                    Note: Errors will return if the issue is not actually fixed.
                                </small>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



















