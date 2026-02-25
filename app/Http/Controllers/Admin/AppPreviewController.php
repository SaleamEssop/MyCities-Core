<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use BumpCore\EditorPhp\EditorPhp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class AppPreviewController extends Controller
{
    public function index()
    {
        $rootPages = Page::where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->with(['activeChildren' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $pages = $rootPages->map(function ($page) {
            $children = $page->activeChildren->map(fn ($child) => [
                'id'           => $child->id,
                'title'        => $child->title,
                'icon'         => $child->icon,
                'html_content' => $this->render($child->content),
            ])->values()->all();

            return [
                'id'           => $page->id,
                'title'        => $page->title,
                'icon'         => $page->icon,
                'html_content' => $this->render($page->content),
                'children'     => $children,
            ];
        })->values()->all();

        // Users with accounts for "View as user" feature
        $users = User::where('is_admin', 0)
            ->orWhereNull('is_admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->values()->all();

        $switchedUser = Session::get('app_view_switched_user');

        return Inertia::render('Admin/AppPreview', [
            'pages'       => $pages,
            'users'       => $users,
            'switchedUser' => $switchedUser,
        ]);
    }

    /**
     * Switch session to a regular user so admin can browse the app as that user.
     * Stores the admin's original ID so we can restore it.
     */
    public function switchUser($userId)
    {
        $user = User::findOrFail($userId);

        // Remember who the original admin was
        if (!Session::has('app_view_admin_id')) {
            Session::put('app_view_admin_id', Auth::id());
            Session::put('app_view_admin_email', Auth::user()->email);
        }

        Session::put('app_view_switched_user', ['id' => $user->id, 'name' => $user->name]);

        Auth::login($user);

        return redirect('/user/dashboard');
    }

    /**
     * Restore original admin session.
     */
    public function restoreAdmin()
    {
        $adminId = Session::get('app_view_admin_id');

        if ($adminId) {
            $admin = User::find($adminId);
            if ($admin) {
                Auth::login($admin);
            }
        }

        Session::forget(['app_view_admin_id', 'app_view_admin_email', 'app_view_switched_user']);

        return redirect()->route('app-view');
    }

    private function render(?string $content): string
    {
        if (!$content) return '';
        try {
            return EditorPhp::make($content)->render();
        } catch (\Throwable $e) {
            return $content;
        }
    }
}
