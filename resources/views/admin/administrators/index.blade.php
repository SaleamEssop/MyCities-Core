@extends('admin.layouts.main')

@section('title', 'Administrators')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Administrators</h1>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAdministratorModal">
            <i class="fas fa-plus"></i> Add Administrator
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Administrators List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="administratorsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Type</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($administrators as $admin)
                        <tr>
                            <td>{{ $admin->name }}</td>
                            <td>{{ $admin->email }}</td>
                            <td>{{ $admin->contact_number }}</td>
                            <td>
                                @if($admin->is_super_admin)
                                    <span class="badge badge-danger">Super Admin</span>
                                @else
                                    <span class="badge badge-primary">Admin</span>
                                @endif
                            </td>
                            <td>{{ $admin->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editAdministrator({{ $admin->id }})">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete({{ $admin->id }}, '{{ $admin->name }}')">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Administrator Modal -->
<div class="modal fade" id="addAdministratorModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Administrator</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="{{ route('administrators.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="contact_number" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password_confirmation" required>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_super_admin" id="is_super_admin_add" value="1">
                            <label class="form-check-label" for="is_super_admin_add">
                                Super Administrator (Full privileges)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Administrator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Administrator Modal -->
<div class="modal fade" id="editAdministratorModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Administrator</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="editAdministratorForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="contact_number" id="edit_contact_number" required>
                    </div>
                    <div class="form-group">
                        <label>Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password" id="edit_password">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" class="form-control" name="password_confirmation" id="edit_password_confirmation">
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_super_admin" id="edit_is_super_admin" value="1">
                            <label class="form-check-label" for="edit_is_super_admin">
                                Super Administrator (Full privileges)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Administrator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Removal</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove administrator privileges from <strong id="deleteAdminName"></strong>?</p>
                <p class="text-danger"><small>This will remove their administrator access but will not delete their user account.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Remove Administrator</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        $('#administratorsTable').DataTable({
            "order": [[ 3, "desc" ], [ 0, "asc" ]]
        });
    });

    function editAdministrator(id) {
        $.ajax({
            url: '/admin/administrators/' + id,
            method: 'GET',
            success: function(response) {
                if (response.status === 200) {
                    const admin = response.data;
                    $('#edit_name').val(admin.name);
                    $('#edit_email').val(admin.email);
                    $('#edit_contact_number').val(admin.contact_number);
                    $('#edit_is_super_admin').prop('checked', admin.is_super_admin == 1);
                    $('#edit_password').val('');
                    $('#edit_password_confirmation').val('');
                    $('#editAdministratorForm').attr('action', '/admin/administrators/' + id);
                    $('#editAdministratorModal').modal('show');
                }
            },
            error: function() {
                alert('Error loading administrator data');
            }
        });
    }

    function confirmDelete(id, name) {
        $('#deleteAdminName').text(name);
        $('#deleteForm').attr('action', '/admin/administrators/' + id);
        $('#deleteModal').modal('show');
    }
</script>
@endsection
























