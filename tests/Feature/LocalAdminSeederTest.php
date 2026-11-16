<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_the_single_local_admin(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // safe to run twice

        $this->assertSame(1, User::count());
        $admin = User::sole();
        $this->assertSame('admin@gouwwaas.be', $admin->email);
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('password', $admin->password));
    }
}
