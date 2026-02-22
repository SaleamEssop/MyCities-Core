<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitializationReadingToMetersTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds start_reading and start_reading_date fields to meters table.
     * These are REQUIRED for Period 1 initialization.
     */
    public function up()
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->decimal('start_reading', 15, 2)->nullable()->after('digit_count');
            $table->date('start_reading_date')->nullable()->after('start_reading');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropColumn(['start_reading', 'start_reading_date']);
        });
    }
}
