@extends('admin.layouts.main')
@section('title', 'Meter Readings')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid" data-component="admin-container" data-component-id="edit-meter-reading-container-1">

        <!-- Page Heading -->
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="edit-meter-reading-title-1">Edit meter reading</h1>

        <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="edit-meter-reading-form-wrapper-1">
            <div class="row" data-component="form-row" data-component-id="edit-meter-reading-row-1">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('edit-meter-reading') }}" data-component="meter-reading-form" data-component-id="edit-meter-reading-form-1">
                        <div class="form-group" data-component="form-group" data-component-id="edit-meter-reading-meter-group-1">
                            <label data-component="form-label" data-component-id="edit-meter-reading-meter-label-1"><strong>Meter:</strong></label>
                            <select class="form-control" id="exampleFormControlSelect1" name="meter_id" required data-component="select-input" data-component-id="edit-meter-reading-meter-select-1">
                                <option disabled value="">--Select Meter--</option>
                                @foreach($meters as $meter)
                                    <option value="{{ $meter->id }}" {{ ($meter->id == $meterReading->meter_id)?'selected':'' }} data-component="select-option" data-component-id="edit-meter-reading-meter-option-{{ $meter->id }}">{{ $meter->meter_title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-meter-reading-date-group-1">
                            <label data-component="form-label" data-component-id="edit-meter-reading-date-label-1"><strong>Meter Reading Date:</strong></label>
                            <input type="date" class="form-control" value="{{ $meterReading->reading_date }}" placeholder="Enter meter reading date" name="reading_date" required data-component="date-input" data-component-id="edit-meter-reading-date-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-meter-reading-value-group-1">
                            <label data-component="form-label" data-component-id="edit-meter-reading-value-label-1"><strong>Reading Value:</strong></label>
                            <input type="text" class="form-control" value="{{ $meterReading->reading_value }}" placeholder="Enter meter reading value" name="reading_value" required data-component="text-input" data-component-id="edit-meter-reading-value-input-1">
                        </div>
                        <input type="hidden" name="meter_reading_id" value="{{ $meterReading->id }}" data-component="hidden-input" data-component-id="edit-meter-reading-id-input-1" />
                        @csrf
                        <button type="submit" class="btn btn-primary" data-component="submit-button" data-component-id="edit-meter-reading-submit-1">Submit</button>
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
