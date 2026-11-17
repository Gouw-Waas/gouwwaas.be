<?php

namespace Tests\Feature;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * /up is checked after every deploy (see .github/workflows/deploy.yml): a failure rolls back.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_answers_200_when_the_app_and_database_work(): void
    {
        $this->get('/up')->assertOk()->assertSee('Application up');
    }

    public function test_up_checks_the_database(): void
    {
        $this->assertTrue(Event::hasListeners(DiagnosingHealth::class));
    }

    public function test_up_answers_500_without_details_when_a_check_fails(): void
    {
        config(['app.debug' => false]);
        Event::listen(DiagnosingHealth::class, fn () => throw new RuntimeException('SQLSTATE geheim wachtwoord'));

        $this->get('/up')->assertStatus(500)
            ->assertSee('experiencing problems')
            ->assertDontSee('geheim');
    }
}
