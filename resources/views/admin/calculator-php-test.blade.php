@extends('admin.layouts.main')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">CalculatorPHP Test Page</h1>
        <span class="badge badge-info">Rev1 - Clean Implementation</span>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Test CalculatorPHP</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>CalculatorPHP (Rev1)</strong> - This is a clean, isolated implementation 
                        totally independent from legacy BillingCalculatorPeriodToPeriod.
                    </div>

                    <form id="testCalculatorForm">
                        <div class="form-group">
                            <label for="account_id">Account ID</label>
                            <input type="number" class="form-control" id="account_id" name="account_id" required>
                        </div>

                        <div class="form-group">
                            <label for="bill_id">Bill ID</label>
                            <input type="number" class="form-control" id="bill_id" name="bill_id" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play"></i> Test CalculatorPHP
                        </button>
                    </form>

                    <div id="result" class="mt-4" style="display: none;">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="m-0">Result</h6>
                            </div>
                            <div class="card-body">
                                <pre id="resultContent" class="bg-light p-3 rounded"></pre>
                            </div>
                        </div>
                    </div>

                    <div id="error" class="mt-4" style="display: none;">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h6 class="m-0">Error</h6>
                            </div>
                            <div class="card-body">
                                <pre id="errorContent" class="bg-light p-3 rounded text-danger"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('testCalculatorForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const accountId = document.getElementById('account_id').value;
    const billId = document.getElementById('bill_id').value;
    const resultDiv = document.getElementById('result');
    const errorDiv = document.getElementById('error');
    const resultContent = document.getElementById('resultContent');
    const errorContent = document.getElementById('errorContent');
    
    // Hide previous results
    resultDiv.style.display = 'none';
    errorDiv.style.display = 'none';
    
    try {
        const response = await fetch('/api/v1/billing/test-calculator-php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                account_id: parseInt(accountId),
                bill_id: parseInt(billId)
            })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            resultContent.textContent = JSON.stringify(data, null, 2);
            resultDiv.style.display = 'block';
        } else {
            errorContent.textContent = JSON.stringify(data, null, 2);
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorContent.textContent = 'Error: ' + error.message;
        errorDiv.style.display = 'block';
    }
});
</script>
@endsection




