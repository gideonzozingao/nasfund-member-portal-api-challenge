<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiTokenSeeder extends Seeder
{
    public function run(): void
    {
        $tokens = [
            [
                'description' => 'Nasfund — Testing API Token',
                'is_active'   => true,
                'expires_at'  => now()->addYear(),
            ],
            [
                'description' => 'Nasfund — Production API Token',
                'is_active'   => true,
                'expires_at'  => now()->addYear(),
            ],
            
        ];

        foreach ($tokens as $token) {
            $plain = Str::random(64);

            DB::table('api_tokens')->insert([
                'token'        => $plain,
                'description'  => $token['description'],
                'is_active'    => $token['is_active'],
                'last_used_at' => null,
                'expires_at'   => $token['expires_at'],
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Print the plain token once at seed time — it cannot be recovered later.
            $this->command->info(
                sprintf('%-40s %s', $token['description'], $plain)
            );
        }

        $this->command->newLine();
        $this->command->warn('Copy the tokens above — they are not stored in recoverable form.');
    }
}