<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Add billing_mode enum column
            if (!Schema::hasColumn('bills', 'billing_mode')) {
                $table->enum('billing_mode', ['PERIOD_TO_PERIOD', 'DATE_TO_DATE'])
                    ->nullable()
                    ->after('tariff_template_id');
            }
            
            // Add period dates for Period to Period billing
            if (!Schema::hasColumn('bills', 'period_start_date')) {
                $table->date('period_start_date')->nullable()->after('billing_mode');
            }
            
            if (!Schema::hasColumn('bills', 'period_end_date')) {
                $table->date('period_end_date')->nullable()->after('period_start_date');
            }
            
            // Add sector_readings JSON column for Date to Date billing
            if (!Schema::hasColumn('bills', 'sector_readings')) {
                $table->json('sector_readings')->nullable()->after('period_end_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (Schema::hasColumn('bills', 'sector_readings')) {
                $table->dropColumn('sector_readings');
            }
            if (Schema::hasColumn('bills', 'period_end_date')) {
                $table->dropColumn('period_end_date');
            }
            if (Schema::hasColumn('bills', 'period_start_date')) {
                $table->dropColumn('period_start_date');
            }
            if (Schema::hasColumn('bills', 'billing_mode')) {
                $table->dropColumn('billing_mode');
            }
        });
    }
};












