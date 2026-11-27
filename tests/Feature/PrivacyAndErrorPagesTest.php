<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class PrivacyAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_statement(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Privacyverklaring')
            ->assertSee('In het kort')
            ->assertSee('gouw-waas-session')   // the cookies we actually set
            ->assertSee('XSRF-TOKEN')
            ->assertSee('Mollie')
            ->assertSee('privacy@scoutsengidsenvlaanderen.be')
            ->assertSee('https://www.gegevensbeschermingsautoriteit.be', false);
    }

    public function test_the_privacy_statement_is_linked_from_the_footer_and_the_registration_form(): void
    {
        $this->get('/')->assertSee('href="'.route('privacy').'"', false);

        $activity = Activity::factory()->create();
        $this->get("/activiteiten/{$activity->slug}")
            ->assertSee('Wat we met je gegevens doen, lees je in onze', false)
            ->assertSee('href="'.route('privacy').'"', false);
    }

    public function test_404(): void
    {
        $this->get('/deze-pagina-bestaat-niet')->assertNotFound()
            ->assertSee('Deze pagina is verdwaald')
            ->assertSee('Naar de homepage')
            ->assertSee('Privacyverklaring');
    }

    public function test_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/organisatie/gebruikers')->assertForbidden()
            ->assertSee('Hier mag je niet binnen')
            ->assertSee('Inloggen');
    }

    public function test_419_and_429_and_503(): void
    {
        Route::get('/_test/419', fn () => abort(419));
        Route::get('/_test/429', fn () => abort(429));
        Route::get('/_test/503', fn () => abort(503));

        $this->get('/_test/419')->assertStatus(419)->assertSee('Deze pagina is verlopen');
        $this->get('/_test/429')->assertStatus(429)->assertSee('Even op adem komen');
        $this->get('/_test/503')->assertStatus(503)->assertSee('Even onderhoud');
    }

    public function test_500_without_debug_info(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/500', fn () => throw new RuntimeException('geheime details'));

        $this->get('/_test/500')->assertStatus(500)
            ->assertSee('Er liep iets mis')
            ->assertSee('gouwbureau@gouwwaas.be')
            ->assertDontSee('geheime details');
    }

    /**
     * A 500 or 503 often happens because the database is down: the error pages must not need it.
     */
    public function test_error_pages_render_without_touching_the_database(): void
    {
        foreach (['403', '404', '419', '429', '500', '503'] as $code) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $html = view("errors.{$code}")->render();

            $this->assertSame([], DB::getQueryLog(), "errors.{$code} runs database queries");
            $this->assertStringContainsString("<p class=\"error-code\">{$code}</p>", $html);
        }
    }
}
