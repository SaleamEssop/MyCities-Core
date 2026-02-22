@extends('admin.layouts.main')
@section('title', 'Add Tariff Template')

@section('content')
<div class="container-fluid" data-component="admin-container" data-component-id="create-tariff-template-container-1">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4" data-component="page-heading" data-component-id="create-tariff-template-heading-1">
        <h1 class="h3 mb-0 text-gray-800 custom-text-heading" data-component="page-title" data-component-id="create-tariff-template-title-1">Add Tariff Template</h1>
    </div>
    <p class="mb-4" data-component="page-description" data-component-id="create-tariff-template-description-1">Create a new billing template for a region.</p>
    
    @if(Session::has('alert-message'))
    <div class="alert {{ Session::get('alert-class', 'alert-info') }} alert-dismissible fade show" role="alert" data-component="alert-message" data-component-id="create-tariff-template-alert-1">
        {{ Session::get('alert-message') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" data-component="close-button" data-component-id="create-tariff-template-alert-close-1">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert" data-component="error-alert" data-component-id="create-tariff-template-error-alert-1">
        <strong>Validation Errors:</strong>
        <ul class="mb-0" data-component="error-list" data-component-id="create-tariff-template-error-list-1">
            @foreach($errors->all() as $error)
                <li data-component="error-item" data-component-id="create-tariff-template-error-item-{{ $loop->index + 1 }}">{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" data-component="close-button" data-component-id="create-tariff-template-error-close-1">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif
    
    <div id="tariff-template-app" 
         data-props="{{ json_encode([
             'regions' => $regions,
             'csrfToken' => csrf_token(),
             'submitUrl' => route('tariff-template-store'),
             'cancelUrl' => route('tariff-template'),
             'getEmailUrl' => route('get-email-region', ['id' => '__ID__'])
         ]) }}"
         data-component="vue-app-container" 
         data-component-id="create-tariff-template-vue-app-1">
        <div class="text-center py-5" data-component="loading-spinner" data-component-id="create-tariff-template-spinner-1">
            <div class="spinner-border" role="status" data-component="spinner" data-component-id="create-tariff-template-spinner-inner-1">
                <span class="sr-only" data-component="sr-only-text" data-component-id="create-tariff-template-sr-only-1">Loading...</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
{!! vite(['resources/js/app.js']) !!}
@endsection
