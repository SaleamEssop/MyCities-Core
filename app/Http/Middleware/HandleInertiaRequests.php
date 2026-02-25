<?php

namespace App\Http\Middleware;

use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared to all pages.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
            ],
            'url'   => $request->url(),
            'flash' => [
                'message' => $request->session()->get('alert-message'),
                'class'   => $request->session()->get('alert-class', 'alert-info'),
            ],
            // Lightweight page tree for the user-app hamburger nav (no HTML content)
            'nav_pages' => fn () => Page::where('is_active', true)
                ->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get(['id', 'title', 'icon', 'parent_id', 'sort_order']),
        ]);
    }
}
