@extends('admin.layouts.main')
@section('title', 'Users')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid" data-component="admin-container" data-component-id="edit-account-container-1">

        <!-- Page Heading -->
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="edit-account-title-1">Edit Account</h1>

        <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="edit-account-form-wrapper-1">
            <div class="row" data-component="form-row" data-component-id="edit-account-row-1">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('edit-account') }}" id="edit-account-form" data-component="account-form" data-component-id="edit-account-form-1">
                        <div class="form-group" data-component="form-group" data-component-id="edit-account-user-group-1">
                            <div class="form-group" data-component="form-group" data-component-id="edit-account-user-inner-group-1">
                                <label data-component="form-label" data-component-id="edit-account-user-label-1">User: </label>
                                <select class="form-control" id="user-select" name="user_id" required data-component="select-input" data-component-id="edit-account-user-select-1">
                                    <option disabled selected value="">--Select User--</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ ($user->id == $account->site->user->id) ? 'selected' : '' }} data-component="select-option" data-component-id="edit-account-user-option-{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label data-component="form-label" data-component-id="edit-account-site-label-1"><strong>Site :</strong></label>
                            <select class="form-control" id="site-select" name="site_id" required data-component="select-input" data-component-id="edit-account-site-select-1">
                                <option disabled value="">--Select Site--</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}" {{ ($site->id == $account->site_id) ? 'selected' : '' }} data-component="select-option" data-component-id="edit-account-site-option-{{ $site->id }}">{{ $site->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Region Selection -->
                        <div class="form-group" data-component="form-group" data-component-id="edit-account-region-group-1">
                            <label data-component="form-label" data-component-id="edit-account-region-label-1"><strong>Region :</strong></label>
                            <select class="form-control" id="region-select" name="region_id" required data-component="select-input" data-component-id="edit-account-region-select-1">
                                <option disabled value="">--Select Region--</option>
                                @foreach($regions as $region)
                                    <option value="{{ $region->id }}" {{ ($account->tariffTemplate && $region->id == $account->tariffTemplate->region_id) ? 'selected' : '' }} data-component="select-option" data-component-id="edit-account-region-option-{{ $region->id }}">{{ $region->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Tariff Template Selection (populated via AJAX) -->
                        <div class="form-group" data-component="form-group" data-component-id="edit-account-tariff-group-1">
                            <label data-component="form-label" data-component-id="edit-account-tariff-label-1"><strong>Tariff Template :</strong></label>
                            <select class="form-control" id="tariff-template-select" name="tariff_template_id" required data-component="select-input" data-component-id="edit-account-tariff-select-1">
                                <option disabled value="">--Select Region First--</option>
                                @if($account->tariffTemplate)
                                    <option value="{{ $account->tariff_template_id }}" selected data-component="select-option" data-component-id="edit-account-tariff-option-{{ $account->tariff_template_id }}">{{ $account->tariffTemplate->template_name }}</option>
                                @endif
                            </select>
                            <small class="form-text text-muted" data-component="help-text" data-component-id="edit-account-tariff-help-1">Changing this may affect billing calculations.</small>
                        </div>

                        <div class="form-group" data-component="form-group" data-component-id="edit-account-name-group-1">
                            <label data-component="form-label" data-component-id="edit-account-name-label-1"><strong>Account Name :</strong></label>
                            <input type="text" value="{{ $account->account_name }}" class="form-control" placeholder="Enter account title" name="title" required data-component="text-input" data-component-id="edit-account-name-input-1" data-component-description="Account Name">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-account-number-group-1">
                            <label data-component="form-label" data-component-id="edit-account-number-label-1"><strong>Account Number :</strong></label>
                            <input type="text" value="{{ $account->account_number }}" class="form-control" placeholder="Enter account number" name="number" required data-component="text-input" data-component-id="edit-account-number-input-1" data-component-description="Account Number">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-account-billing-date-group-1">
                            <label data-component="form-label" data-component-id="edit-account-billing-date-label-1"><strong>Billing Date :</strong></label>
                            <input type="number" min="1" max="31" value="{{ $account->billing_date }}" class="form-control" placeholder="Enter billing date" name="billing_date" required data-component="number-input" data-component-id="edit-account-billing-date-input-1" data-component-description="Billing Date">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-account-optional-group-1">
                            <label data-component="form-label" data-component-id="edit-account-optional-label-1"><strong>Optional Information :</strong></label>
                            <input type="text" value="{{ $account->optional_information }}" name="optional_info" class="form-control" placeholder="Enter optional information" data-component="text-input" data-component-id="edit-account-optional-input-1" data-component-description="Optional Information">
                        </div>
                        <hr>
                        <p><u>Default Costs</u></p>
                        <div class="row">
                            <div class="col-md-4"><b>Title</b></div>
                            <div class="col-md-4"><b>Default Value</b></div>
                            <div class="col-md-4"><b>Your Value</b></div>
                        </div>
                        <br>
                        @foreach($account->defaultFixedCosts as $accDefaultCost)
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{ $accDefaultCost->fixedCost->title ?? null }}</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <input class="form-control" type="text" value="{{ $accDefaultCost->fixedCost->value ?? null }}" readonly/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <input class="form-control" type="number" name="default_cost_value[]" value="{{ $accDefaultCost->value ?? null }}" />
                                    </div>
                                </div>
                                <input type="hidden" name="default_ids[]" value="{{$accDefaultCost->id ?? null }}" />
                            </div>
                        @endforeach
                        <hr>
                        <p>Fixed Costs</p>
                        @foreach($account->fixedCosts as $fixedCost)
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <input value="{{ $fixedCost->title }}" class="form-control" type="text" placeholder="Enter title" name="additional_cost_name[]" required/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <input value="{{ $fixedCost->value }}" class="form-control" type="text" placeholder="Enter value" name="additional_cost_value[]" required/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <a href="#" data-id="{{ $fixedCost->id }}" class="btn btn-sm btn-circle btn-danger additional-cost-del-btn">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                                <input type="hidden" name="fixed_cost_id[]" value="{{ $fixedCost->id }}" />
                                <input type="hidden" name="fixed_cost_type[]" value="old" />
                            </div>

                        @endforeach
                        <input type="hidden" name="deleted" id="deletedCosts" value="" />
                        <input type="hidden" name="original_tariff_template_id" id="original_tariff_template_id" value="{{ $account->tariff_template_id }}" />
                        <div class="fixed-cost-container"></div>

                        <a href="#" id="add-cost" class="btn btn-sm btn-primary btn-circle"><i class="fa fa-plus"></i></a>
                        <br>
                        <br>
                        <input type="hidden" name="account_id" value="{{ $account->id }}" />
                        @csrf
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /.container-fluid -->

    <!-- Warning Modal for Tariff Template Change -->
    <div class="modal fade" id="tariffChangeWarningModal" tabindex="-1" role="dialog" aria-labelledby="tariffChangeWarningLabel" aria-hidden="true" data-component="modal" data-component-id="tariff-change-warning-modal-1">
        <div class="modal-dialog" role="document" data-component="modal-dialog" data-component-id="tariff-change-modal-dialog-1">
            <div class="modal-content" data-component="modal-content" data-component-id="tariff-change-modal-content-1">
                <div class="modal-header bg-warning text-dark" data-component="modal-header" data-component-id="tariff-change-modal-header-1">
                    <h5 class="modal-title" id="tariffChangeWarningLabel" data-component="modal-title" data-component-id="tariff-change-modal-title-1"><i class="fas fa-exclamation-triangle"></i> Warning</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" data-component="close-button" data-component-id="tariff-change-modal-close-1">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" data-component="modal-body" data-component-id="tariff-change-modal-body-1">
                    <p data-component="modal-text" data-component-id="tariff-change-modal-text-1">This account has existing meter readings. Changing the tariff template will affect future billing calculations.</p>
                    <p data-component="modal-text" data-component-id="tariff-change-modal-question-1"><strong>Are you sure you want to continue?</strong></p>
                </div>
                <div class="modal-footer" data-component="modal-footer" data-component-id="tariff-change-modal-footer-1">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" id="cancel-tariff-change" data-component="cancel-button" data-component-id="tariff-change-cancel-button-1">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirm-tariff-change" data-component="confirm-button" data-component-id="tariff-change-confirm-button-1">Yes, Change Tariff</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-level-scripts')
    <script type="text/javascript">
        $(document).ready(function() {
            $('#user-dataTable').dataTable();
            
            // Store whether account has meter readings (using efficient query)
            var hasMeterReadings = {{ $account->meters()->whereHas('readings')->exists() ? 'true' : 'false' }};
            var originalTariffTemplateId = $('#original_tariff_template_id').val();

            // Load tariff templates on page load if region is selected
            var selectedRegionId = $('#region-select').val();
            if(selectedRegionId) {
                loadTariffTemplates(selectedRegionId, '{{ $account->tariff_template_id }}');
            }

            // Load Tariff Templates when Region changes
            $(document).on("change", '#region-select', function () {
                let region_id = $(this).val();
                if(region_id) {
                    loadTariffTemplates(region_id);
                }
            });

            function loadTariffTemplates(regionId, selectedId) {
                $.ajax({
                    type: 'GET',
                    dataType: 'JSON',
                    url: '/admin/tariff-templates/by-region/' + regionId,
                    success: function (result) {
                        $('#tariff-template-select').empty();
                        $('#tariff-template-select').append('<option disabled selected value="">--Select Tariff Template--</option>');
                        
                        if(result.data && result.data.length > 0) {
                            $.each(result.data, function(key, value) {
                                let displayText = value.template_name + ' (' + value.start_date + ' to ' + value.end_date + ')';
                                let isSelected = selectedId && value.id == selectedId ? 'selected' : '';
                                $('#tariff-template-select').append($('<option>', { value: value.id, text: displayText, selected: isSelected }));
                            });
                            $('#tariff-template-select').prop('disabled', false);
                        } else {
                            $('#tariff-template-select').append('<option disabled value="">No tariff templates available for this region</option>');
                        }
                    }
                });
            }

            // Form submit handler with warning for tariff change
            $('#edit-account-form').on('submit', function(e) {
                var newTariffTemplateId = $('#tariff-template-select').val();
                
                if(hasMeterReadings && originalTariffTemplateId && newTariffTemplateId != originalTariffTemplateId) {
                    e.preventDefault();
                    $('#tariffChangeWarningModal').modal('show');
                    return false;
                }
            });

            // Confirm tariff change
            $('#confirm-tariff-change').on('click', function() {
                $('#tariffChangeWarningModal').modal('hide');
                // Submit the form without the warning check
                hasMeterReadings = false;
                $('#edit-account-form').submit();
            });

            // Cancel tariff change - revert to original
            $('#cancel-tariff-change').on('click', function() {
                $('#tariff-template-select').val(originalTariffTemplateId);
            });

            $("#add-cost").on("click", function () {

                var html = '<div class="row">\n' +
                    '                            <div class="col-md-4">\n' +
                    '                                <div class="form-group">\n' +
                    '                                    <input class="form-control" type="text" placeholder="Enter title" name="additional_cost_name[]" required/>\n' +
                    '                                </div>\n' +
                    '                            </div>\n' +
                    '                            <div class="col-md-4">\n' +
                    '                                <div class="form-group">\n' +
                    '                                    <input class="form-control" type="text" placeholder="Enter value" name="additional_cost_value[]" required/>\n' +
                    '                                </div>\n' +
                    '                            </div>\n' +
                    '                            <div class="col-md-4">\n' +
                    '                                <a href="#" data-id="" style="margin-top: 6px" class="btn btn-sm btn-circle btn-danger additional-cost-del-btn">\n' +
                    '                                    <i class="fa fa-trash"></i>\n' +
                    '                                </a>\n' +
                    '                            </div>\n' +
                    '                            <input type="hidden" name="fixed_cost_type[]" value="new" />\n' +
                    '                        </div>'

                $(".fixed-cost-container").append(html);
            });

            $(document).on("click", '.additional-cost-del-btn', function () {
                var ID = $(this).data('id');
                if(ID) {
                    var oldVal = $("#deletedCosts").val();
                    var newVal = oldVal + ',' + ID;
                    $("#deletedCosts").val(newVal);
                }
                $(this).parent().parent().remove();
            });

            $(document).on("change", '#user-select', function () {
                let user_id = $(this).val();
                let token = $("[name='_token']").val();
                // Get list of accounts added under this user
                $.ajax({
                    type: 'POST',
                    dataType: 'JSON',
                    headers: { 'X-CSRF-TOKEN': token },
                    url: '/admin/accounts/get-user-sites',
                    data: {user: user_id},
                    success: function (result) {
                        $('#site-select').empty();
                        $.each(result.details, function(key, value) {
                            $('#site-select').append($('<option>', {
                                value: value.id,
                                text: value.title
                            }));
                        });
                        $('#site-select').prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endsection
