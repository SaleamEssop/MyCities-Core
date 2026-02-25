<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EditorImageController extends Controller
{
    /**
     * Handle image upload from Editor.js Image tool.
     *
     * Expected by @editorjs/image:
     *   POST with multipart field "image"
     *   Response: { "success": 1, "file": { "url": "..." } }
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
        ]);

        $path = $request->file('image')->store('public/editor-images');
        // Use an absolute URL so EditorPhp's 'url' validator accepts it,
        // and so the image works regardless of how the app is accessed (LAN IP, localhost, etc.).
        $url  = $request->getSchemeAndHttpHost() . Storage::url($path);

        return response()->json([
            'success' => 1,
            'file'    => ['url' => $url],
        ]);
    }

    /**
     * Handle image by URL from Editor.js Image tool (fetch & store locally).
     *
     * Expected by @editorjs/image byUrl endpoint:
     *   POST with JSON body { "url": "https://..." }
     *   Response: { "success": 1, "file": { "url": "..." } }
     */
    public function uploadByUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($request->url);

            if (!$response->successful()) {
                return response()->json(['success' => 0, 'message' => 'Could not fetch image from URL.'], 422);
            }

            $ext      = pathinfo(parse_url($request->url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'public/editor-images/' . uniqid('img_', true) . '.' . strtolower($ext);
            Storage::put($filename, $response->body());

            return response()->json([
                'success' => 1,
                'file'    => ['url' => $request->getSchemeAndHttpHost() . Storage::url($filename)],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => 0, 'message' => 'Failed to download image.'], 422);
        }
    }
}
