@extends('admin.layouts.main')
@section('title', 'Add Site')

@section('content')
    <div class="container-fluid" data-component="admin-container" data-component-id="create-site-container-1">
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="create-site-title-1">Add New Site</h1>

        <div class="card shadow mb-4" data-component="card" data-component-id="create-site-card-1">
            <div class="card-header py-3" data-component="card-header" data-component-id="create-site-header-1">
                <h6 class="m-0 font-weight-bold text-primary" data-component="card-title" data-component-id="create-site-card-title-1">Site Details</h6>
            </div>
            <div class="card-body" data-component="card-body" data-component-id="create-site-body-1">
                <form method="POST" action="{{ route('add-site') }}" data-component="site-form" data-component-id="create-site-form-1">
                    @csrf
                    
                    <div class="form-row" data-component="form-row" data-component-id="create-site-row-1">
                        <div class="form-group col-md-6" data-component="form-group" data-component-id="create-site-title-group-1">
                            <label data-component="form-label" data-component-id="create-site-title-label-1"><strong>Site Name / Title:</strong></label>
                            <input type="text" class="form-control" name="title" placeholder="e.g. Medina Towers" required data-component="text-input" data-component-id="create-site-title-input-1">
                        </div>
                        <div class="form-group col-md-6" data-component="form-group" data-component-id="create-site-user-group-1">
                            <label data-component="form-label" data-component-id="create-site-user-label-1"><strong>Site Owner (Client):</strong></label>
                            <select class="form-control" name="user_id" required data-component="select-input" data-component-id="create-site-user-select-1">
                                <option value="" disabled selected>-- Select Owner --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" data-component="select-option" data-component-id="create-site-user-option-{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row" data-component="form-row" data-component-id="create-site-row-2">
                        <div class="form-group col-md-6" data-component="form-group" data-component-id="create-site-region-group-1">
                            <label data-component="form-label" data-component-id="create-site-region-label-1"><strong>Region:</strong></label>
                            <select class="form-control" name="region_id" required data-component="select-input" data-component-id="create-site-region-select-1">
                                <option value="" disabled selected>-- Select Region --</option>
                                @foreach($regions as $region)
                                    <option value="{{ $region->id }}" data-component="select-option" data-component-id="create-site-region-option-{{ $region->id }}">{{ $region->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6" data-component="form-group" data-component-id="create-site-address-group-1">
                            <label data-component="form-label" data-component-id="create-site-address-label-1"><strong>Physical Address:</strong></label>
                            <input type="text" class="form-control" name="address" placeholder="e.g. 123 Main Street, Cape Town" required data-component="text-input" data-component-id="create-site-address-input-1">
                        </div>
                    </div>

                    <div class="form-row" data-component="form-row" data-component-id="create-site-row-3">
                        <div class="form-group col-md-6" data-component="form-group" data-component-id="create-site-billing-type-group-1">
                            <label data-component="form-label" data-component-id="create-site-billing-type-label-1"><strong>Billing Type:</strong></label>
                            <select class="form-control" name="billing_type" data-component="select-input" data-component-id="create-site-billing-type-select-1">
                                <option value="monthly" data-component="select-option" data-component-id="create-site-billing-monthly-1">Monthly</option>
                                <option value="prepaid" data-component="select-option" data-component-id="create-site-billing-prepaid-1">Prepaid</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6" data-component="form-group" data-component-id="create-site-email-group-1">
                            <label data-component="form-label" data-component-id="create-site-email-label-1"><strong>Contact Email (Optional):</strong></label>
                            <input type="email" class="form-control" name="email" placeholder="site@example.com" data-component="email-input" data-component-id="create-site-email-input-1">
                        </div>
                    </div>

                    <!-- Hidden fields for Lat/Lng set to 0 so the database doesn't complain -->
                    <input type="hidden" name="lat" value="0" data-component="hidden-input" data-component-id="create-site-lat-input-1">
                    <input type="hidden" name="lng" value="0" data-component="hidden-input" data-component-id="create-site-lng-input-1">

                    <button type="submit" class="btn btn-primary" data-component="submit-button" data-component-id="create-site-submit-1">Create Site</button>
                    <a href="{{ route('show-sites') }}" class="btn btn-secondary" data-component="cancel-button" data-component-id="create-site-cancel-1">Cancel</a>
                </form>
            </div>
        </div>
    </div>
@endsection
