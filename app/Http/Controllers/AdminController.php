<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountFixedCost;
use App\Models\FixedCost;
use App\Models\Meter;
use App\Models\MeterCategory;
use App\Models\MeterReadings;
use App\Models\MeterType;
use App\Models\Payment;
use App\Models\Regions;
use App\Models\RegionsAccountTypeCost;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\RegionZone;

class AdminController extends Controller
{
    /**
     * Handle admin forgot password request
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if the email exists and is an admin
        $user = User::where('email', $request->email)
                    ->where(function($q) {
                        $q->where('is_admin', 1)->orWhere('is_super_admin', 1);
                    })
                    ->first();

        if (!$user) {
            Session::flash('alert-class', 'alert-danger');
            Session::flash('alert-message', 'No admin account found with that email address.');
            return redirect()->back();
        }

        // Generate a temporary password
        $tempPassword = Str::random(12);
        $user->password = Hash::make($tempPassword);
        $user->save();

        // Send email to the designated recovery email
        $recoveryEmail = 'saleam.essop@gmail.com';
        
        try {
            Mail::raw("
MyCities Admin Password Reset

An admin password reset was requested for: {$request->email}

Your temporary password is: {$tempPassword}

Please login and change your password immediately.

If you did not request this reset, please contact support.

---
MyCities Administrator
            ", function($message) use ($recoveryEmail, $request) {
                $message->to($recoveryEmail)
                        ->subject('MyCities Admin Password Reset - ' . $request->email);
            });

            Session::flash('success', true);
            Session::flash('alert-class', 'alert-success');
            Session::flash('alert-message', 'Password reset instructions sent to the administrator email.');
        } catch (\Exception $e) {
            // If email fails, still show the temp password for now (dev mode)
            Session::flash('alert-class', 'alert-info');
            Session::flash('alert-message', 'Email service unavailable. Temporary password: ' . $tempPassword);
        }

        return redirect()->back();
    }

    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        $credentials = $request->only('email', 'password');
        
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect('/admin');
        }
        
        Session::flash('alert-class', 'alert-danger');
        Session::flash('alert-message', 'Invalid email or password');
        return redirect()->back();
    }

    public function home() { return view('admin.home'); }
    public function dashboard() { return view('admin.home'); }
    public function showUsers() { return view('admin.users', ['users' => User::all()]); }
    
    public function showAccounts() { return view('admin.accounts', ['accounts' => Account::with('site')->get()]); }
    public function showSites() { return view('admin.sites', ['sites' => Site::with(['user', 'region'])->get()]); }
    public function showRegions() { return view('admin.regions', ['regions' => Regions::all()]); }
    public function showMeters() { return view('admin.meters', ['meters' => Meter::with(['account', 'meterTypes'])->get()]); }
    public function showReadings() { return view('admin.meter_readings', ['readings' => MeterReadings::with('meter')->orderBy('id', 'desc')->get()]); }
    public function showAlarms() { return view('admin.alarms', ['alarms' => []]); }
    
    public function showPayments() { 
        return view('admin.payments', ['payments' => Payment::with('account')->orderBy('payment_date', 'desc')->get()]); 
    }

    public function addUserForm() { return view('admin.create_user'); }
    
    public function addSiteForm() { 
        return view('admin.create_site', ['users' => User::all(), 'regions' => Regions::all()]); 
    }

    public function addAccountForm() { 
        return view('admin.create_account', [
            'users' => User::all(), 
            'sites' => Site::all(), 
            'regions' => Regions::all()
        ]);
    }

    public function addMeterForm() {
        return view('admin.create_meter', ['accounts' => Account::all(), 'meterTypes' => MeterType::all(), 'meterCats' => MeterCategory::all()]);
    }

    public function addReadingForm() {
        return view('admin.create_meter_reading', ['meters' => Meter::with('account')->get()]);
    }
    
    public function addRegionForm() {
        return Inertia::render('Admin/RegionCreate');
    }

    public function createRegion(Request $request) {
        $request->validate([
            'province'          => 'required|string|max:255',
            'municipality'      => 'required|string|max:255',
            'name'              => 'nullable|string|max:255',
            'water_email'       => 'nullable|email|max:255',
            'electricity_email' => 'nullable|email|max:255',
            'zones'             => 'nullable|array',
            'zones.*.zone_name'         => 'required_with:zones|string|max:255',
            'zones.*.water_email'       => 'nullable|email|max:255',
            'zones.*.electricity_email' => 'nullable|email|max:255',
        ]);

        $region = Regions::create([
            'province'          => $request->input('province'),
            'municipality'      => $request->input('municipality'),
            'name'              => $request->input('name') ?: $request->input('municipality'),
            'water_email'       => $request->input('water_email'),
            'electricity_email' => $request->input('electricity_email'),
        ]);

        foreach ($request->input('zones', []) as $zone) {
            if (!empty($zone['zone_name'])) {
                RegionZone::create([
                    'region_id'         => $region->id,
                    'zone_name'         => $zone['zone_name'],
                    'water_email'       => $zone['water_email'] ?? null,
                    'electricity_email' => $zone['electricity_email'] ?? null,
                ]);
            }
        }

        return redirect(route('regions-list'))
            ->with('alert-class', 'alert-success')
            ->with('alert-message', 'Region created successfully');
    }

    public function editRegionForm($id) {
        $region = Regions::with('zones')->find($id);
        if (!$region) {
            return redirect(route('regions-list'))
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Region not found');
        }
        return Inertia::render('Admin/RegionEdit', [
            'region' => [
                'id'                => $region->id,
                'province'          => $region->province,
                'municipality'      => $region->municipality,
                'name'              => $region->name,
                'water_email'       => $region->water_email,
                'electricity_email' => $region->electricity_email,
                'zones'             => $region->zones->map(fn ($z) => [
                    'id'                => $z->id,
                    'zone_name'         => $z->zone_name,
                    'water_email'       => $z->water_email,
                    'electricity_email' => $z->electricity_email,
                ]),
            ],
        ]);
    }

    public function editRegion(Request $request) {
        $request->validate([
            'id'                => 'required|exists:regions,id',
            'province'          => 'required|string|max:255',
            'municipality'      => 'required|string|max:255',
            'name'              => 'nullable|string|max:255',
            'water_email'       => 'nullable|email|max:255',
            'electricity_email' => 'nullable|email|max:255',
            'zones'             => 'nullable|array',
            'zones.*.zone_name'         => 'required_with:zones|string|max:255',
            'zones.*.water_email'       => 'nullable|email|max:255',
            'zones.*.electricity_email' => 'nullable|email|max:255',
        ]);

        $region = Regions::findOrFail($request->id);
        $region->update([
            'province'          => $request->input('province'),
            'municipality'      => $request->input('municipality'),
            'name'              => $request->input('name') ?: $request->input('municipality'),
            'water_email'       => $request->input('water_email'),
            'electricity_email' => $request->input('electricity_email'),
        ]);

        // Sync zones: delete existing and re-create from submitted list
        RegionZone::where('region_id', $region->id)->delete();
        foreach ($request->input('zones', []) as $zone) {
            if (!empty($zone['zone_name'])) {
                RegionZone::create([
                    'region_id'         => $region->id,
                    'zone_name'         => $zone['zone_name'],
                    'water_email'       => $zone['water_email'] ?? null,
                    'electricity_email' => $zone['electricity_email'] ?? null,
                ]);
            }
        }

        return redirect(route('regions-list'))
            ->with('alert-class', 'alert-success')
            ->with('alert-message', 'Region updated successfully');
    }

    public function deleteRegion($id) {
        $region = Regions::find($id);
        if ($region) {
            $region->delete();
            Session::flash('alert-class', 'alert-success');
            Session::flash('alert-message', 'Region Deleted Successfully');
        } else {
            Session::flash('alert-class', 'alert-danger');
            Session::flash('alert-message', 'Region not found');
        }
        return redirect(route('regions-list'));
    }

    public function getRegionZones($regionId) {
        $zones = RegionZone::where('region_id', $regionId)->orderBy('zone_name')->get();
        return response()->json(['status' => 200, 'data' => $zones]);
    }

    public function getEmailBasedRegion($id) {
        $region = Regions::find($id);
        if (!$region) {
            return response()->json(['status' => 404, 'message' => 'Region not found']);
        }
        return response()->json([
            'status' => 200,
            'data' => [
                'water_email' => $region->water_email,
                'electricity_email' => $region->electricity_email,
            ]
        ]);
    }

    public function addPaymentForm() {
        $sites = Site::all();
        return view('admin.create_payment', ['sites' => $sites]);
    }

    public function createUser(Request $request) {
        $postData = $request->post();
        if(User::where('email', $postData['email'])->exists()) {
            Session::flash('alert-class', 'alert-danger');
            Session::flash('alert-message', 'Email exists');
            return redirect()->back();
        }
        
        // Create user
        $user = User::create([
            'name' => $postData['name'], 
            'email' => $postData['email'], 
            'contact_number' => $postData['contact_number'], 
            'password' => bcrypt($postData['password']), 
            'is_admin' => 0
        ]);
        
        // Automatically create a default site for the user (required for dashboard access)
        $region = Regions::first(); // Get first available region
        if ($region) {
            $site = Site::create([
                'user_id' => $user->id,
                'title' => $postData['name'] . "'s Site",
                'address' => 'Address not set',
                'lat' => 0.0,
                'lng' => 0.0,
                'email' => $postData['email'],
                'region_id' => $region->id,
                'billing_type' => 'monthly',
            ]);
            
            // Get first available tariff template for this region
            $tariffTemplate = RegionsAccountTypeCost::where('region_id', $region->id)
                ->where('is_active', 1)
                ->first();
            
            // Create a default account for the site
            $account = Account::create([
                'site_id' => $site->id,
                'account_name' => $postData['name'] . "'s Account",
                'account_number' => 'ACC-' . strtoupper(substr(md5($postData['email']), 0, 8)),
                'tariff_template_id' => $tariffTemplate ? $tariffTemplate->id : null,
                'bill_day' => 15, // Default billing day for monthly billing
                'read_day' => 1,  // Default reading day
            ]);
        }
        
        Session::flash('alert-class', 'alert-success');
        Session::flash('alert-message', 'User created successfully with default site and account');
        return redirect(route('show-users'));
    }

    public function deleteUser($id) { User::destroy($id); return redirect()->back(); }

    public function createSite(Request $request) {
        $postData = $request->post();
        Site::create([
            'user_id' => $postData['user_id'], 
            'title' => $postData['title'], 
            'lat' => $postData['lat'] ?? 0.0,
            'lng' => $postData['lng'] ?? 0.0,
            'address' => $postData['address'], 
            'email' => $postData['email'] ?? null, 
            'region_id' => $postData['region_id'], 
            'billing_type' => $postData['billing_type'] ?? 'monthly', 
            'site_username' => $postData['site_username'] ?? null
        ]);
        return redirect(route('show-sites'));
    }

    public function deleteSite($id) { Site::destroy($id); return redirect()->back(); }
    public function editSiteForm($id) { 
        $site = Site::with('user', 'region')->find($id);
        if (!$site) {
            abort(404, "Site with ID {$id} not found");
        }
        return view('admin.edit_site', [
            'site' => $site, 
            'users' => User::all(), 
            'regions' => Regions::all()
        ]); 
    }
    public function editSite(Request $request) { Site::where('id', $request->id)->update(['title'=>$request->title]); return redirect(route('show-sites')); }

    public function createAccount(Request $request) {
        $postData = $request->post();
        
        Account::create([
            'site_id' => $postData['site_id'], 
            'account_name' => $postData['title'], 
            'account_number' => $postData['number'], 
            'billing_date' => $postData['billing_date'],
            'optional_information' => $postData['optional_info'],
            'tariff_template_id' => $postData['tariff_template_id'] ?? null
        ]);
        
        return redirect(route('account-list'));
    }
    
    public function deleteAccount($id) { Account::destroy($id); return redirect()->back(); }
    public function editAccountForm($id) { 
        return view('admin.edit_account', [
            'account' => Account::with('tariffTemplate')->find($id), 
            'users' => User::all(), 
            'sites' => Site::all(),
            'regions' => Regions::all()
        ]); 
    }
    public function editAccount(Request $request) { return redirect(route('account-list')); }
    
    public function getAccountsBySite(Request $request) {
        $accounts = Account::where('site_id', $request->site_id)->get();
        return response()->json(['status' => 200, 'data' => $accounts]);
    }

    public function getAccountDetails(Request $request) {
        $account = Account::with(['site', 'meters', 'payments' => function($q) {
            $q->latest()->take(3);
        }])->find($request->account_id);

        if (!$account) {
            return response()->json(['status' => 404, 'message' => 'Account not found']);
        }

        $data = [
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'site_name' => $account->site->title ?? 'Unknown Site',
            'meters' => $account->meters->pluck('meter_number')->toArray(),
            'recent_payments' => $account->payments,
        ];

        return response()->json(['status' => 200, 'data' => $data]);
    }

    /**
     * Get tariff templates by region ID for AJAX dropdown population.
     * New endpoint for simplified billing architecture.
     */
    public function getTariffTemplatesByRegion($regionId) {
        $tariffTemplates = RegionsAccountTypeCost::where('region_id', $regionId)
            ->where('is_active', 1)
            ->select('id', 'template_name', 'start_date', 'end_date')
            ->get();
        
        return response()->json([
            'status' => 200, 
            'data' => $tariffTemplates
        ]);
    }

