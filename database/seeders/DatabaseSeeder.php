<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Product::updateOrCreate(
            ['code' => 'DEMO-001'],
            ['name' => 'Mouse gamer RGB', 'unit_code' => 'NIU', 'unit_price' => 89.90, 'tax_rate' => 18.00, 'active' => true],
        );

        Product::updateOrCreate(
            ['code' => 'DEMO-002'],
            ['name' => 'Reparación de computadora', 'unit_code' => 'ZZ', 'unit_price' => 50.00, 'tax_rate' => 18.00, 'active' => true],
        );

        User::updateOrCreate(
            ['email' => 'admin@devfest.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('123456789'),
            ],
        );

    }
}
