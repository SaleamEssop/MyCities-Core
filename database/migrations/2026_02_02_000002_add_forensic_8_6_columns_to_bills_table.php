<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForensic86ColumnsToBillsTable extends Migration
{
    /**
     * Run the migrations.
     * Forensic 8.6: Add adjustment_charge and status columns for immutability and adjustments
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bills', function (Blueprint $table) {
            if (!Schema::hasColumn('bills', 'adjustment_charge')) {
                $table->decimal('adjustment_charge', 10, 2)->default(0)->after('tiered_charge');
            }
            
            if (!Schema::hasColumn('bills', 'status')) {
                $table->string('status', 50)->nullable()->after('is_provisional');
            }
        });
        
        // Add index for performance on adjustment queries
        // Note: Index creation will be skipped if it already exists (Laravel handles this)
        try {
            Schema::table('bills', function (Blueprint $table) {
                $table->index(['account_id', 'meter_id', 'is_provisional', 'status'], 'bills_account_meter_status_idx');
            });
        } catch (\Exception $e) {
            // Index may already exist, continue
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bills', function (Blueprint $table) {
            // Drop index first (if exists)
            try {
                $table->dropIndex('bills_account_meter_status_idx');
            } catch (\Exception $e) {
                // Index may not exist, continue
            }
            
            // Drop columns
            if (Schema::hasColumn('bills', 'status')) {
                $table->dropColumn('status');
            }
            
            if (Schema::hasColumn('bills', 'adjustment_charge')) {
                $table->dropColumn('adjustment_charge');
            }
        });
    }
}

