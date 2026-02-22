@extends('admin.layouts.main')
@section('title', 'Edit Region')

@section('content')
<!-- Begin Page Content -->
<div class="container-fluid" data-component="admin-container" data-component-id="edit-region-container-1">

    <!-- Page Heading -->
    <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="edit-region-title-1">Edit Region</h1>

    <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="edit-region-form-wrapper-1">
        <div class="row" data-component="form-row" data-component-id="edit-region-row-1">
            <div class="col-md-6">
                <form method="POST" action="{{ route('edit-region') }}" data-component="region-form" data-component-id="edit-region-form-1">
                    <input type="hidden" name="id" value="{{ $region->id }}" data-component="hidden-input" data-component-id="edit-region-id-input-1" />
                    
                    <div class="form-group" data-component="form-group" data-component-id="edit-region-name-group-1">
                        <label data-component="form-label" data-component-id="edit-region-name-label-1"><strong>Region Name :</strong></label>
                        <input placeholder="Enter region name" type="text" class="form-control" name="name" value="{{ $region->name }}" required data-component="text-input" data-component-id="edit-region-name-input-1" />
                    </div>
                    <div class="form-group" data-component="form-group" data-component-id="edit-region-water-email-group-1">
                        <label data-component="form-label" data-component-id="edit-region-water-email-label-1"><strong>Water Email :</strong></label>
                        <input placeholder="Enter Water Email" type="email" class="form-control" name="water_email" value="{{ $region->water_email }}" data-component="email-input" data-component-id="edit-region-water-email-input-1" />
                    </div>
                    <div class="form-group" data-component="form-group" data-component-id="edit-region-electricity-email-group-1">
                        <label data-component="form-label" data-component-id="edit-region-electricity-email-label-1"><strong>Electricity Email :</strong></label>
                        <input placeholder="Enter Electricity Email" type="email" class="form-control" name="electricity_email" value="{{ $region->electricity_email }}" data-component="email-input" data-component-id="edit-region-electricity-email-input-1" />
                    </div>
                    <hr data-component="divider" data-component-id="edit-region-divider-1">
                    
                    @csrf
                    <button type="submit" class="btn btn-warning" data-component="submit-button" data-component-id="edit-region-submit-1">Update</button>
                    <a href="{{ route('regions-list') }}" class="btn btn-secondary" data-component="cancel-button" data-component-id="edit-region-cancel-1">Cancel</a>
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
        // Any additional JS can go here
    });
</script>
@endsection
