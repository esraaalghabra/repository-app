<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Supplier::truncate();
        Supplier::create([
            'name'=>'Medical',
            'address'=>'Medical',
            'phone_number'=>'1234433333222',
        ]);
    }
}
