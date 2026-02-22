<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterType;
use App\Models\Site;
use App\Models\RegionsAccountTypeCost;
use Illuminate\Support\Facades\Log;

class BillingRepository
{
    /**
     * Find site by user ID or fallback to email.
     */
    public function findSiteByUserId(int $userId, string $email, string $correlationId = 'unknown'): ?Site
    {
        // First try direct user_id match
        $site = Site::where('user_id', $userId)
            ->with(['accounts.meters', 'accounts.tariffTemplate'])
            ->first();

        if ($site) {
            Log::channel('api')->info('BillingRepo - Site found by user_id', [
                'correlation_id' => $correlationId,
                'user_id' => $userId,
                'site_id' => $site->id
            ]);
            return $site;
        }

        // Fallback: Try email match (for data integrity issues)
        $site = Site::where('email', $email)
            ->with(['accounts.meters', 'accounts.tariffTemplate'])
            ->first();

        if ($site) {
            Log::channel('api')->warning('BillingRepo - Site found by email fallback (user_id mismatch)', [
                'correlation_id' => $correlationId,
                'user_id' => $userId,
                'email' => $email,
                'site_id' => $site->id
            ]);
        }

        return $site;
    }

    /**
     * Find account with necessary relationships loaded.
     */
    public function findAccountWithDetails(int $accountId): ?Account
    {
        return Account::with(['meters', 'tariffTemplate', 'site.user'])->find($accountId);
    }

    /**
     * Find specific meter types for an account.
     */
    public function getAccountMetersByType(Account $account): array
    {
        $meters = $account->meters;
        $waterMeter = null;
        $electricityMeter = null;

        $waterType = $this->getMeterTypeByTitle('water');
        $electricityType = $this->getMeterTypeByTitle('electricity');

        if ($waterType) {
            $waterMeter = $meters->where('meter_type_id', $waterType->id)->first();

            // Direct query fallback
            if (!$waterMeter) {
                $waterMeter = Meter::where('account_id', $account->id)
                    ->where('meter_type_id', $waterType->id)
                    ->first();
            }
        }

        if ($electricityType) {
            $electricityMeter = $meters->where('meter_type_id', $electricityType->id)->first();

            // Direct query fallback
            if (!$electricityMeter) {
                $electricityMeter = Meter::where('account_id', $account->id)
                    ->where('meter_type_id', $electricityType->id)
                    ->first();
            }
        }

        return [
            'water' => $waterMeter,
            'electricity' => $electricityMeter,
            'all' => $meters
        ];
    }

    /**
     * Case-insensitive meter type lookup.
     */
    public function getMeterTypeByTitle(string $title): ?MeterType
    {
        return MeterType::whereRaw('LOWER(TRIM(title)) = ?', [strtolower($title)])->first();
    }

    /**
     * Find tariff template with fallback to direct query.
     */
    public function getTariffTemplateForAccount(Account $account): ?RegionsAccountTypeCost
    {
        $tariff = $account->tariffTemplate;

        if (!$tariff && $account->tariff_template_id) {
            $tariff = RegionsAccountTypeCost::find($account->tariff_template_id);
        }

        return $tariff;
    }

    /**
     * Find account for a user or if admin, find any account.
     */
    public function findAccount(?int $accountId, \Illuminate\Contracts\Auth\Authenticatable $user): ?Account
    {
        $isAdmin = $user->is_admin ?? false;

        if ($accountId && $isAdmin) {
            return Account::with(['meters', 'tariffTemplate', 'site.user'])->find($accountId);
        }

        $query = Account::with(['meters', 'tariffTemplate', 'site'])
            ->whereHas('site', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        if ($accountId) {
            return $query->find($accountId);
        }

        return $query->first();
    }

    /**
     * Find a meter of a specific type for an account.
     */
    public function findMeterByType(Account $account, string $type): ?Meter
    {
        $meterType = $this->getMeterTypeByTitle($type);
        if (!$meterType)
            return null;

        return Meter::where('account_id', $account->id)
            ->where('meter_type_id', $meterType->id)
            ->first();
    }

    /**
     * Get all readings for all meters belonging to an account.
     */
    public function getAllReadingsForAccount(Account $account)
    {
        $meterIds = $account->meters->pluck('id')->toArray();
        if (empty($meterIds)) {
            return collect([]);
        }

        return \App\Models\MeterReadings::whereIn('meter_id', $meterIds)
            ->orderBy('reading_date', 'asc')
            ->get();
    }
}
