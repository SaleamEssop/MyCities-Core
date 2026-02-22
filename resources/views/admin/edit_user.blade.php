@extends('admin.layouts.main')
@section('title', 'Users')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid" data-component="admin-container" data-component-id="edit-user-container-1">

        <!-- Page Heading -->
        <h1 class="h3 mb-2 custom-text-heading" data-component="page-title" data-component-id="edit-user-title-1">Edit user</h1>

        <div class="cust-form-wrapper" data-component="form-wrapper" data-component-id="edit-user-form-wrapper-1">
            <div class="row" data-component="form-row" data-component-id="edit-user-row-1">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('edit-user') }}" data-component="user-form" data-component-id="edit-user-form-1">
                        <div class="form-group" data-component="form-group" data-component-id="edit-user-name-group-1">
                            <label data-component="form-label" data-component-id="edit-user-name-label-1"><strong>Name :</strong></label>
                            <input type="text" class="form-control" placeholder="Enter name" name="name" value="{{$user->name}}" required data-component="text-input" data-component-id="edit-user-name-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-user-contact-group-1">
                            <label data-component="form-label" data-component-id="edit-user-contact-label-1"><strong>Contact Number :</strong></label>
                            <input type="text" class="form-control" placeholder="Enter contact number" value="{{$user->contact_number}}" name="contact_number" required data-component="text-input" data-component-id="edit-user-contact-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-user-email-group-1">
                            <label data-component="form-label" data-component-id="edit-user-email-label-1"><strong>Email :</strong></label>
                            <input type="email" name="email" value="{{$user->email}}" class="form-control" id="exampleInputEmail1" required aria-describedby="emailHelp" placeholder="Enter email" data-component="email-input" data-component-id="edit-user-email-input-1">
                        </div>
                        <div class="form-group" data-component="form-group" data-component-id="edit-user-password-group-1">
                            <label data-component="form-label" data-component-id="edit-user-password-label-1"><strong>Password :</strong></label>
                            <input type="password" name="password" class="form-control" id="exampleInputPassword1" placeholder="Enter new Password" data-component="password-input" data-component-id="edit-user-password-input-1">
                        </div>
                        @csrf
                        <input type="hidden" name="user_id" value="{{$user->id}}" data-component="hidden-input" data-component-id="edit-user-id-input-1">
                        <button type="submit" class="btn btn-primary" data-component="submit-button" data-component-id="edit-user-submit-1">Submit</button>
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
