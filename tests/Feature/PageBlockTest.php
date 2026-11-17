<?php

namespace Tests\Feature;

use App\Models\PageBlock;
use App\Models\ScoutGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBlockTest extends TestCase
{
    use RefreshDatabase;

    private function block(string $title): PageBlock
    {
        return PageBlock::where('title', $title)->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_the_home_page_shows_the_blocks_from_the_database(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Gouw watte?', 'En wat doen jullie dan?'])
            ->assertSee('<strong>Herfstontmoeting</strong>', false)
            // The placeholder becomes the number of groups on the map.
            ->assertSee('samen '.ScoutGroup::count().' scoutsgroepen')
            ->assertDontSee(PageBlock::GROUP_COUNT)
            ->assertSee('<a href="/districten">Bekijk alle groepen per district</a>', false)
            ->assertDontSee('/blokken/')
            ->assertDontSee('Blok toevoegen');
    }

    public function test_only_admins_can_manage_blocks(): void
    {
        $block = $this->block('Gouw watte?');
        $user = User::factory()->create();

        $this->get("/blokken/{$block->id}/bewerken")->assertRedirect('/login');
        $this->actingAs($user)->get("/blokken/{$block->id}/bewerken")->assertForbidden();
        $this->actingAs($user)->put("/blokken/{$block->id}", ['title' => 'Gehackt', 'body' => 'Gehackt'])->assertForbidden();
        $this->actingAs($user)->get('/blokken/nieuw')->assertForbidden();
        $this->actingAs($user)->post('/blokken', ['title' => 'Gehackt', 'body' => 'Gehackt'])->assertForbidden();
        $this->actingAs($user)->delete("/blokken/{$block->id}")->assertForbidden();

        $this->assertSame(['Gouw watte?', 'En wat doen jullie dan?'], PageBlock::ordered()->pluck('title')->all());
    }

    public function test_admin_edits_a_block(): void
    {
        $admin = $this->admin();
        $block = $this->block('En wat doen jullie dan?');

        $this->actingAs($admin)->get('/')->assertSee("/blokken/{$block->id}/bewerken", false);
        $this->actingAs($admin)->get("/blokken/{$block->id}/bewerken")->assertOk()->assertSee('open kampwerkingen');

        $this->actingAs($admin)
            ->put("/blokken/{$block->id}", [
                'title' => 'Wat doen we?',
                'body' => "Eerste alinea met een [link](https://example.com).\n\n- Vormingen\n- Kampen",
            ])
            ->assertRedirect(route('home')."#blok-{$block->id}")
            ->assertSessionHas('status');

        $this->get('/')
            ->assertSee('Wat doen we?')
            ->assertSee('<a href="https://example.com">link</a>', false)
            ->assertSee('<li>Vormingen</li>', false)
            ->assertDontSee('En wat doen jullie dan?');
    }

    public function test_admin_adds_a_block_at_the_end(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/')->assertSee('Blok toevoegen');
        $this->actingAs($admin)->get('/blokken/nieuw')->assertOk()->assertSee('Nieuw blok');

        $this->actingAs($admin)
            ->post('/blokken', ['title' => 'Herfstontmoeting', 'body' => 'Elk jaar in **oktober**.'])
            ->assertRedirect()
            ->assertSessionHas('status', '"Herfstontmoeting" is toegevoegd.');

        $this->get('/')->assertSeeInOrder(['Gouw watte?', 'En wat doen jullie dan?', '<h2>Herfstontmoeting</h2>', '<strong>oktober</strong>'], false);
        $this->assertSame(3, $this->block('Herfstontmoeting')->position);
    }

    public function test_admin_removes_a_block(): void
    {
        $admin = $this->admin();
        $block = $this->block('Gouw watte?');

        $this->actingAs($admin)->get("/blokken/{$block->id}/bewerken")->assertSee('Blok verwijderen');

        $this->actingAs($admin)
            ->delete("/blokken/{$block->id}")
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', '"Gouw watte?" is verwijderd.');

        $this->assertModelMissing($block);
        // (The toast still names it, so look for the heading.)
        $this->get('/')->assertDontSee('<h2>Gouw watte?</h2>', false)->assertSee('<h2>En wat doen jullie dan?</h2>', false);
    }

    public function test_without_blocks_visitors_see_no_empty_section_and_admins_can_add_one(): void
    {
        PageBlock::query()->delete();

        $this->get('/')->assertOk()->assertDontSee('intro-grid');
        $this->actingAs($this->admin())->get('/')->assertSee('Blok toevoegen');
    }

    public function test_html_and_script_links_are_not_run(): void
    {
        $block = $this->block('Gouw watte?');
        $block->update(['body' => "<script>alert(1)</script>\n\n[klik](javascript:alert(1))"]);

        $html = $block->bodyHtml();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_title_and_text_are_required(): void
    {
        $block = $this->block('Gouw watte?');

        $this->actingAs($this->admin())
            ->put("/blokken/{$block->id}", ['title' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'body']);

        $this->actingAs($this->admin())
            ->post('/blokken', ['title' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'body']);

        $this->assertSame(2, PageBlock::count());
    }
}
