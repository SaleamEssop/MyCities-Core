<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDigitCountToMetersTable extends Migration
{
    /**
     * Run the migrations.
     * Forensic 8.6: Add digit_count column to meters table for rollover calculation
     *
     * @return void
     */
    public function up()
    {
        Schema::table('meters', function (Blueprint $table) {
            if (!Schema::hasColumn('meters', 'digit_count')) {
                $table->integer('digit_count')->default(4)->after('meter_number');
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
        Schema::table('meters', function (Blueprint $table) {
            if (Schema::hasColumn('meters', 'digit_count')) {
                $table->dropColumn('digit_count');
            }
        });
    }
}