    public function createMeter(Request $request) {
        $postData = $request->post();
        Meter::create([
            'account_id'=>$postData['account_id'], 'meter_number'=>$postData['meter_number'], 
            'meter_type_id'=>$postData['meter_type_id'], 'meter_category_id'=>$postData['meter_category_id']
        ]);
        return redirect(route('meters-list'));
    }

    public function deleteMeter($id) { Meter::destroy($id); return redirect()->back(); }
    
    public function createReading(Request $request) {
        $postData = $request->post();
        $imageName = time().'.'.$request->image->extension();  
        $request->image->move(public_path('images'), $imageName);
        
        MeterReadings::create([
            'meter_id'=>$postData['meter_id'], 'reading_value'=>$postData['reading_value'], 'reading_date'=>$postData['reading_date'], 'image_url'=>$imageName
        ]);
        return redirect(route('meter-reading-list'));
    }
    
    public function createPayment(Request $request) {
        $postData = $request->post();
        Payment::create([
            'account_id' => $postData['account_id'], 
            'amount' => $postData['amount'], 
            'payment_date' => $postData['payment_date'], 
            'payment_method' => $postData['payment_method'] ?? 'EFT', 
            'reference' => $postData['reference'] ?? 'PAY-' . time(),
            'notes' => $postData['notes'] ?? null,
        ]);
        Session::flash('alert-class', 'alert-success');
        Session::flash('alert-message', 'Payment recorded successfully!');
        return redirect(route('payments-list'));
    }
    
    public function deletePayment($id) { Payment::destroy($id); return redirect()->back(); }

    // Helper for AJAX Sites by User
    public function getSitesByUser(Request $request) {
        // UPDATED: Eager load 'region' to support auto-fill
        $sites = Site::with('region')->where('user_id', $request->user_id)->get();
        return response()->json(['status' => 200, 'data' => $sites]);
    }
}
