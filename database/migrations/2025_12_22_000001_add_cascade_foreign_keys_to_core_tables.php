<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddCascadeForeignKeysToCoreTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add foreign key constraint: sites.user_id -> users.id (CASCADE)
        if (Schema::hasTable('sites') && Schema::hasTable('users')) {
            // Drop existing foreign key if it exists
            try {
                DB::statement('ALTER TABLE sites DROP FOREIGN KEY sites_user_id_foreign');
            } catch (\Exception $e) {
                // Foreign key doesn't exist or has different name, try common variations
                try {
                    DB::statement('ALTER TABLE sites DROP FOREIGN KEY sites_user_id_fk');
                } catch (\Exception $e2) {
                    // Ignore - foreign key may not exist
                }
            }
            
            // First, ensure user_id column type matches users.id (bigint unsigned)
            // Use raw SQL to change column type if it's currently integer
            try {
                DB::statement('ALTER TABLE sites MODIFY COLUMN user_id BIGINT UNSIGNED NOT NULL');
            } catch (\Exception $e) {
                // Column might already be correct type, ignore
            }
            
            Schema::table('sites', function (Blueprint $table) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }

        // Add foreign key constraint: accounts.site_id -> sites.id (CASCADE)
        if (Schema::hasTable('accounts') && Schema::hasTable('sites')) {
            // Drop existing foreign key if it exists
            try {
                DB::statement('ALTER TABLE accounts DROP FOREIGN KEY accounts_site_id_foreign');
            } catch (\Exception $e) {
                try {
                    DB::statement('ALTER TABLE accounts DROP FOREIGN KEY accounts_site_id_fk');
                } catch (\Exception $e2) {
                    // Ignore
                }
            }
            
            // First, ensure site_id column type matches sites.id (bigint unsigned)
            try {
                DB::statement('ALTER TABLE accounts MODIFY COLUMN site_id BIGINT UNSIGNED NOT NULL');
            } catch (\Exception $e) {
                // Column might already be correct type, ignore
            }
            
            Schema::table('accounts', function (Blueprint $table) {
                $table->foreign('site_id')
                    ->references('id')
                    ->on('sites')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }

        // Add foreign key constraint: meters.account_id -> accounts.id (CASCADE)
        if (Schema::hasTable('meters') && Schema::hasTable('accounts')) {
            // Drop existing foreign key if it exists
            try {
                DB::statement('ALTER TABLE meters DROP FOREIGN KEY meters_account_id_foreign');
            } catch (\Exception $e) {
                try {
                    DB::statement('ALTER TABLE meters DROP FOREIGN KEY meters_account_id_fk');
                } catch (\Exception $e2) {
                    // Ignore
                }
            }
            
            // First, ensure account_id column type matches accounts.id (bigint unsigned)
            try {
                DB::statement('ALTER TABLE meters MODIFY COLUMN account_id BIGINT UNSIGNED NOT NULL');
            } catch (\Exception $e) {
                // Column might already be correct type, ignore
            }
            
            Schema::table('meters', function (Blueprint $table) {
                $table->foreign('account_id')
                    ->references('id')
                    ->on('accounts')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }

        // Add foreign key constraint: meter_readings.meter_id -> meters.id (CASCADE)
        if (Schema::hasTable('meter_readings') && Schema::hasTable('meters')) {
            // Drop existing foreign key if it exists
            try {
                DB::statement('ALTER TABLE meter_readings DROP FOREIGN KEY meter_readings_meter_id_foreign');
            } catch (\Exception $e) {
                try {
                    DB::statement('ALTER TABLE meter_readings DROP FOREIGN KEY meter_readings_meter_id_fk');
                } catch (\Exception $e2) {
                    // Ignore
                }
            }
            
            // First, ensure meter_id column type matches meters.id (bigint unsigned)
            try {
                DB::statement('ALTER TABLE meter_readings MODIFY COLUMN meter_id BIGINT UNSIGNED NOT NULL');
            } catch (\Exception $e) {
                // Column might already be correct type, ignore
            }
            
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->foreign('meter_id')
                    ->references('id')
                    ->on('meters')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop foreign keys in reverse order
        if (Schema::hasTable('meter_readings')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->dropForeign(['meter_id']);
            });
        }

        if (Schema::hasTable('meters')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
            });
        }

        if (Schema::hasTable('accounts')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropForeign(['site_id']);
            });
        }

        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }
    }
}





















