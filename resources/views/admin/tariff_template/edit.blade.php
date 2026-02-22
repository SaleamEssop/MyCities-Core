@extends('admin.layouts.main')
@section('title', 'Edit Tariff Template')

@section('content')
<!-- Begin Page Content -->
<div class="container-fluid" data-component="admin-container" data-component-id="edit-tariff-template-container-1">

    <!-- Page Heading -->
    <div style="float: right;" data-component="action-buttons" data-component-id="edit-tariff-template-actions-1">
        <button type="button" class="btn btn-success" onclick="showCopyModal()" data-component="copy-button" data-component-id="edit-tariff-template-copy-button-1">
            <i class="fas fa-copy"></i> Make a Copy
        </button>
    </div>
    <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="edit-tariff-template-title-1">Edit Tariff Template</h1>
    
    <!-- Parent Hierarchy Display -->
    @if($tariff_template->parent_id)
    <div class="alert alert-info mb-3" style="clear: both;">
        <strong><i class="fas fa-sitemap"></i> Tariff Hierarchy:</strong>
        <div class="mt-2" id="hierarchy-display">
            @php
                $hierarchy = [];
                $current = $tariff_template;
                while ($current->parent_id) {
                    $parent = \App\Models\RegionsAccountTypeCost::find($current->parent_id);
                    if ($parent) {
                        array_unshift($hierarchy, $parent);
                        $current = $parent;
                    } else {
                        break;
                    }
                }
            @endphp
            @foreach($hierarchy as $index => $ancestor)
                <span class="badge badge-secondary">{{ $ancestor->template_name }}</span>
                <i class="fas fa-arrow-right mx-1"></i>
            @endforeach
            <span class="badge badge-primary">{{ $tariff_template->template_name }}</span>
            <span class="text-muted ml-2">(Date Child)</span>
        </div>
    </div>
    @endif

    <!-- Copy Modal -->
    <div class="modal fade" id="copyModal" tabindex="-1" role="dialog" aria-labelledby="copyModalLabel" aria-hidden="true" data-component="modal" data-component-id="edit-tariff-template-copy-modal-1">
        <div class="modal-dialog" role="document" data-component="modal-dialog" data-component-id="edit-tariff-template-copy-dialog-1">
            <div class="modal-content" data-component="modal-content" data-component-id="edit-tariff-template-copy-content-1">
                <div class="modal-header bg-success text-white" data-component="modal-header" data-component-id="edit-tariff-template-copy-header-1">
                    <h5 class="modal-title" id="copyModalLabel" data-component="modal-title" data-component-id="edit-tariff-template-copy-title-1">
                        <i class="fas fa-copy"></i> Copy Tariff Template
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" data-component="close-button" data-component-id="edit-tariff-template-copy-close-1">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('copy-tariff-template') }}" data-component="copy-form" data-component-id="edit-tariff-template-copy-form-1">
                    @csrf
                    <input type="hidden" name="id" value="{{ $tariff_template->id }}" data-component="hidden-input" data-component-id="edit-tariff-template-copy-id-input-1" />
                    <div class="modal-body" data-component="modal-body" data-component-id="edit-tariff-template-copy-body-1">
                        <p class="mb-3" data-component="modal-text" data-component-id="edit-tariff-template-copy-text-1">How would you like to create the copy?</p>
                        
                        <div class="form-group" data-component="form-group" data-component-id="edit-tariff-template-copy-type-group-1">
                            <div class="custom-control custom-radio mb-3" data-component="radio-control" data-component-id="edit-tariff-template-copy-independent-radio-1">
                                <input type="radio" id="copyIndependent" name="is_date_child" value="0" class="custom-control-input" checked data-component="radio-input" data-component-id="edit-tariff-template-copy-independent-input-1">
                                <label class="custom-control-label" for="copyIndependent" data-component="radio-label" data-component-id="edit-tariff-template-copy-independent-label-1">
                                    <strong>Independent Copy</strong>
                                    <br><small class="text-muted">Create a standalone tariff with no relationship to the original.</small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio" data-component="radio-control" data-component-id="edit-tariff-template-copy-child-radio-1">
                                <input type="radio" id="copyDateChild" name="is_date_child" value="1" class="custom-control-input" data-component="radio-input" data-component-id="edit-tariff-template-copy-child-input-1">
                                <label class="custom-control-label" for="copyDateChild" data-component="radio-label" data-component-id="edit-tariff-template-copy-child-label-1">
                                    <strong>Date Child</strong>
                                    <br><small class="text-muted">Create a child tariff linked to this parent. Use for date-range variants of the same tariff.</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-3" style="font-size: 13px;" data-component="alert-warning" data-component-id="edit-tariff-template-copy-warning-1">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Overlap Handling:</strong> When date ranges overlap between parent and child, the <strong>lower tariff rate</strong> will be applied.
                        </div>
                        
                        @if($tariff_template->parent_id)
                        <div class="alert alert-info mt-2" style="font-size: 13px;" data-component="alert-info" data-component-id="edit-tariff-template-copy-info-1">
                            <i class="fas fa-sitemap"></i> 
                            <strong>Note:</strong> This tariff is already a date child. A new copy will extend the existing hierarchy.
                        </div>
                        @endif
                    </div>
                    <div class="modal-footer" data-component="modal-footer" data-component-id="edit-tariff-template-copy-footer-1">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" data-component="cancel-button" data-component-id="edit-tariff-template-copy-cancel-1">Cancel</button>
                        <button type="submit" class="btn btn-success" data-component="submit-button" data-component-id="edit-tariff-template-copy-submit-1">
                            <i class="fas fa-copy"></i> Create Copy
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    @if(Session::has('alert-message'))
    <div class="alert {{ Session::get('alert-class', 'alert-info') }} alert-dismissible fade show" role="alert">
        {{ Session::get('alert-message') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Validation Errors:</strong>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif
    
    <div id="tariff-template-app" 
         data-props="{{ json_encode([
             'regions' => $regions,
             'csrfToken' => csrf_token(),
             'submitUrl' => route('update-tariff-template'),
             'cancelUrl' => route('tariff-template'),
             'getEmailUrl' => route('get-email-region', ['id' => '__ID__']),
             'existingData' => $tariff_template
         ]) }}">
        <div class="text-center py-5">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>
</div>
<!-- /.container-fluid -->
@endsection

@section('script')
{!! vite(['resources/js/app.js']) !!}
<script>
function showCopyModal() {
    $('#copyModal').modal('show');
}
</script>
@endsection
