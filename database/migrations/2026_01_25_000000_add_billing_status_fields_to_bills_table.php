<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBillingStatusFieldsToBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('usage_status', 20)->nullable()->after('is_provisional');
            $table->string('bill_total_status', 20)->nullable()->after('usage_status');
            $table->decimal('adjustment_brought_forward', 10, 2)->default(0)->after('bill_total_status');
            $table->decimal('usage_charge', 10, 2)->nullable()->after('adjustment_brought_forward');
            $table->decimal('editable_charge_total', 10, 2)->nullable()->after('usage_charge');
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
                'usage_status',
                'bill_total_status',
                'adjustment_brought_forward',
                'usage_charge',
                'editable_charge_total'
            ]);
        });
    }
}



















