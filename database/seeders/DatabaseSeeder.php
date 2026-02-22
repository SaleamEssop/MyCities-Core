<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            BaseDataSeeder::class,   // Creates regions, meter types, tariff templates (MUST BE FIRST)
            UserSeeder::class,       // Creates demo and admin users
            DemoUserSeeder::class,   // Creates full demo setup: region, tariff, site, account, meters, readings
            TestSiteSeeder::class,
            PageSeeder::class,       // Creates sample pages for app navigation
        ]);
    }
}