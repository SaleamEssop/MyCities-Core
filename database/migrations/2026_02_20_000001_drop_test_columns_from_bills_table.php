<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes 4 test columns that were added in December 2025 for migration-detection
 * testing only. These columns hold no billing data and are not referenced anywhere
 * in the application code.
 *
 * Supersedes (and these files were deleted):
 *   2025_12_27_091950_add_test_column_to_bills_table.php
 *   2025_12_27_092000_add_another_test_column_to_bills_table.php
 *   2025_12_27_092100_add_temp_test_column_to_bills_table.php
 *   2025_12_27_092200_add_final_test_column_to_bills_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Guard with hasColumn so this is safe whether or not the
            // original test migrations were ever run.
            $columns = ['test_migration_check', 'another_test_check', 'temp_test_migration', 'final_test_migration'];
            $toDrop = array_filter($columns, fn($col) => Schema::hasColumn('bills', $col));
            if (!empty($toDrop)) {
                $table->dropColumn(array_values($toDrop));
            }
        });
    }

    public function down(): void
    {
        // No rollback — these columns should not be re-created.
    }
};
