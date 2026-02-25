@extends('admin.layouts.main')
@section('title', 'Meter Readings')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">

        <!-- Page Heading -->
        <div class="cust-page-head">
            <h1 class="h3 mb-2 custom-text-heading">Ads</h1>
            <button type="button" class="btn btn-primary btn-circle" data-toggle="modal" data-target="#catModal">
                <i class="fas fa-plus-square"></i>
            </button>
        </div>

        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit</h6>
        </div>

        <div class="row">
            <div class="col-md-6">
                <form method="POST" action="{{ route('edit-ad') }}" enctype="multipart/form-data">
                    <img src="{{ $ad->image }}" width="200" height="200" />
                    <div class="form-group">
                        <label><strong>Ad Image :</strong></label>
                        <input type="file" name="ad_image" />
                    </div>
                    <div class="form-group">
                        <label><strong>Category :</strong></label>
                        <select class="form-control" name="ads_category_id">
                            <option disabled>--Select Category--</option>
                            @foreach($categories as $category)
                                <option {{ ($category->id == $ad->ads_category_id) ? 'selected' : '' }} value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label><strong>Name :</strong></label>
                        <input placeholder="Enter new ad name" type="text" value="{{ $ad->name }}" class="form-control" name="ad_name" required />
                    </div>
                    <div class="form-group">
                        <label><strong>Url :</strong></label>
                        <input placeholder="Enter new ad url" type="text" value="{{ $ad->url }}" class="form-control" name="ad_url" required />
                    </div>
                    <div class="form-group">
                        <label><strong>Price :</strong></label>
                        <input placeholder="Enter new ad price" type="number" value="{{ $ad->price }}" class="form-control" name="ad_price" required />
                    </div>
                    <div class="form-group">
                        <label><strong>Priority :</strong></label>
                        <input placeholder="Enter new ad priority" type="number" value="{{ $ad->priority }}" class="form-control" name="ad_priority" />
                    </div>
                    <div class="form-group">
                        <label><strong>Description :</strong></label>
                        <textarea id="description-editor" name="description-editor" style="display:none;">{{ $ad->description }}</textarea>
                        <div id="ads-editorjs" style="border:1px solid #d1d3e2;border-radius:0.35rem;min-height:250px;background:#fff;padding:4px 0;"></div>
                    </div>
                    @csrf
                    <input type="hidden" name="ad_id" value="{{ $ad->id }}" />
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </form>
            </div>
        </div>
    </div>
    <!-- /.container-fluid -->
@endsection

@section('page-level-styles')
<style>
    #ads-editorjs { border:1px solid #d1d3e2; border-radius:0.35rem; min-height:250px; background:#fff; padding:4px 0; }
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
        const adsTextarea   = document.getElementById('description-editor');
        const adsRawContent = adsTextarea.value.trim();

        let adsInitialData = { blocks: [] };
        if (adsRawContent) {
            try {
                adsInitialData = JSON.parse(adsRawContent);
            } catch (e) {
                adsInitialData = { blocks: [{ type: 'paragraph', data: { text: adsRawContent } }] };
            }
        }

        const adsCsrfToken = document.querySelector('meta[name="csrf-token"]').content;

        const adsEditor = new EditorJS({
            holder: 'ads-editorjs',
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
                        additionalRequestHeaders: {
                            'X-CSRF-TOKEN': adsCsrfToken,
                        },
                        captionPlaceholder: 'Image caption (optional)',
                    },
                },
                underline: Underline,
                marker:    Marker,
                inlineCode: InlineCode,
            },
            data: adsInitialData,
            placeholder: 'Write your ad description...',
            onChange: async () => {
                const saved = await adsEditor.save();
                adsTextarea.value = JSON.stringify(saved);
            },
            onReady: () => {
                new Undo({ editor: adsEditor });
            },
        });

        // Safety-net sync on form submit
        document.querySelector('form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const saved = await adsEditor.save();
            adsTextarea.value = JSON.stringify(saved);
            e.target.submit();
        });
    </script>
@endsection
