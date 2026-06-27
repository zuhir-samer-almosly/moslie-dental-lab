<?php

namespace Database\Seeders;

use App\Models\MaterialPurchase;
use Illuminate\Database\Seeder;

class MaterialPurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MaterialPurchase::factory(8)->create();
    }
}
