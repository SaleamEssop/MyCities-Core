<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\RegionZone;
use App\Models\Regions;
use App\Models\RegionsAccountTypeCost;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    /**
     * Show the account creation form.
     */
    public function create()
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

        return Inertia::render('Admin/AccountCreate', [
            'regions' => $regions,
        ]);
    }

    /**
     * Store a new account.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id'             => 'required|exists:users,id',
            'account_name'        => 'nullable|string|max:255',
            'name_on_bill'        => 'required|string|max:255',
            'account_number'      => 'required|string|max:255',
            'optional_information'=> 'nullable|string|max:255',
            'bill_day'            => 'required|integer|min:1|max:31',
            'address'             => 'nullable|string',
            'latitude'            => 'nullable|numeric',
            'longitude'           => 'nullable|numeric',
            'region_id'           => 'nullable|exists:regions,id',
            'zone_id'             => 'nullable|exists:region_zones,id',
            'tariff_template_id'  => 'nullable|exists:regions_account_type_cost,id',
            'water_email'         => 'nullable|email|max:255',
            'electricity_email'   => 'nullable|email|max:255',
        ]);

        // Derive read_day from bill_day (bill_day - 5, wrapping to previous month)
        $billDay = (int) $request->bill_day;
        $readDay = $billDay > 5 ? $billDay - 5 : 30 + ($billDay - 5);

        // Resolve emails: prefer explicitly passed emails (from ArcGIS zone),
        // then fall back to zone default, then region default
        $waterEmail = $request->water_email;
        $elecEmail  = $request->electricity_email;

        if (!$waterEmail || !$elecEmail) {
            if ($request->zone_id) {
                $zone = RegionZone::find($request->zone_id);
                $waterEmail = $waterEmail ?: ($zone->water_email ?? null);
                $elecEmail  = $elecEmail  ?: ($zone->electricity_email ?? null);
            }
            if ((!$waterEmail || !$elecEmail) && $request->region_id) {
                $region     = Regions::find($request->region_id);
                $waterEmail = $waterEmail ?: ($region->water_email ?? null);
                $elecEmail  = $elecEmail  ?: ($region->electricity_email ?? null);
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
            'account_name'         => $request->account_name ?: $request->name_on_bill,
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

        return redirect(route('account-list'))
            ->with('alert-class', 'alert-success')
            ->with('alert-message', 'Account created successfully for ' . $request->name_on_bill);
    }
}
