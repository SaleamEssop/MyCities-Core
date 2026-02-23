<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Direct user ownership — replaces the indirect site_id -> sites.user_id path
            $table->unsignedBigInteger('user_id')->nullable()->after('site_id');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Back-fill user_id from the site relationship for existing accounts
        DB::statement('
            UPDATE accounts a
            INNER JOIN sites s ON s.id = a.site_id
            SET a.user_id = s.user_id
            WHERE a.user_id IS NULL AND a.site_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
