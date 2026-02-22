@extends('admin.layouts.main')
@section('title', 'Record Payment')

@section('content')
    <div class="container-fluid" data-component="admin-container" data-component-id="create-payment-container-1">
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="create-payment-title-1">Record New Payment</h1>

        <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="create-payment-form-wrapper-1">
            <div class="row" data-component="form-row" data-component-id="create-payment-row-1">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('add-payment') }}" data-component="payment-form" data-component-id="create-payment-form-1">
                        
                        <div class="form-group" data-component="form-group" data-component-id="create-payment-site-group-1">
                            <label data-component="form-label" data-component-id="create-payment-site-label-1"><strong>Select Site:</strong></label>
                            <select class="form-control" id="site_select" required data-component="select-input" data-component-id="create-payment-site-select-1">
                                <option value="" disabled selected>-- First Select Site --</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}" data-component="select-option" data-component-id="create-payment-site-option-{{ $site->id }}">{{ $site->title }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" data-component="form-group" data-component-id="create-payment-account-group-1">
                            <label data-component="form-label" data-component-id="create-payment-account-label-1"><strong>Select Account:</strong></label>
                            <select class="form-control" name="account_id" id="account_select" required disabled data-component="select-input" data-component-id="create-payment-account-select-1">
                                <option value="" disabled selected>-- Select Site First --</option>
                            </select>
                        </div>

                        <div class="form-group" data-component="form-group" data-component-id="create-payment-amount-group-1">
                            <label data-component="form-label" data-component-id="create-payment-amount-label-1"><strong>Amount (R):</strong></label>
                            <input type="number" step="0.01" class="form-control" name="amount" placeholder="0.00" required data-component="number-input" data-component-id="create-payment-amount-input-1">
                        </div>
                        
                        <div class="form-group" data-component="form-group" data-component-id="create-payment-date-group-1">
                            <label data-component="form-label" data-component-id="create-payment-date-label-1"><strong>Payment Date:</strong></label>
                            <input type="date" class="form-control" name="payment_date" value="{{ date('Y-m-d') }}" required data-component="date-input" data-component-id="create-payment-date-input-1">
                        </div>

                        <div class="form-group" data-component="form-group" data-component-id="create-payment-method-group-1">
                            <label data-component="form-label" data-component-id="create-payment-method-label-1"><strong>Payment Method:</strong></label>
                            <select class="form-control" name="payment_method" required data-component="select-input" data-component-id="create-payment-method-select-1">
                                <option value="EFT" selected data-component="select-option" data-component-id="create-payment-method-eft-1">EFT (Bank Transfer)</option>
                                <option value="Cash" data-component="select-option" data-component-id="create-payment-method-cash-1">Cash</option>
                                <option value="Card" data-component="select-option" data-component-id="create-payment-method-card-1">Card</option>
                                <option value="Debit Order" data-component="select-option" data-component-id="create-payment-method-debit-1">Debit Order</option>
                                <option value="Other" data-component="select-option" data-component-id="create-payment-method-other-1">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group" data-component="form-group" data-component-id="create-payment-reference-group-1">
                            <label data-component="form-label" data-component-id="create-payment-reference-label-1"><strong>Reference:</strong></label>
                            <input type="text" class="form-control" name="reference" placeholder="E.g. EFT-12345" data-component="text-input" data-component-id="create-payment-reference-input-1">
                        </div>
                        
                        <div class="form-group" data-component="form-group" data-component-id="create-payment-notes-group-1">
                            <label data-component="form-label" data-component-id="create-payment-notes-label-1"><strong>Notes:</strong></label>
                            <textarea class="form-control" name="notes" rows="3" data-component="textarea-input" data-component-id="create-payment-notes-textarea-1"></textarea>
                        </div>
                        
                        @csrf
                        <button type="submit" class="btn btn-primary" data-component="submit-button" data-component-id="create-payment-submit-1">Save Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-level-scripts')
<script type="text/javascript">
    $(document).ready(function() {
        $('#site_select').on('change', function() {
            var siteId = this.value;
            $("#account_select").html('<option value="">Loading...</option>');
            $("#account_select").prop('disabled', true);

            $.ajax({
                url: "{{ route('get-accounts-by-site') }}",
                type: "POST",
                data: {
                    site_id: siteId,
                    _token: '{{ csrf_token() }}'
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status == 200 && res.data.length > 0) {
                        $('#account_select').html('<option value="" disabled selected>-- Select Account --</option>');
                        $.each(res.data, function(key, value) {
                            $("#account_select").append('<option value="' + value.id + '">' + value.account_name + ' (' + value.account_number + ')</option>');
                        });
                        $("#account_select").prop('disabled', false);
                    } else {
                        $('#account_select').html('<option value="">No accounts found for this site</option>');
                    }
                },
                error: function() {
                    $('#account_select').html('<option value="">Error loading accounts</option>');
                }
            });
        });
    });
</script>
@endsection
