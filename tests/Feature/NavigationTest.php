<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_and_side_menu_have_the_same_items_and_mark_the_current_page(): void
    {
        $html = $this->get('/districten')->assertOk()
            ->assertSee('data-sidenav-open', false)
            ->assertSee('id="sidenav"', false)
            ->assertSee('js/nav.js', false)
            ->getContent();

        foreach (['Home', 'Districten', 'Wie is wie', 'Activiteiten'] as $label) {
            $this->assertSame(2, substr_count($html, ">{$label}</a>"), "{$label} in the tabs and in the side menu");
        }

        $this->assertSame(2, preg_match_all('/aria-current="page"\s*>Districten</', $html));
        $this->assertSame(0, preg_match_all('/aria-current="page"\s*>Home</', $html));
    }

    public function test_subpages_mark_their_section(): void
    {
        $activity = Activity::factory()->create();

        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Activiteiten</', $this->get("/activiteiten/{$activity->slug}")->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Home</', $this->get('/')->getContent());
    }

    public function test_side_menu_has_the_login_state(): void
    {
        $sidenav = fn () => (string) str($this->get('/')->getContent())->between('id="sidenav"', '</dialog>');

        $this->assertStringContainsString('Inloggen', $sidenav());
        $this->assertStringNotContainsString('Gebruikers', $sidenav());

        $this->actingAs(User::factory()->create(['name' => 'Akela', 'is_admin' => true]));
        $this->assertStringContainsString('Akela', $sidenav());
        $this->assertStringContainsString('Gebruikers', $sidenav());
        $this->assertStringContainsString('Uitloggen', $sidenav());
    }

    public function test_error_pages_have_the_burger_too(): void
    {
        $this->get('/bestaat-niet')->assertNotFound()
            ->assertSee('data-sidenav-open', false)
            ->assertSee('aria-labelledby="sidenav-title"', false)
            ->assertDontSee('Inloggen');
    }
}
