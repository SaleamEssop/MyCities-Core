<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /*public function __construct() {

    }*/

    public function register(Request $request) {

        $postData = $request->post();
        if(empty($postData['action'])) // action = insert, update
            return response()->json(['status' => false, 'code' => 400, 'msg' => 'Oops, action field is required!']);

        $action = $postData['action'];
        $requiredFields = ['full_name', 'email', 'phone_number'];
        if($action == 'insert')
            $requiredFields[] = 'password';
        elseif($action == 'update')
            $requiredFields[] = 'id';
        else
            return response()->json(['status' => false, 'code' => 400, 'msg' => 'Oops, wrong action provided!']);

        $validated = validateData($requiredFields, $postData);
        if(!$validated['status'])
            return response()->json(['status' => false, 'code' => 400, 'msg' => $validated['error']]);

        // Check if user with this email already exists
        if($postData['action'] == 'insert')
            $alreadyExists = User::where('email', $postData['email'])->get();
        elseif($postData['action'] == 'update')
            $alreadyExists = User::where('email', $postData['email'])->where('id','<>', $postData['id'])->get();

        if(count($alreadyExists) !== 0)
            return response()->json(['status' => false, 'code' => 400, 'msg' => 'Oops, user with this email already exists!']);

        $userArr = array(
            'name' => $postData['full_name'],
            'email' => $postData['email'],
            'contact_number' => $postData['phone_number']
        );

        if($action == 'insert') {
            $userArr['password'] = bcrypt($postData['password']);
            $response = User::create($userArr);
        }
        elseif ($action == 'update')
            $response = User::where('id', $postData['id'])->update($userArr);

        if(!$response)
            return response()->json(['status' => false, 'code' => 400, 'msg' => 'Oops, something went wrong!']);

        $responseArr = array(
            'status' => true,
            'code' => 200,
            'msg' => 'Action successfully!'
        );
        if($action == 'update')
            $responseArr['data'] = User::find($postData['id']);

        return response()->json($responseArr);
    }

    /* *
     * * * User login function
     * */

    public function login(Request $request) {

        $postData = $request->post();
        $requiredFields = ['email', 'password'];
        $validated = validateData($requiredFields, $postData);
        if(!$validated['status'])
            return response()->json(['status' => false, 'code' => 400, 'msg' => $validated['error']]);

        $user = User::where(['email' => $postData['email']])->get();
        if(count($user) !== 1)
            return response()->json(['status' => false, 'code' => 400, 'msg' => 'Oops, username or password is wrong!']);

        // Now check the password hash
        $dbPasswordHash = $user[0]->password;
        $userPassword = $postData['password'];
        if(!Hash::check($userPassword, $dbPasswordHash))
            return response()->json(['status' => false, 'code' => 400, 'msg' => 'Oops, username or password is wrong!']);

        $token = $user[0]->createToken('lightsAndWaterAPP')->plainTextToken;
        $responseData = array(
            'id' => $user[0]->id,
            'name' => $user[0]->name,
            'email' => $user[0]->email,
            'contact_number' => $user[0]->contact_number,
            'is_admin' => $user[0]->is_admin ?? 0,
            'is_super_admin' => $user[0]->is_super_admin ?? 0,
            'role' => ($user[0]->is_super_admin ?? 0) ? 'admin' : (($user[0]->is_admin ?? 0) ? 'admin' : 'user')
        );
        return response()->json(['status' => true, 'code' => 200, 'msg' => 'User logged in successfully!', 'token' => $token, 'data' => $responseData]);
    }

    public function logout(Request $request) {

        $postData = $request->post();
        if(empty($postData['user_id']))
            return response()->json(['status' => false, 'code' => 400, 'msg' => "user_id is required!"]);

        $request->user()->currentAccessToken()->delete();
        return response()->json(['status' => true, 'code' => 200, 'msg' => 'User logged out successfully!']);
    }

    /**
     * Admin impersonation - Get user by email for admin to act as that user
     */
    public function adminImpersonate(Request $request) {
        $postData = $request->post();
        $requiredFields = ['email', 'admin_token'];
        $validated = validateData($requiredFields, $postData);
        if(!$validated['status'])
            return response()->json(['status' => false, 'code' => 400, 'msg' => $validated['error']]);

        try {
            // Verify admin token (the admin's token from their login)
            $adminToken = \Laravel\Sanctum\PersonalAccessToken::findToken($postData['admin_token']);
            
            if(!$adminToken) {
                \Log::error('Admin impersonation failed: Token not found', [
                    'token_preview' => substr($postData['admin_token'], 0, 20) . '...',
                    'email' => $postData['email']
                ]);
                return response()->json([
                    'status' => false, 
                    'code' => 401, 
                    'msg' => 'Invalid admin token. Please log in again as admin.'
                ]);
            }

            $admin = $adminToken->tokenable;
            
            if(!$admin) {
                \Log::error('Admin impersonation failed: Token has no tokenable user');
                return response()->json([
                    'status' => false, 
                    'code' => 401, 
                    'msg' => 'Invalid admin token. Token not associated with a user.'
                ]);
            }
            
            // Check if user is admin
            $isAdmin = ($admin->is_admin ?? 0) == 1;
            $isSuperAdmin = ($admin->is_super_admin ?? 0) == 1;
            
            if(!$isAdmin && !$isSuperAdmin) {
                \Log::warning('Admin impersonation denied: User is not admin', [
                    'user_id' => $admin->id,
                    'email' => $admin->email,
                    'is_admin' => $admin->is_admin,
                    'is_super_admin' => $admin->is_super_admin
                ]);
                return response()->json([
                    'status' => false, 
                    'code' => 403, 
                    'msg' => 'Only administrators can impersonate users. Your account does not have admin privileges.'
                ]);
            }

            // Find the user to impersonate (case-insensitive email search)
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($postData['email'])])->first();
            
            if(!$user) {
                // Log all users with similar emails for debugging
                $similarUsers = User::where('email', 'like', '%' . $postData['email'] . '%')
                    ->select('id', 'email', 'name')
                    ->get();
                
                \Log::warning('Admin impersonation failed: User not found', [
                    'admin_id' => $admin->id,
                    'requested_email' => $postData['email'],
                    'similar_emails' => $similarUsers->pluck('email')->toArray()
                ]);
                
                $errorMsg = 'User not found with email: ' . $postData['email'];
                if ($similarUsers->count() > 0) {
                    $errorMsg .= '. Similar emails found: ' . $similarUsers->pluck('email')->join(', ');
                }
                
                return response()->json([
                    'status' => false, 
                    'code' => 404, 
                    'msg' => $errorMsg
                ]);
            }

            // Create token for the impersonated user
            $token = $user->createToken('lightsAndWaterAPP')->plainTextToken;
            
            $responseData = array(
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'is_admin' => $isAdmin ? 1 : 0, // Keep admin status from actual admin
                'is_super_admin' => $isSuperAdmin ? 1 : 0, // Keep super admin status
                'role' => 'admin', // Always admin role when impersonating
                'impersonating' => true, // Flag to indicate impersonation
                'impersonated_user_id' => $user->id, // The user being impersonated
                'admin_user_id' => $admin->id // The actual admin user
            );
            
            \Log::info('Admin impersonation successful', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'impersonated_user_id' => $user->id,
                'impersonated_email' => $user->email
            ]);
            
            return response()->json([
                'status' => true, 
                'code' => 200, 
                'msg' => 'Impersonation successful!', 
                'token' => $token, 
                'data' => $responseData
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Admin impersonation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $postData['email'] ?? 'not provided'
            ]);
            
            return response()->json([
                'status' => false, 
                'code' => 500, 
                'msg' => 'An error occurred during impersonation: ' . $e->getMessage()
            ], 500);
        }
    }
}
