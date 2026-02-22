@extends('admin.layouts.main')
@section('title', 'Users')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid" data-component="admin-container" data-component-id="edit-site-container-1">

        <!-- Page Heading -->
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="edit-site-title-1">Edit Site</h1>

        <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="edit-site-form-wrapper-1">
            <div class="row" data-component="form-row" data-component-id="edit-site-row-1">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('edit-site') }}" data-component="site-form" data-component-id="edit-site-form-1">
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-region-group-1">
                            <label data-component="form-label" data-component-id="edit-site-region-label-1"><strong>Region:</strong></label>
                            <select class="form-control" id="exampleFormControlSelect1" name="region_id" required data-component="select-input" data-component-id="edit-site-region-select-1">
                                <option disabled selected value="">--Select Region--</option>
                                @foreach($regions as $region)
                                    <option value="{{ $region->id }}" {{ ($site->region_id == $region->id) ? 'selected' : '' }} data-component="select-option" data-component-id="edit-site-region-option-{{ $region->id }}">{{ $region->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-user-group-1">
                            <label data-component="form-label" data-component-id="edit-site-user-label-1"><strong>User:</strong></label>
                            <select class="form-control" id="exampleFormControlSelect1" name="user_id" required data-component="select-input" data-component-id="edit-site-user-select-1">
                                <option disabled value="">--Select User--</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ ($site->user_id == $user->id) ? 'selected' : '' }} data-component="select-option" data-component-id="edit-site-user-option-{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-title-group-1">
                            <label data-component="form-label" data-component-id="edit-site-title-label-1"><strong>Title:</strong></label>
                            <input type="text" class="form-control" placeholder="Enter title" name="title" required value="{{ $site->title }}" data-component="text-input" data-component-id="edit-site-title-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-lat-group-1">
                            <label data-component="form-label" data-component-id="edit-site-lat-label-1"><strong>Latitude:</strong></label>
                            <input type="text" class="form-control" placeholder="Enter lat" name="lat" required value="{{ $site->lat }}" data-component="text-input" data-component-id="edit-site-lat-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-lng-group-1">
                            <label data-component="form-label" data-component-id="edit-site-lng-label-1"><strong>Longitude:</strong></label>
                            <input type="text" name="lng" class="form-control" id="exampleInputEmail1" required value="{{ $site->lng }}" aria-describedby="emailHelp" placeholder="Enter lng" data-component="text-input" data-component-id="edit-site-lng-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-email-group-1">
                            <label data-component="form-label" data-component-id="edit-site-email-label-1"><strong>Email:</strong></label>
                            <input type="email" name="email" class="form-control" id="exampleInputPassword1" value="{{ $site->email }}" placeholder="Enter email" data-component="email-input" data-component-id="edit-site-email-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-address-group-1">
                            <label data-component="form-label" data-component-id="edit-site-address-label-1"><strong>Address:</strong></label>
                            <textarea name="address" placeholder="Enter address" class="form-control" rows="4" data-component="textarea-input" data-component-id="edit-site-address-textarea-1">{{ $site->address }}</textarea>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-billing-type-group-1">
                            <label data-component="form-label" data-component-id="edit-site-billing-type-label-1"><strong>Billing Type:</strong></label>
                            <select class="form-control" name="billing_type" data-component="select-input" data-component-id="edit-site-billing-type-select-1">
                                <option value="monthly" {{ ($site->billing_type == 'monthly' || $site->billing_type == null) ? 'selected' : '' }} data-component="select-option" data-component-id="edit-site-billing-monthly-1">Monthly</option>
                                <option value="date_to_date" {{ $site->billing_type == 'date_to_date' ? 'selected' : '' }} data-component="select-option" data-component-id="edit-site-billing-date-to-date-1">Date-to-Date</option>
                            </select>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-username-group-1">
                            <label data-component="form-label" data-component-id="edit-site-username-label-1"><strong>Site Username:</strong></label>
                            <input type="text" class="form-control" placeholder="Enter site username" name="site_username" value="{{ $site->site_username }}" data-component="text-input" data-component-id="edit-site-username-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-site-password-group-1">
                            <label data-component="form-label" data-component-id="edit-site-password-label-1"><strong>Site Password:</strong></label>
                            <input type="password" class="form-control" placeholder="Leave blank to keep unchanged" name="site_password" data-component="password-input" data-component-id="edit-site-password-input-1">
                        </div>
                        <input type="hidden" name="site_id" value="{{ $site->id }}" data-component="hidden-input" data-component-id="edit-site-id-input-1" />
                        @csrf
                        <button type="submit" class="btn btn-primary" data-component="submit-button" data-component-id="edit-site-submit-1">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /.container-fluid -->
@endsection

@section('page-level-scripts')
    <script type="text/javascript">
        $(document).ready(function() {
            $('#user-dataTable').dataTable();
        });
    </script>
@endsection
