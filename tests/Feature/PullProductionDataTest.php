<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "Production" is a temporary SQLite file here; the local database is the usual in-memory one.
 */
class PullProductionDataTest extends TestCase
{
    private string $productionFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productionFile = tempnam(sys_get_temp_dir(), 'prod').'.sqlite';
        touch($this->productionFile);
        config(['database.connections.production' => [
            'driver' => 'sqlite',
            'database' => $this->productionFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        $this->artisan('migrate', ['--database' => 'production'])->assertSuccessful();

        $production = DB::connection('production');
        $production->table('users')->insert([
            ['id' => 7, 'name' => 'Echte Admin', 'email' => 'echte.admin@example.be', 'password' => Hash::make('geheim'), 'is_admin' => true],
            ['id' => 8, 'name' => 'Leiding', 'email' => 'leiding@example.be', 'password' => Hash::make('geheim'), 'is_admin' => false],
        ]);
        $production->table('activities')->insert(['id' => 3, 'user_id' => 7, 'title' => 'Gouwdag', 'slug' => 'gouwdag', 'description' => 'x', 'location' => 'Karree', 'starts_at' => '2026-12-01 10:00', 'registration_deadline' => '2026-11-30 10:00']);
        $production->table('activity_fields')->insert([
            ['id' => 1, 'activity_id' => 3, 'label' => 'Telefoon', 'type' => 'tel'],
            ['id' => 2, 'activity_id' => 3, 'label' => 'Tak', 'type' => 'select'],
        ]);
        $production->table('registrations')->insert(['activity_id' => 3, 'name' => 'Jan Echt', 'email' => 'jan@echt.be', 'answers' => json_encode([
            ['field_id' => 1, 'label' => 'Telefoon', 'value' => '0470 12 34 56'],
            ['field_id' => 2, 'label' => 'Tak', 'value' => 'Welpen'],
        ])]);
        $production->table('role_members')->insert(['role_id' => 1, 'first_name' => 'An', 'last_name' => 'Peeters', 'photo_path' => 'wie-is-wie/alleen-op-prod.webp']);
        $production->table('sessions')->insert(['id' => 'abc', 'payload' => 'x', 'last_activity' => 1]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('production');
        @unlink($this->productionFile);

        parent::tearDown();
    }

    public function test_copies_production_data_and_anonymises_personal_data(): void
    {
        $this->artisan('db:pull-production', ['--force' => true])->assertSuccessful();

        // Production accounts are never copied: the only user is the local admin, who owns the activities.
        $this->assertSame(1, DB::table('users')->count());
        $admin = DB::table('users')->first();
        $this->assertSame('admin@gouwwaas.be', $admin->email);
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertSame($admin->id, DB::table('activities')->value('user_id'));

        $registration = DB::table('registrations')->first();
        $this->assertNotSame('Jan Echt', $registration->name);
        $this->assertNotSame('jan@echt.be', $registration->email);
        $answers = json_decode($registration->answers, true);
        $this->assertNotSame('0470 12 34 56', $answers[0]['value']);
        $this->assertSame('Welpen', $answers[1]['value']); // choices are kept

        // Production's adjuncten replace the ones the local migration created.
        $this->assertSame(9, DB::table('roles')->count());
        $this->assertSame(1, DB::table('role_members')->count());
        $this->assertSame(0, DB::table('sessions')->count());

        // The photo only exists on production: the page falls back to initials.
        $this->get('/wie-is-wie')->assertOk()->assertSee('AP')->assertDontSee('alleen-op-prod');
    }

    public function test_reads_the_production_credentials_from_env_prod_only(): void
    {
        config(['database.connections.production' => null]);
        DB::purge('production');

        // No .env.prod in the test environment's base path → refuses instead of guessing.
        $this->app->setBasePath(sys_get_temp_dir());
        $this->artisan('db:pull-production', ['--force' => true])->assertFailed();
    }

    public function test_refuses_when_the_local_database_is_production(): void
    {
        config(['database.connections.production' => config('database.connections.sqlite')]);
        DB::purge('production');

        $this->artisan('db:pull-production', ['--force' => true])->assertFailed();
    }

    public function test_refuses_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:pull-production', ['--force' => true])->assertFailed();
    }
}
