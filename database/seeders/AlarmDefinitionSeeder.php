<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AlarmDefinition;

class AlarmDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $alarms = [
            [
                'code'             => 'ALM-001',
                'name'             => 'No Period Reading',
                'description'      => 'No readings exist for the current billing period and the last reading is more than 5 days old.',
                'condition_type'   => 'no_period_reading',
                'condition_params' => ['days_threshold' => 5],
                'delivery_method'  => 'modal',
                'severity'         => 'warning',
                'is_active'        => true,
            ],
            [
                'code'             => 'ALM-002',
                'name'             => 'Reading Overdue',
                'description'      => 'A reading exists in the current period but the last reading was more than 5 days ago. Read Day is bill day minus 5 days; countdown begins 5 days before Read Day.',
                'condition_type'   => 'reading_overdue',
                'condition_params' => ['days_threshold' => 5, 'read_day_offset' => -5],
                'delivery_method'  => 'modal',
                'severity'         => 'warning',
                'is_active'        => true,
            ],
        ];

        foreach ($alarms as $alarm) {
            AlarmDefinition::updateOrCreate(['code' => $alarm['code']], $alarm);
        }
    }
}
