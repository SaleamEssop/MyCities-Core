@extends('admin.layouts.main')
@section('title', 'Terms')

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">

        <!-- Page Heading -->
        <h1 class="h3 mb-2 custom-text-heading">Terms & Conditions</h1>

        <div class="cust-form-wrapper">
            <div class="row">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('updateTC') }}" id="tcForm">
                        <div class="form-group">
                            <textarea id="tc" name="tc" style="display:none;">{{ $settings->terms_condition ?? '' }}</textarea>
                            <div id="tc-editorjs" style="border:1px solid #d1d3e2;border-radius:0.35rem;min-height:400px;background:#fff;padding:4px 0;"></div>
                        </div>
                        <input type="hidden" name="setting_id" value="{{ $settings->id ?? '' }}">
                        @csrf
                        <button type="submit" class="btn btn-warning mt-3">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /.container-fluid -->
@endsection

@section('page-level-styles')
<style>
    #tc-editorjs { border:1px solid #d1d3e2; border-radius:0.35rem; min-height:400px; background:#fff; padding:4px 0; }
    .ce-block__content, .ce-toolbar__content { max-width: 100% !important; }
    .codex-editor { font-family: inherit; }
</style>
@endsection

@section('page-level-scripts')
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
        const tcTextarea   = document.getElementById('tc');
        const tcRawContent = tcTextarea.value.trim();
        const tcCsrf       = document.querySelector('meta[name="csrf-token"]').content;

        let tcInitialData = { blocks: [] };
        if (tcRawContent) {
            try {
                tcInitialData = JSON.parse(tcRawContent);
            } catch (e) {
                // Legacy HTML content: wrap as a single paragraph block
                tcInitialData = { blocks: [{ type: 'paragraph', data: { text: tcRawContent } }] };
            }
        }

        const tcEditor = new EditorJS({
            holder: 'tc-editorjs',
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
                        additionalRequestHeaders: { 'X-CSRF-TOKEN': tcCsrf },
                        captionPlaceholder: 'Image caption (optional)',
                    },
                },
                underline:  Underline,
                marker:     Marker,
                inlineCode: InlineCode,
            },
            data: tcInitialData,
            placeholder: 'Write your Terms & Conditions...',
            onChange: async () => {
                const saved = await tcEditor.save();
                tcTextarea.value = JSON.stringify(saved);
            },
            onReady: () => { new Undo({ editor: tcEditor }); },
        });

        document.getElementById('tcForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const saved = await tcEditor.save();
            tcTextarea.value = JSON.stringify(saved);
            e.target.submit();
        });
    </script>
@endsection

