<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Account & Access' => 'Login, password, and permission concerns.',
            'Technical Issue' => 'Errors, crashes, and unexpected behavior.',
            'Billing' => 'Invoices, payments, and subscription concerns.',
            'General' => 'Anything that does not fit another category.',
        ];

        foreach ($categories as $name => $description) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}
