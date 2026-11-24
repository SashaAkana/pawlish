<?php

namespace Database\Seeders;

use App\Models\User;
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
        $name = trim((string) env('SEED_ADMIN_NAME', 'Администратор'));
        $email = trim((string) env('SEED_ADMIN_EMAIL', ''));
        $password = (string) env('SEED_ADMIN_PASSWORD', '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 16) {
            throw new \RuntimeException('Укажите SEED_ADMIN_EMAIL и пароль длиной не менее 16 символов.');
        }

        User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'email_verified_at' => now()]
        );
    }
}
