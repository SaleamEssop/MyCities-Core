<?php

if (! function_exists('validateData')) {

    function validateData($required, $data) {
        foreach ($required as $field) {
            if(empty($data[$field])) {
                return ['status' => false, 'error' => "Required '$field' field is missing."];
            }
        }
        return ['status' => true, 'error' => ""];
    }
}

/**
 * Output Vite script/link tags for the given entry points (Laravel 8 compatible).
 * In dev: when "npm run dev" is running, public/hot exists and we load from Vite dev server.
 * In prod: read public/build/manifest.json and output built asset URLs.
 *
 * @param  array<int, string>  $entries  e.g. ['resources/js/inertia-app.js']
 * @return \Illuminate\Support\HtmlString
 */
if (! function_exists('vite')) {
    function vite(array $entries)
    {
        $hotFile = public_path('hot');
        if (file_exists($hotFile)) {
            $url = rtrim(file_get_contents($hotFile));
            $tags = [];
            $tags[] = '<script type="module" src="' . e($url . '/@vite/client') . '"></script>';
            foreach ($entries as $entry) {
                $tags[] = '<script type="module" src="' . e($url . '/' . $entry) . '"></script>';
            }
            return new \Illuminate\Support\HtmlString(implode("\n", $tags));
        }

        $manifestPath = public_path('build/manifest.json');
        if (! file_exists($manifestPath)) {
            return new \Illuminate\Support\HtmlString('<!-- Vite manifest not found. Run npm run build. -->');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $tags = [];
        foreach ($entries as $entry) {
            $chunk = $manifest[$entry] ?? null;
            if (! $chunk) {
                continue;
            }
            foreach ($chunk['css'] ?? [] as $css) {
                $tags[] = '<link rel="stylesheet" href="' . e(asset('build/' . $css)) . '" />';
            }
            $tags[] = '<script type="module" src="' . e(asset('build/' . $chunk['file'])) . '"></script>';
        }
        return new \Illuminate\Support\HtmlString(implode("\n", $tags));
    }
}
