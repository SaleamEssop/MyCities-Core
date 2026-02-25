<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>MyCities - {{ $landingTitle }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; font-family: 'Nunito', sans-serif; background: #f4f6f8; color: #333; }

        /* ── Hero ───────────────────────────────────────────────── */
        .hero {
            min-height: 100vh;
            background-image: url('{{ $backgroundUrl }}');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
        }
        .hero-box {
            position: relative;
            z-index: 1;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            padding: 48px 56px;
            max-width: 520px;
            width: calc(100% - 32px);
            text-align: center;
            color: #fff;
        }
        .hero-box h1 {
            font-size: 26px;
            font-weight: 300;
            font-style: italic;
            line-height: 1.5;
            margin-bottom: 32px;
        }
        .hero-btn {
            display: inline-block;
            background: #009BA4;
            color: #fff;
            padding: 14px 48px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            border-radius: 6px;
            letter-spacing: 0.5px;
            transition: background 0.25s;
        }
        .hero-btn:hover { background: #007d85; }

        /* ── Pages section ──────────────────────────────────────── */
        .pages-section {
            max-width: 860px;
            margin: 0 auto;
            padding: 0 16px 48px;
        }

        /* Tab strip */
        .tab-strip {
            display: flex;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            background: #fff;
            border-bottom: 2px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .tab-strip::-webkit-scrollbar { display: none; }

        .tab-btn {
            flex-shrink: 0;
            background: none;
            border: none;
            padding: 16px 22px;
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #718096;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            white-space: nowrap;
            transition: color 0.2s, border-color 0.2s;
        }
        .tab-btn:hover { color: #009BA4; }
        .tab-btn.active {
            color: #009BA4;
            border-bottom-color: #009BA4;
        }

        /* Content panels */
        .tab-panel { display: none; padding: 32px 0; }
        .tab-panel.active { display: block; }

        /* Rendered Editor.js content styles */
        .editorjs-content h1 { font-size: 2em;   font-weight: 700; margin: 0.6em 0 0.3em; color: #1a202c; }
        .editorjs-content h2 { font-size: 1.5em;  font-weight: 700; margin: 0.6em 0 0.3em; color: #1a202c; }
        .editorjs-content h3 { font-size: 1.25em; font-weight: 600; margin: 0.6em 0 0.3em; color: #2d3748; }
        .editorjs-content h4 { font-size: 1.05em; font-weight: 600; margin: 0.5em 0 0.3em; color: #2d3748; }
        .editorjs-content p  { margin-bottom: 14px; color: #4a5568; line-height: 1.75; }
        .editorjs-content ul,
        .editorjs-content ol { padding-left: 28px; margin-bottom: 14px; color: #4a5568; }
        .editorjs-content li { margin-bottom: 6px; line-height: 1.7; }
        .editorjs-content blockquote {
            border-left: 4px solid #009BA4;
            padding: 14px 20px;
            background: #f0fafa;
            margin: 20px 0;
            font-style: italic;
            color: #4a5568;
            border-radius: 0 6px 6px 0;
        }
        .editorjs-content hr {
            border: none;
            border-top: 2px solid #e2e8f0;
            margin: 28px 0;
        }
        .editorjs-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 16px 0;
        }
        .editorjs-content a  { color: #009BA4; text-decoration: underline; }
        .editorjs-content code {
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
        }
        .editorjs-content pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 1em;
            border-radius: 6px;
            overflow-x: auto;
            margin-bottom: 14px;
        }
        .editorjs-content table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .editorjs-content th,
        .editorjs-content td {
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            text-align: left;
        }
        .editorjs-content th { background: #f7fafc; font-weight: 600; }

        /* Empty state */
        .no-pages {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }
        .no-pages p { margin-top: 12px; font-size: 15px; }

        /* Footer */
        footer {
            background: #1a202c;
            color: #a0aec0;
            text-align: center;
            padding: 20px;
            font-size: 13px;
        }
        footer a { color: #009BA4; text-decoration: none; }

        @media (max-width: 480px) {
            .hero-box { padding: 36px 24px; }
            .hero-box h1 { font-size: 20px; }
            .tab-btn { padding: 14px 16px; font-size: 13px; }
        }
    </style>
</head>
<body>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-box">
            <h1>{{ $landingSubtitle }}</h1>
            <a href="{{ url('/web-app') }}" class="hero-btn">Login / Register</a>
        </div>
    </section>

    @php
        use BumpCore\EditorPhp\EditorPhp;

        // Merge home page (first) and all other active pages into one ordered list
        $allPages = collect();
        if ($homePage) {
            $allPages->push($homePage);
        }
        if ($pages && $pages->count() > 0) {
            foreach ($pages as $p) {
                $allPages->push($p);
            }
        }
    @endphp

    @if($allPages->count() > 0)
    <section class="pages-section">

        <!-- Scrollable tab strip -->
        <nav class="tab-strip" role="tablist">
            @foreach($allPages as $idx => $p)
            <button
                class="tab-btn {{ $idx === 0 ? 'active' : '' }}"
                role="tab"
                data-target="page-panel-{{ $p->id }}"
                aria-selected="{{ $idx === 0 ? 'true' : 'false' }}"
            >
                @if($p->icon)<i class="{{ $p->icon }}" style="margin-right:6px;"></i>@endif
                {{ $p->title }}
            </button>
            @endforeach
        </nav>

        <!-- Content panels -->
        @foreach($allPages as $idx => $p)
        <div
            id="page-panel-{{ $p->id }}"
            class="tab-panel {{ $idx === 0 ? 'active' : '' }}"
            role="tabpanel"
        >
            <div class="editorjs-content">
                @if($p->content)
                    {!! EditorPhp::make($p->content)->render() !!}
                @else
                    <div class="no-pages">
                        <p>This page has no content yet.</p>
                    </div>
                @endif
            </div>
        </div>
        @endforeach

    </section>
    @endif

    <footer>
        &copy; {{ date('Y') }} MyCities. All rights reserved.
    </footer>

    <script>
        // Tab switching — vanilla JS, no framework
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Deactivate all tabs and panels
                document.querySelectorAll('.tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                });
                document.querySelectorAll('.tab-panel').forEach(function(p) {
                    p.classList.remove('active');
                });

                // Activate clicked tab and its panel
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
                var target = document.getElementById(btn.dataset.target);
                if (target) target.classList.add('active');

                // Scroll tab into view (for overflow-x strip)
                btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            });
        });
    </script>

</body>
</html>
