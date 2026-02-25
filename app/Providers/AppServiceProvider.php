<?php

namespace App\Providers;

use App\Models\MeterReadings;
use App\Observers\MeterReadingObserver;
use App\EditorBlocks\ImageBlock;
use BumpCore\EditorPhp\EditorPhp;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        MeterReadings::observe(MeterReadingObserver::class);

        // Use Bootstrap 5 templates — images get w-100/d-block which are Bootstrap 4 compatible.
        EditorPhp::useBootstrapFive();

        // Override the Image block so relative storage paths (/storage/...) pass validation.
        EditorPhp::register(['image' => ImageBlock::class]);
    }
}
