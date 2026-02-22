<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReconciliationFieldsToBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bills', function (Blueprint $table) {
            // Add fields for reconciliation tracking
            // original_provisional_value: The original provisional consumption value before recalculation
            $table->decimal('original_provisional_value', 12, 4)->nullable()->after('consumption');
            
            // calculated_value: The recalculated consumption value after reconciliation
            $table->decimal('calculated_value', 12, 4)->nullable()->after('original_provisional_value');
            
            // adjustment_delta: The difference between calculated and original (can be positive or negative)
            // This is billed in the current month only
            $table->decimal('adjustment_delta', 12, 4)->nullable()->after('calculated_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['original_provisional_value', 'calculated_value', 'adjustment_delta']);
        });
    }
}


















