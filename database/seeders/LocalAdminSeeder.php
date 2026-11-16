<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The only account in a local database: admin@gouwwaas.be / password.
 */
class LocalAdminSeeder extends Seeder
{
    public const EMAIL = 'admin@gouwwaas.be';

    public const PASSWORD = 'password';

    public function run(): void
    {
        $admin = User::firstOrNew(['email' => self::EMAIL]);

        // is_admin can't be mass-assigned on purpose.
        $admin->forceFill([
            'name' => 'Beheerder',
            'password' => self::PASSWORD,
            'is_admin' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}
