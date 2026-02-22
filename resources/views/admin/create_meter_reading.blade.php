@extends('admin.layouts.main')
@section('title', 'Meter Readings')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid" data-component="admin-container" data-component-id="create-meter-reading-container-1">

        <!-- Page Heading -->
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="create-meter-reading-title-1">Add new reading to a meter</h1>

        <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="create-meter-reading-form-wrapper-1">
            <div class="row" data-component="form-row" data-component-id="create-meter-reading-row-1">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('add-meter-reading') }}" enctype="multipart/form-data" data-component="meter-reading-form" data-component-id="create-meter-reading-form-1">
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-reading-meter-group-1">
                            <label data-component="form-label" data-component-id="create-meter-reading-meter-label-1"><strong>Meter:</strong></label>
                            <select class="form-control" id="exampleFormControlSelect1" name="meter_id" required data-component="select-input" data-component-id="create-meter-reading-meter-select-1">
                                <option disabled selected value="">--Select Meter--</option>
                                @foreach($meters as $meter)
                                    <option value="{{ $meter->id }}" data-component="select-option" data-component-id="create-meter-reading-meter-option-{{ $meter->id }}">{{ $meter->meter_title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-reading-image-group-1">
                            <label data-component="form-label" data-component-id="create-meter-reading-image-label-1"><strong>Meter Reading Image:</strong></label>
                            <input type="file" name="reading_image" data-component="file-input" data-component-id="create-meter-reading-image-input-1" />
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-reading-date-group-1">
                            <label data-component="form-label" data-component-id="create-meter-reading-date-label-1"><strong>Reading Date:</strong></label>
                            <input type="date" class="form-control" placeholder="Enter meter reading date" name="reading_date" required data-component="date-input" data-component-id="create-meter-reading-date-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-meter-reading-value-group-1">
                            <label data-component="form-label" data-component-id="create-meter-reading-value-label-1"><strong>Reading Value:</strong></label>
                            <input type="text" class="form-control" placeholder="Enter meter reading value" name="reading_value" required data-component="text-input" data-component-id="create-meter-reading-value-input-1">
                        </div>
                        @csrf
                        <button type="submit" class="btn btn-primary" data-component="submit-button" data-component-id="create-meter-reading-submit-1">Submit</button>
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
