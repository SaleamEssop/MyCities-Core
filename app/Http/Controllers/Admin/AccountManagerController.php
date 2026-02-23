<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Models\MeterType;
use App\Models\RegionZone;
use App\Models\Regions;
use App\Models\RegionsAccountTypeCost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class AccountManagerController extends Controller
{
    /**
     * Show the Account Manager page.
     * GET /admin/account-manager
     */
    public function index()
    {
        $regions = Regions::with('zones')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'name'              => $r->name,
                'province'          => $r->province,
                'municipality'      => $r->municipality,
                'water_email'       => $r->water_email,
                'electricity_email' => $r->electricity_email,
                'zones'             => $r->zones->map(fn ($z) => [
                    'id'                => $z->id,
                    'zone_name'         => $z->zone_name,
                    'water_email'       => $z->water_email,
                    'electricity_email' => $z->electricity_email,
                ]),
            ]);

        $meterTypes = MeterType::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/AccountManager', [
            'regions'    => $regions,
            'meterTypes' => $meterTypes,
        ]);
    }

    /**
     * Return a user's full data: profile, accounts, and meters per account.
     * GET /admin/account-manager/user/{id}
     */
    public function getUserAccounts($id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        // Load accounts directly via user_id (site_id is now nullable — reserved for building management)
        $accountModels = Account::with([
            'meters',
            'tariffTemplate:id,template_name',
            'region:id,name,province,municipality',
            'zone:id,zone_name',
        ])->where('user_id', $id)->get();

        $accounts = $accountModels->map(fn ($a) => [
            'id'               => $a->id,
            'account_name'     => $a->account_name,
            'account_number'   => $a->account_number,
            'name_on_bill'     => $a->name_on_bill,
            'description'      => $a->optional_information,
            'bill_day'         => $a->bill_day,
            'read_day'         => $a->read_day,
            'address'          => $a->address,
            'latitude'         => $a->latitude,
            'longitude'        => $a->longitude,
            'water_email'      => $a->water_email,
            'electricity_email'=> $a->electricity_email,
            'region_id'        => $a->region_id,
            'zone_id'          => $a->zone_id,
            'tariff_template_id' => $a->tariff_template_id,
            'tariff_name'      => $a->tariffTemplate?->template_name,
            'region_name'      => $a->region ? "{$a->region->municipality}, {$a->region->province}" : null,
            'zone_name'        => $a->zone?->zone_name,
            'meters'           => $a->meters->map(fn ($m) => [
                'id'           => $m->id,
                'meter_title'  => $m->meter_title,
                'meter_number' => $m->meter_number,
                'meter_type_id'=> $m->meter_type_id,
            ])->values(),
        ])->values();


        return response()->json([
            'success' => true,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->contact_number,
            ],
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a new account for a user (JSON response, not redirect).
     * POST /admin/account-manager/account
     */
    public function storeAccount(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'              => 'required|exists:users,id',
            'name_on_bill'         => 'required|string|max:255',
            'account_number'       => 'required|string|max:255',
            'optional_information' => 'nullable|string|max:255',
            'bill_day'             => 'required|integer|min:1|max:31',
            'address'              => 'nullable|string',
            'latitude'             => 'nullable|numeric',
            'longitude'            => 'nullable|numeric',
            'region_id'            => 'nullable|exists:regions,id',
            'zone_id'              => 'nullable|exists:region_zones,id',
            'tariff_template_id'   => 'nullable|exists:regions_account_type_cost,id',
            'water_email'          => 'nullable|email|max:255',
            'electricity_email'    => 'nullable|email|max:255',
        ]);

        try {
            $billDay = (int) $request->bill_day;
            $readDay = $billDay > 5 ? $billDay - 5 : 30 + ($billDay - 5);

            $waterEmail = $request->water_email;
            $elecEmail  = $request->electricity_email;

            if (!$waterEmail || !$elecEmail) {
                if ($request->zone_id) {
                    $zone       = RegionZone::find($request->zone_id);
                    $waterEmail = $waterEmail ?: ($zone?->water_email);
                    $elecEmail  = $elecEmail  ?: ($zone?->electricity_email);
                }
                if ((!$waterEmail || !$elecEmail) && $request->region_id) {
                    $region     = Regions::find($request->region_id);
                    $waterEmail = $waterEmail ?: ($region?->water_email);
                    $elecEmail  = $elecEmail  ?: ($region?->electricity_email);
                }
            }

            // Accounts are self-contained with their own address fields.
            // Sites are reserved for future building/estate management.
            $account = Account::create([
                'user_id'              => (int) $request->user_id,
                'site_id'              => null,
                'tariff_template_id'   => $request->tariff_template_id,
                'region_id'            => $request->region_id,
                'zone_id'              => $request->zone_id,
                'account_name'         => $request->name_on_bill,
                'account_number'       => $request->account_number,
                'name_on_bill'         => $request->name_on_bill,
                'optional_information' => $request->optional_information,
                'billing_date'         => $billDay,
                'bill_day'             => $billDay,
                'read_day'             => $readDay,
                'address'              => $request->address,
                'latitude'             => $request->latitude,
                'longitude'            => $request->longitude,
                'water_email'          => $waterEmail,
                'electricity_email'    => $elecEmail,
            ]);

            return response()->json([
                'success'    => true,
                'message'    => 'Account created successfully.',
                'account_id' => $account->id,
                'account'    => [
                    'id'               => $account->id,
                    'account_name'     => $account->account_name,
                    'account_number'   => $account->account_number,
                    'name_on_bill'     => $account->name_on_bill,
                    'description'      => $account->optional_information,
                    'bill_day'         => $account->bill_day,
                    'read_day'         => $account->read_day,
                    'address'          => $account->address,
                    'water_email'      => $account->water_email,
                    'electricity_email'=> $account->electricity_email,
                    'meters'           => [],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('AccountManagerController::storeAccount', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a meter to an account.
     * POST /admin/account-manager/meter
     */
    public function storeMeter(Request $request): JsonResponse
    {
        $request->validate([
            'account_id'    => 'required|exists:accounts,id',
            'meter_type_id' => 'required|exists:meter_types,id',
            'meter_number'  => 'required|string|max:50',
            'meter_title'   => 'nullable|string|max:255',
        ]);

        try {
            $meterType = MeterType::find($request->meter_type_id);
            $meter = Meter::create([
                'account_id'        => $request->account_id,
                'meter_type_id'     => $request->meter_type_id,
                'meter_category_id' => $request->meter_category_id ?? null,
                'meter_title'       => $request->meter_title ?: ($meterType?->title . ' Meter'),
                'meter_number'      => $request->meter_number,
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Meter added.',
                'meter'    => [
                    'id'            => $meter->id,
                    'meter_title'   => $meter->meter_title,
                    'meter_number'  => $meter->meter_number,
                    'meter_type_id' => $meter->meter_type_id,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a meter.
     * DELETE /admin/account-manager/meter/{id}
     */
    public function deleteMeter($id): JsonResponse
    {
        try {
            $meter = Meter::findOrFail($id);
            $meter->delete();
            return response()->json(['success' => true, 'message' => 'Meter deleted.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
