<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $name = config('rafael.admin.name');
        $email = config('rafael.admin.email');
        $password = config('rafael.admin.password');

        if (blank($name) || blank($email) || blank($password)) {
            throw new RuntimeException('ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD must all be set before seeding.');
        }

        User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }
}
