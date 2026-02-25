@extends('admin.layouts.main')
@section('title', 'Edit Page')

@section('content')
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit mr-2"></i>Edit Page: {{ $page->title }}
        </h1>
        <a href="{{ route('pages-list') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Pages
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Main Content Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Page Content</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('pages-update') }}" id="pageForm">
                        @csrf
                        <input type="hidden" name="page_id" value="{{ $page->id }}">
                        
                        <div class="form-group">
                            <label for="title"><strong>Page Title <span class="text-danger">*</span></strong></label>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="title" 
                                   name="title" 
                                   value="{{ $page->title }}"
                                   placeholder="Enter page title"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="slug"><strong>URL Slug</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">/</span>
                                </div>
                                <input type="text" 
                                       class="form-control" 
                                       id="slug" 
                                       name="slug" 
                                       value="{{ $page->slug }}"
                                       placeholder="url-slug">
                            </div>
                            <small class="text-muted">Current URL: <code>{{ $page->url }}</code></small>
                        </div>

                        <div class="form-group">
                            <label for="page_content"><strong>Page Content</strong></label>

                            {{-- Editor.js usage guide --}}
                            <div class="alert alert-info py-2 px-3 mb-2 small">
                                <strong><i class="fas fa-keyboard mr-1"></i>Block Editor</strong> —
                                Click inside the editor below, then:
                                <span class="badge badge-light border mx-1"><kbd>/</kbd></span> to choose a block type &nbsp;|&nbsp;
                                <span class="badge badge-light border mx-1"><kbd>Enter</kbd></span> for a new paragraph &nbsp;|&nbsp;
                                <span class="badge badge-light border mx-1">+ button</span> on the left margin to add any block
                            </div>

                            <textarea id="page_content" name="page_content" style="display:none;">{!! $page->content !!}</textarea>
                            <div id="editorjs" style="border:1px solid #4e73df;border-radius:0.35rem;min-height:400px;background:#fff;padding:8px 0;"></div>
                            <div id="editorjs-status" class="text-muted small mt-1"><i class="fas fa-spinner fa-spin mr-1"></i>Loading editor...</div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="meta_title"><strong>SEO Title</strong></label>
                                    <input type="text" class="form-control" id="meta_title" name="meta_title" value="{{ $page->meta_title }}" placeholder="SEO title (optional)">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="icon"><strong>Icon Class</strong></label>
                                    <input type="text" class="form-control" id="icon" name="icon" value="{{ $page->icon }}" placeholder="e.g., fas fa-home">
                                    <small class="text-muted">FontAwesome icon class</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="meta_description"><strong>SEO Description</strong></label>
                            <textarea class="form-control" id="meta_description" name="meta_description" rows="2" placeholder="SEO description (optional)">{{ $page->meta_description }}</textarea>
                        </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Settings Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Page Settings</h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label><strong>Page Type <span class="text-danger">*</span></strong></label>
                        <div class="mt-2">
                            <div class="custom-control custom-radio mb-2">
                                <input type="radio" id="type_single" name="page_type" value="single" class="custom-control-input" {{ $page->page_type == 'single' ? 'checked' : '' }}>
                                <label class="custom-control-label" for="type_single">
                                    <i class="fas fa-columns text-primary mr-1"></i>
                                    <strong>Single Page (Header Tab)</strong>
                                    <br><small class="text-muted">Shows as a tab in the app header</small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio mb-2">
                                <input type="radio" id="type_parent" name="page_type" value="parent" class="custom-control-input" {{ $page->page_type == 'parent' ? 'checked' : '' }}>
                                <label class="custom-control-label" for="type_parent">
                                    <i class="fas fa-bars text-success mr-1"></i>
                                    <strong>Parent Page (Menu Group)</strong>
                                    <br><small class="text-muted">Expandable menu with child pages</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    @if($page->page_type != 'parent' || !$page->hasChildren())
                    <div class="form-group" id="parentSelectGroup" style="{{ $page->page_type == 'parent' ? 'display:none;' : '' }}">
                        <label for="parent_id"><strong>Parent Page</strong></label>
                        <select class="form-control" id="parent_id" name="parent_id">
                            <option value="">-- No Parent (Root Level) --</option>
                            @foreach($parentPages as $parent)
                                <option value="{{ $parent->id }}" {{ $page->parent_id == $parent->id ? 'selected' : '' }}>{{ $parent->title }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Add as child to a menu group</small>
                    </div>
                    @endif

                    <div class="form-group">
                        <label for="sort_order"><strong>Sort Order</strong></label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ $page->sort_order }}" min="0">
                        <small class="text-muted">Lower numbers appear first</small>
                    </div>

                    <hr>

                    <div class="form-group">
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" {{ $page->is_active ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">
                                <strong>Active</strong>
                                <br><small class="text-muted">Page is visible</small>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="show_in_navigation" name="show_in_navigation" {{ $page->show_in_navigation ? 'checked' : '' }}>
                            <label class="custom-control-label" for="show_in_navigation">
                                <strong>Show in Navigation</strong>
                                <br><small class="text-muted">Display in app menu</small>
                            </label>
                        </div>
                    </div>

                    <hr>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-save mr-2"></i>Update Page
                    </button>
                    </form>

                    <a href="{{ route('pages-preview', $page->id) }}" class="btn btn-info btn-block mt-2" target="_blank">
                        <i class="fas fa-eye mr-2"></i>Preview Page
                    </a>
                </div>
            </div>

            <!-- Page Info Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-secondary">Page Info</h6>
                </div>
                <div class="card-body small">
                    <p><strong>ID:</strong> {{ $page->id }}</p>
                    <p><strong>Created:</strong> {{ $page->created_at->format('M d, Y H:i') }}</p>
                    <p><strong>Updated:</strong> {{ $page->updated_at->format('M d, Y H:i') }}</p>
                    @if($page->hasChildren())
                    <p><strong>Child Pages:</strong> {{ $page->children->count() }}</p>
                    @endif
                    @if($page->parent)
                    <p><strong>Parent:</strong> {{ $page->parent->title }}</p>
                    @endif
                </div>
            </div>

            @if($page->page_type == 'parent' && $page->hasChildren())
            <!-- Children List -->
            <div class="card shadow mb-4 border-left-success">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-sitemap mr-2"></i>Child Pages ({{ $page->children->count() }})
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @foreach($page->children as $child)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span>
                                @if($child->icon)<i class="{{ $child->icon }} mr-2"></i>@endif
                                {{ $child->title }}
                            </span>
                            <a href="{{ route('pages-edit', $child->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('pages-create') }}?parent={{ $page->id }}" class="btn btn-success btn-sm btn-block mt-3">
                        <i class="fas fa-plus mr-1"></i>Add Child Page
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('page-level-styles')
<style>
    .ce-toolbar__actions { opacity: 1 !important; }
    .ce-block__content, .ce-toolbar__content { max-width: 100% !important; }
    .codex-editor { font-family: inherit; }
