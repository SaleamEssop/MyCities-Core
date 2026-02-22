<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class AdministratorsController extends Controller
{
    /**
     * Display the administrators management page
     */
    public function index()
    {
        // Get all administrators (users with is_admin = 1 or is_super_admin = 1)
        $administrators = User::where(function($query) {
            $query->where('is_admin', 1)
                  ->orWhere('is_super_admin', 1);
        })->orderBy('is_super_admin', 'desc')
          ->orderBy('name', 'asc')
          ->get();
        
        return Inertia::render('Admin/Administrators', [
            'administrators' => $administrators->map(fn ($a) => [
                'id'        => $a->id,
                'name'      => $a->name,
                'email'     => $a->email,
                'role'      => $a->is_super_admin ? 'Super Admin' : 'Admin',
                'is_active' => true,
            ]),
        ]);
    }

    /**
     * Store a new administrator
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact_number' => 'required|string|max:20',
            'password' => 'required|string|min:6|confirmed',
            'is_super_admin' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'password' => Hash::make($request->password),
            'is_admin' => 1,
            'is_super_admin' => $request->has('is_super_admin') ? 1 : 0,
        ]);

        Session::flash('alert-message', 'Administrator created successfully!');
        Session::flash('alert-class', 'alert-success');

        return redirect()->route('administrators.index');
    }

    /**
     * Update an existing administrator
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Check if user is an administrator
        if (!$user->is_admin && !$user->is_super_admin) {
            Session::flash('alert-message', 'User is not an administrator!');
            Session::flash('alert-class', 'alert-danger');
            return redirect()->route('administrators.index');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'contact_number' => 'required|string|max:20',
            'password' => 'nullable|string|min:6|confirmed',
            'is_super_admin' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->contact_number = $request->contact_number;
        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        
        $user->is_super_admin = $request->has('is_super_admin') ? 1 : 0;
        $user->save();

        Session::flash('alert-message', 'Administrator updated successfully!');
        Session::flash('alert-class', 'alert-success');

        return redirect()->route('administrators.index');
    }

    /**
     * Remove administrator privileges from a user
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent removing the last super admin
        $superAdminCount = User::where('is_super_admin', 1)->count();
        if ($user->is_super_admin && $superAdminCount <= 1) {
            Session::flash('alert-message', 'Cannot remove the last super administrator!');
            Session::flash('alert-class', 'alert-danger');
            return redirect()->route('administrators.index');
        }

        // Remove administrator privileges
        $user->is_admin = 0;
        $user->is_super_admin = 0;
        $user->save();

        Session::flash('alert-message', 'Administrator privileges removed successfully!');
        Session::flash('alert-class', 'alert-success');

        return redirect()->route('administrators.index');
    }

    /**
     * Get administrator data for editing (AJAX)
     */
    public function getAdministrator($id)
    {
        $user = User::findOrFail($id);

        // Check if user is an administrator
        if (!$user->is_admin && !$user->is_super_admin) {
            return response()->json([
                'status' => 404,
                'message' => 'User is not an administrator'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $user
        ]);
    }
}
























