<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Product::truncate();
        Product::create([
            'name'=>'fish',
            'category_id'=>'1',
            'purchase_price'=>'100',
            'sale_price'=>'200',
            'amount'=>'200',
            'measuring_unit'=>'k',
        ]);
    }
}
