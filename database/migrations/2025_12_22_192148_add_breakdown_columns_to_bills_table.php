<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBreakdownColumnsToBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->json('tier_breakdown')->nullable()->after('tiered_charge');
            $table->json('fixed_costs_breakdown')->nullable()->after('fixed_costs_total');
            $table->json('account_costs_breakdown')->nullable()->after('fixed_costs_breakdown');
            $table->json('warnings')->nullable()->after('account_costs_breakdown');
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
            $table->dropColumn([
                'tier_breakdown',
                'fixed_costs_breakdown',
                'account_costs_breakdown',
                'warnings'
            ]);
        });
    }
}
