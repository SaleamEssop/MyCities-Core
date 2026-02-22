@extends('admin.layouts.main')
@section('title', 'Users')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid" data-component="admin-container" data-component-id="create-user-container-1">

        <!-- Page Heading -->
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="create-user-title-1">Create new User</h1>

        <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="create-user-form-wrapper-1">
            <div class="row" data-component="form-row" data-component-id="create-user-row-1">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('add-user') }}" data-component="user-form" data-component-id="create-user-form-1">
                        <div class="form-group" data-component="form-group" data-component-id="create-user-name-group-1">
                            <label data-component="form-label" data-component-id="create-user-name-label-1"><strong>Name :</strong></label>
                            <input type="text" class="form-control" value="{{ old('name') }}" placeholder="Enter name" name="name" required data-component="text-input" data-component-id="create-user-name-input-1" data-component-description="Name">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-user-contact-group-1">
                            <label data-component="form-label" data-component-id="create-user-contact-label-1"><strong>Contact Number :</strong></label>
                            <input type="text" class="form-control" value="{{ old('contact_number') }}" placeholder="Enter contact number" name="contact_number" required data-component="text-input" data-component-id="create-user-contact-input-1" data-component-description="Contact Number">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-user-email-group-1">
                            <label data-component="form-label" data-component-id="create-user-email-label-1"><strong>Email :</strong></label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control" id="exampleInputEmail1" required aria-describedby="emailHelp" placeholder="Enter email" data-component="email-input" data-component-id="create-user-email-input-1" data-component-description="Email">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="create-user-password-group-1">
                            <label data-component="form-label" data-component-id="create-user-password-label-1"><strong>Password :</strong></label>
                            <input type="password" name="password" class="form-control" required id="exampleInputPassword1" placeholder="Password" data-component="password-input" data-component-id="create-user-password-input-1" data-component-description="Password">
                        </div>
                        @csrf
                        <button type="submit" class="btn btn-primary" data-component="submit-button" data-component-id="create-user-submit-1">Submit</button>
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
