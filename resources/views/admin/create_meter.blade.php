@extends('admin.layouts.main')
@section('title', 'Meters')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid" data-component="admin-container" data-component-id="create-meter-container-1">

        <!-- Page Heading -->
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="create-meter-title-1">Create new Meter</h1>

        <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="create-meter-form-wrapper-1">
            <div class="row" data-component="form-row" data-component-id="create-meter-row-1">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('add-meter') }}" data-component="meter-form" data-component-id="create-meter-form-1">
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-account-group-1">
                            <label data-component="form-label" data-component-id="create-meter-account-label-1"><strong>Account :</strong></label>
                            <select class="form-control" id="exampleFormControlSelect1" name="account_id" required data-component="select-input" data-component-id="create-meter-account-select-1">
                                <option disabled selected value="">--Select Account--</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" data-component="select-option" data-component-id="create-meter-account-option-{{ $account->id }}">{{ $account->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-category-group-1">
                            <label data-component="form-label" data-component-id="create-meter-category-label-1"><strong>Meter Category :</strong></label>
                            <select class="form-control" id="exampleFormControlSelect1" name="meter_cat_id" required data-component="select-input" data-component-id="create-meter-category-select-1">
                                <option disabled selected value="">--Select Meter Category--</option>
                                @foreach($meterCats as $meterCat)
                                    <option value="{{ $meterCat->id }}" data-component="select-option" data-component-id="create-meter-category-option-{{ $meterCat->id }}">{{ $meterCat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-type-group-1">
                            <label data-component="form-label" data-component-id="create-meter-type-label-1"><strong>Meter Type :</strong></label>
                            <select class="form-control" id="exampleFormControlSelect1" name="meter_type_id" required data-component="select-input" data-component-id="create-meter-type-select-1">
                                <option disabled selected value="">--Select Meter Type--</option>
                                @foreach($meterTypes as $meterType)
                                    <option value="{{ $meterType->id }}" data-component="select-option" data-component-id="create-meter-type-option-{{ $meterType->id }}">{{ $meterType->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-title-group-1">
                            <label data-component="form-label" data-component-id="create-meter-title-label-1"><strong>Meter Title :</strong></label>
                            <input type="text" class="form-control" placeholder="Enter meter title" name="title" required data-component="text-input" data-component-id="create-meter-title-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-number-group-1">
                            <label data-component="form-label" data-component-id="create-meter-number-label-1"><strong>Meter Number :</strong></label>
                            <input type="text" class="form-control" placeholder="Enter meter number" name="number" required data-component="text-input" data-component-id="create-meter-number-input-1">
                        </div>
                        @csrf
                        <button type="submit" class="btn btn-primary" data-component="submit-button" data-component-id="create-meter-submit-1">Submit</button>
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
