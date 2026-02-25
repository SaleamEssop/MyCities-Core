@extends('admin.layouts.main')
@section('title', 'Meter Readings')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">

        <!-- Page Heading -->
        <div class="cust-page-head">
            <h1 class="h3 mb-2 custom-text-heading">Ads</h1>
            <button type="button" class="btn btn-warning btn-circle" data-toggle="modal" data-target="#catModal">
                <i class="fas fa-plus-square"></i>
            </button>
        <a class="btn btn-primary ml-2" href="{{ route('ads.landing-settings') }}">
            Edit Landing Page
        </a>
        </div>

        <!-- DataTales Example -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">List of added Ads</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="acc-dataTable" width="100%" cellspacing="0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Ad Image</th>
                            <th>Category</th>
                            <th>Ad Name</th>
                            <th>Url</th>
                            <th>Price</th>
                            <th>Priority</th>
                            <th>Created Date</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th>#</th>
                            <th>Ad Image</th>
                            <th>Category</th>
                            <th>Ad Name</th>
                            <th>Url</th>
                            <th>Price</th>
                            <th>Priority</th>
                            <th>Created Date</th>
                            <th>Action</th>
                        </tr>
                        </tfoot>
                        <tbody>
                        @foreach($ads as $ad)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><img src="{{ $ad->image }}" width="100" height="100"></td>
                                <td>{{ $ad->category->name ?? ' - ' }}</td>
                                <td>{{ $ad->name }}</td>
                                <td>{{ $ad->url }}</td>
                                <td>{{ $ad->price }}</td>
                                <td>{{ $ad->priority }}</td>
                                <td>{{ $ad->created_at }}</td>
                                <td>
                                    <a href="{{ url('admin/ads/edit/'.$ad->id) }}" class="btn btn-warning btn-circle">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="{{ url('admin/ads/delete/'.$ad->id) }}" onclick="return confirm('Are you sure you want to delete this ad?')" class="btn btn-danger btn-circle">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- /.container-fluid -->
    <!-- Modal -->
    <div class="modal fade" id="catModal" tabindex="-1" role="dialog" aria-labelledby="costModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="costModalLabel">Create new Ad</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('add-ads') }}" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="form-group">
                            <input type="file" name="ad_image" />
                        </div>
                        <div class="form-group">
                            <label><strong>Category :</strong></label>
                            <select class="form-control" name="ads_category_id">
                                <option selected disabled>--Select Category--</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label><strong>Name :</strong></label>
                            <input placeholder="Enter new ad name" type="text" class="form-control" name="ad_name" required />
                        </div>
                        <div class="form-group">
                            <label><strong>Url :</strong></label>
                            <input placeholder="Enter new ad url" type="text" class="form-control" name="ad_url" required />
                        </div>
                        <div class="form-group">
                            <label><strong>Price :</strong></label>
                            <input placeholder="Enter new ad price" type="number" class="form-control" name="ad_price" required />
                        </div>
                        <div class="form-group">
                            <label><strong>Priority :</strong></label>
                            <input placeholder="Enter new ad priority" type="number" class="form-control" name="ad_priority" />
                        </div>
                        <div class="form-group">
                            <label><strong>Description :</strong></label>
                            <textarea id="ads-create-description" name="description-editor" style="display:none;"></textarea>
                            <div id="ads-create-editorjs" style="border:1px solid #d1d3e2;border-radius:0.35rem;min-height:200px;background:#fff;padding:4px 0;"></div>
                        </div>
                        @csrf
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('page-level-styles')
<style>
    #ads-create-editorjs { border:1px solid #d1d3e2; border-radius:0.35rem; min-height:200px; background:#fff; padding:4px 0; }
    .ce-block__content, .ce-toolbar__content { max-width: 100% !important; }
    .codex-editor { font-family: inherit; }
</style>
@endsection

@section('page-level-scripts')
    <!-- Editor.js core + tools -->
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/image@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/underline@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/marker@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/inline-code@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/editorjs-undo@latest"></script>

    <script>
        $(document).ready(function() {
            $('#acc-dataTable').dataTable();
        });

        const adsCsrf = document.querySelector('meta[name="csrf-token"]').content;
        let adsCreateEditor = null;

        // Initialise Editor.js only when the modal is fully visible
        $('#catModal').on('shown.bs.modal', function() {
            if (adsCreateEditor) return; // already initialised

            adsCreateEditor = new EditorJS({
                holder: 'ads-create-editorjs',
                tools: {
                    header:    { class: Header,    inlineToolbar: true, config: { levels: [1,2,3,4], defaultLevel: 2 } },
                    list:      { class: List,      inlineToolbar: true },
                    quote:     { class: Quote,     inlineToolbar: true },
                    delimiter: Delimiter,
                    image: {
                        class: ImageTool,
                        config: {
                            endpoints: {
                                byFile: '{{ route("editor.image.upload") }}',
                                byUrl:  '{{ route("editor.image.by-url") }}',
                            },
                            additionalRequestHeaders: { 'X-CSRF-TOKEN': adsCsrf },
                            captionPlaceholder: 'Image caption (optional)',
                        },
                    },
                    underline:  Underline,
                    marker:     Marker,
                    inlineCode: InlineCode,
                },
                data: { blocks: [] },
                placeholder: 'Write your ad description...',
                onChange: async () => {
                    const saved = await adsCreateEditor.save();
                    document.getElementById('ads-create-description').value = JSON.stringify(saved);
                },
            });
        });

        // Safety-net sync before the modal form submits
        $('#catModal form').on('submit', async function(e) {
            if (!adsCreateEditor) return;
            e.preventDefault();
            const saved = await adsCreateEditor.save();
            document.getElementById('ads-create-description').value = JSON.stringify(saved);
            this.submit();
        });
    </script>
@endsection
