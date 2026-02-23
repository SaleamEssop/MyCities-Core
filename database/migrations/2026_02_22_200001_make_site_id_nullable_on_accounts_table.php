<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Sites are now reserved for building/estate management (future dev).
            // Accounts are self-contained with their own address fields.
            $table->unsignedBigInteger('site_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable(false)->change();
        });
    }
};
