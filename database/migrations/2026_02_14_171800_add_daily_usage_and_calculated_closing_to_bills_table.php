<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDailyUsageAndCalculatedClosingToBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bills', function (Blueprint $table) {
            if (!Schema::hasColumn('bills', 'daily_usage')) {
                $table->decimal('daily_usage', 12, 4)->nullable()->after('consumption');
            }
            if (!Schema::hasColumn('bills', 'calculated_closing')) {
                $table->decimal('calculated_closing', 12, 4)->nullable()->after('daily_usage');
            }
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
            $table->dropColumn(['daily_usage', 'calculated_closing']);
        });
    }
}
