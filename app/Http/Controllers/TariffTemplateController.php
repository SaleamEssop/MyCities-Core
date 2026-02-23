<?php

namespace App\Http\Controllers;

use App\Models\RegionsAccountTypeCost;
use App\Models\Regions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class TariffTemplateController extends Controller
{
    public function index()
    {
        // Get costs with their related Region
        $costs = RegionsAccountTypeCost::with(['region'])
                    ->orderBy('region_id')
                    ->get();

        return view('admin.tariff_template.index', ['costs' => $costs]);
    }

    public function create()
    {
        return \Inertia\Inertia::render('Admin/TariffTemplateCreate', [
            'regions'     => Regions::orderBy('name')->get(['id','name','province','municipality']),
            'csrfToken'   => csrf_token(),
            'submitUrl'   => route('tariff-template-store'),
            'cancelUrl'   => route('tariff-template'),
            'getEmailUrl' => route('get-email-region', ['id' => '__ID__']),
        ]);
    }

    public function store(Request $request)
    {
        try {
            // Validation is now optional - allow saving empty templates
            $request->validate([
                'template_name' => 'nullable|string|max:255',
                'region_id' => 'nullable|exists:regions,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Find existing record by id or create new
            if ($request->has('id') && $request->id) {
                $cost = RegionsAccountTypeCost::findOrFail($request->id);
            } else {
                $cost = new RegionsAccountTypeCost();
            }

            // Assign non-array fields with defaults for NOT NULL columns
            $cost->template_name = $request->input('template_name', 'Untitled Template');
            $cost->region_id = $request->input('region_id') ?: null;
            $cost->start_date = $request->input('start_date') ?: null;
            $cost->end_date = $request->input('end_date') ?: null;
            $cost->water_used = $request->input('water_used', 1);
            $cost->electricity_used = $request->input('electricity_used', 1);
            $cost->vat_rate = $request->input('vat_rate', 0);
            $cost->vat_percentage = $request->input('vat_percentage', 0);
            $cost->ratable_value = $request->input('ratable_value', 0);
            $cost->rates_rebate = $request->input('rates_rebate', 0);
            // Note: billing_day and read_day are NOT stored in tariff templates
            // They are account-specific and set in the account edit form
            $cost->water_email = $request->input('water_email', '');
            $cost->electricity_email = $request->input('electricity_email', '');

            // Handle checkbox fields (convert to 1/0)
            $cost->is_water = $request->has('is_water') ? 1 : 0;
            $cost->is_electricity = $request->has('is_electricity') ? 1 : 0;

            // Billing period type — UI uses PERIOD_TO_PERIOD as alias for MONTHLY
            $billingType = $request->input('billing_type', 'MONTHLY');
            $cost->billing_type = ($billingType === 'PERIOD_TO_PERIOD') ? 'MONTHLY' : $billingType;

            // Explicitly assign array fields (Laravel casting handles JSON conversion)
            $cost->water_in = $request->input('waterin', []);
            $cost->water_out = $request->input('waterout', []);
            $cost->electricity = $request->input('electricity', []);
            $cost->additional = $request->input('additional', []);
            $cost->waterin_additional = $request->input('waterin_additional', []);
            $cost->waterout_additional = $request->input('waterout_additional', []);
            $cost->electricity_additional = $request->input('electricity_additional', []);
            
            // Handle new fixed costs and customer costs arrays
            $cost->fixed_costs = $request->input('fixed_costs', []);
            $cost->customer_costs = $request->input('customer_costs', []);

            $cost->save();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success'     => true,
                    'message'     => 'Tariff Template saved successfully!',
                    'redirect'    => route('tariff-template'),
                ]);
            }

            Session::flash('alert-class', 'alert-success');
            Session::flash('alert-message', 'Tariff Template saved successfully!');
            return redirect()->route('tariff-template');

        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = 'Validation failed: ' . implode(', ', $e->validator->errors()->all());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            Session::flash('alert-class', 'alert-danger');
            Session::flash('alert-message', $msg);
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Tariff Template save error: ' . $e->getMessage());
            $msg = 'Failed to save: ' . $e->getMessage();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 500);
            }
            Session::flash('alert-class', 'alert-danger');
            Session::flash('alert-message', $msg);
            return redirect()->back();
        }
    }

    public function edit($id)
    {
        $tariff_template = RegionsAccountTypeCost::findOrFail($id);

        return \Inertia\Inertia::render('Admin/TariffTemplateEdit', [
            'regions'      => Regions::orderBy('name')->get(['id','name','province','municipality']),
            'csrfToken'    => csrf_token(),
            'submitUrl'    => route('update-tariff-template'),
            'cancelUrl'    => route('tariff-template'),
            'copyUrl'      => route('copy-tariff-template'),
            'getEmailUrl'  => route('get-email-region', ['id' => '__ID__']),
            'existingData' => $tariff_template,
        ]);
    }

    public function update(Request $request)
    {
        return $this->store($request);
    }

    public function delete($id)
    {
        RegionsAccountTypeCost::destroy($id);

        Session::flash('alert-class', 'alert-success');
        Session::flash('alert-message', 'Tariff Template deleted successfully!');

        return redirect()->back();
    }

    public function copyRecord(Request $request)
    {
        $original = RegionsAccountTypeCost::findOrFail($request->id);

        $copy = $original->replicate();
        
        // Check if this should be a date child
        $isDateChild = $request->input('is_date_child', '0') === '1';
        
        if ($isDateChild) {
            // Set parent_id to create hierarchy
            $copy->parent_id = $original->id;
            $copy->template_name = $original->template_name . ' (Date Child)';
            
            Session::flash('alert-class', 'alert-success');
            Session::flash('alert-message', 'Date Child tariff created successfully! Update the date range for the new tariff.');
        } else {
            // Independent copy - no parent relationship
            $copy->parent_id = null;
            $copy->template_name = $original->template_name . ' (Copy)';
            
            Session::flash('alert-class', 'alert-success');
            Session::flash('alert-message', 'Independent copy created successfully!');
        }
        
        $copy->save();

        return redirect()->route('tariff-template-edit', ['id' => $copy->id]);
    }
}