</style>
@endsection

@section('script')
<!-- Editor.js core + tools from jsDelivr CDN -->
<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/image@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/underline@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/marker@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/inline-code@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/table@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/editorjs-undo@latest"></script>
<script>
    const textarea   = document.getElementById('page_content');
    const rawContent = textarea.value.trim();

    let initialData = { blocks: [] };
    if (rawContent) {
        try {
            initialData = JSON.parse(rawContent);
        } catch (e) {
            // Legacy HTML content: wrap as a single paragraph block
            initialData = { blocks: [{ type: 'paragraph', data: { text: rawContent } }] };
        }
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const editor = new EditorJS({
        holder: 'editorjs',
        autofocus: true,
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
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    captionPlaceholder: 'Image caption (optional)',
                },
            },
            underline: Underline,
            marker:    Marker,
            inlineCode: InlineCode,
            table:     { class: Table, inlineToolbar: true },
        },
        data: initialData,
        placeholder: 'Click here or press / to choose a block type...',
        onChange: async () => {
            const savedData = await editor.save();
            textarea.value  = JSON.stringify(savedData);
        },
        onReady: () => {
            document.getElementById('editorjs-status').textContent = '✓ Editor ready — click inside to begin';
            document.getElementById('editorjs-status').className = 'text-success small mt-1';
            try { new Undo({ editor }); } catch(e) {}
        },
    });

    // Sync on form submit as a safety net
    document.getElementById('pageForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const savedData = await editor.save();
        textarea.value  = JSON.stringify(savedData);
        e.target.submit();
    });
</script>

<script>
    // Page type toggle
    $('input[name="page_type"]').on('change', function() {
        if ($(this).val() === 'single') {
            $('#parentSelectGroup').slideDown();
        } else {
            $('#parentSelectGroup').slideUp();
            $('#parent_id').val('');
        }
    });
</script>
@endsection
