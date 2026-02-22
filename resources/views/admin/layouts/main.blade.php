<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>MyCities-Core - @yield('title', 'Admin')</title>

    <!-- Custom fonts for this template-->
    <link href="{{ url('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="{{ url('/css/main.css')  }}" rel="stylesheet">
    <!-- NEW: Professional Theme Override -->
    <link href="{{ url('/css/custom-admin.css')  }}" rel="stylesheet">

    <link href="{{ url('vendor/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
    @yield('page-level-styles')
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ url('admin/') }}">
                <img src="{{ url('img/my_cities.png') }}" alt="logo-img" width="100%" />
            </a>
            <div class="text-center px-2 py-1" style="font-size:11px;color:rgba(255,255,255,0.7);">MyCities-Core</div>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item active">
                <a class="nav-link" href="{{ url('admin/') }}">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Interface
            </div>

            <!-- Nav Item - User Accounts - Setup -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('user-accounts.setup') }}">
                    <i class="fas fa-fw fa-user-plus"></i>
                    <span>User Accounts - Setup</span>
                </a>
            </li>

            <!-- Nav Item - User Accounts - Manager -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('user-accounts.manager') }}">
                    <i class="fas fa-fw fa-users-cog"></i>
                    <span>User Accounts - Manager</span>
                </a>
            </li>

            <!-- Nav Item - Sites -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseSites"
                    aria-expanded="true" aria-controls="collapseSites">
                    <i class="fas fa-fw fa-location-arrow"></i>
                    <span>Sites</span>
                </a>
                <div id="collapseSites" class="collapse" aria-labelledby="headingUtilities"
                    data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded cust-sidebar-bg">
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('show-sites') }}">List</a>
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('create-site-form') }}">Add</a>
                    </div>
                </div>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Configuration
            </div>

            <!-- Nav Item - Regions -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseRegions"
                    aria-expanded="true" aria-controls="collapseRegions">
                    <i class="fas fa-fw fa-location-arrow"></i>
                    <span>Regions</span>
                </a>
                <div id="collapseRegions" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded cust-sidebar-bg">
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('regions-list') }}">List</a>
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('add-region-form') }}">Add</a>
                    </div>
                </div>
            </li>

            <!-- Nav Item - Tariff Templates -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseTariffTemplate"
                    aria-expanded="true" aria-controls="collapseTariffTemplate">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i>
                    <span>Tariff Templates</span>
                </a>
                <div id="collapseTariffTemplate" class="collapse" aria-labelledby="headingTwo"
                    data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded cust-sidebar-bg">
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('tariff-template') }}">List</a>
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('tariff-template-create') }}">Add</a>
                    </div>
                </div>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Content
            </div>

            <!-- Nav Item - Page Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages"
                    aria-expanded="true" aria-controls="collapsePages">
                    <i class="fas fa-fw fa-file-alt"></i>
                    <span>Pages</span>
                </a>
                <div id="collapsePages" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded cust-sidebar-bg">
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('pages-list') }}">All Pages</a>
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('pages-create') }}">Add New Page</a>
                    </div>
                </div>
            </li>

            <!-- Nav Item - Ads/Content -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseAds"
                    aria-expanded="true" aria-controls="collapseAds">
                    <i class="fas fa-fw fa-ad"></i>
                    <span>Advertising</span>
                </a>
                <div id="collapseAds" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded cust-sidebar-bg">
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('ads-list') }}">Manage Ads</a>
                        <a class="collapse-item cust-sidebar-sub" href="{{ route('ads-categories') }}">Categories</a>
                    </div>
                </div>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Nav Item - Billing Calculator -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('billing-calculator') }}">
                    <i class="fas fa-fw fa-calculator"></i>
                    <span>Billing Calculator</span></a>
            </li>

            <!-- Nav Item - Calculator (Inertia) -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('calculator') }}">
                    <i class="fas fa-fw fa-calculator"></i>
                    <span>Calculator</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('alarms') }}">
                    <i class="fas fa-fw fa-clock"></i>
                    <span>Alarms</span></a>
            </li>

            <!-- Nav Item - Administrators -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('administrators.index') }}">
                    <i class="fas fa-fw fa-user-shield"></i>
                    <span>Administrators</span></a>
            </li>

            <!-- Nav Item - Settings -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('settings.index') }}">
                    <span>Settings</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.logout') }}">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    <span>Logout</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle" data-component="sidebar-toggle"
                    data-component-id="sidebar-toggle-1"></button>
            </div>

        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                data-component="user-dropdown-toggle" data-component-id="user-dropdown-toggle-1">
                                <span
                                    class="mr-2 d-none d-lg-inline text-gray-600 small">{{ isset(auth()->user()->name) ? auth()->user()->name : 'Admin' }}</span>
                                <img class="img-profile rounded-circle" src="{{ url('img/undraw_profile.svg') }}">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="{{ route('admin.logout') }}" data-toggle="modal"
                                    data-target="#logoutModal" data-component="logout-link"
                                    data-component-id="user-dropdown-logout-link-1">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    @if(\Illuminate\Support\Facades\Session::has('alert-message'))
                        <p class="alert {{ \Illuminate\Support\Facades\Session::get('alert-class', 'alert-info') }}">
                            {{ \Illuminate\Support\Facades\Session::get('alert-message') }}
                        </p>
                    @endif
                    @yield('content')
                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; LightsAndWater 2021</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true" data-component="modal" data-component-id="logout-modal-1">
        <div class="modal-dialog" role="document" data-component="modal-dialog"
            data-component-id="logout-modal-dialog-1">
            <div class="modal-content" data-component="modal-content" data-component-id="logout-modal-content-1">
                <div class="modal-header" data-component="modal-header" data-component-id="logout-modal-header-1">
                    <h5 class="modal-title" id="exampleModalLabel" data-component="modal-title"
                        data-component-id="logout-modal-title-1">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close"
                        data-component="close-button" data-component-id="logout-modal-close-1">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body" data-component="modal-body" data-component-id="logout-modal-body-1">Select
                    "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer" data-component="modal-footer" data-component-id="logout-modal-footer-1">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal" data-component="cancel-button"
                        data-component-id="logout-modal-cancel-1">Cancel</button>
                    <a class="btn btn-primary" href="{{ route('admin.logout') }}" data-component="logout-button"
                        data-component-id="logout-modal-logout-button-1">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="{{ url('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ url('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <!-- Core plugin JavaScript-->
    <script src="{{ url('vendor/jquery-easing/jquery.easing.min.js') }}"></script>

    <!-- Custom scripts for all pages-->
    <script src="{{ url('js/sb-admin-2.min.js') }}"></script>

    <!-- Page level plugins -->
    <script src="{{ url('vendor/chart.js/Chart.min.js') }}"></script>

    <!-- Page level plugins -->
    <script src="{{ url('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ url('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

    <!-- Page level custom scripts -->
    <script src="{{ url('js/demo/datatables-demo.js') }}"></script>
    <!-- Component Debug Information System -->
    <script src="{{ url('js/component-debug.js') }}"></script>
    <script>
        $(".alert").delay(4000).slideUp(200, function () {
            $(this).alert('close');
        });
    </script>
    @yield('script')
</body>

</html>