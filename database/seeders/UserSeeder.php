<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\User\CreateUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command->info('UserSeeder: a user already exists, skipping.');

            return;
        }

        CreateUser::execute([
            'name' => 'Admin',
            'email' => 'admin@trypost.it',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin account created — change the password on first login:');
        $this->command->line('  email:    admin@trypost.it');
        $this->command->line('  password: password');
    }
}
