<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Client::truncate();
        Client::create([
            'name'=>'Medical',
            'phone_number'=>'11111111114',
            'address'=>'Medical',
        ]);
    }
}
