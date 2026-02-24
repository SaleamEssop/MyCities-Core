<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarm_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // ALM-001
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('condition_type');              // e.g. no_period_reading
            $table->json('condition_params')->nullable();  // {"days_threshold": 5}
            $table->string('delivery_method')->default('modal'); // modal | sound | email | push
            $table->string('severity')->default('warning');      // info | warning | critical
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarm_definitions');
    }
};
