<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Role;
use App\Models\ScoutGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DistrictMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function logo(string $name = 'logo.png'): UploadedFile
    {
        // A real 1x1 PNG, so the image rule's mime check passes without GD.
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
    }

    public function test_admin_sets_replaces_and_removes_a_group_logo(): void
    {
        $this->actingAs($this->admin());
        $group = ScoutGroup::where('number', 'O3106G')->sole();
        $fields = $this->validGroup(['number' => 'O3106G', 'name' => $group->name]);

        $this->put("/districten/groepen/{$group->id}", $fields + ['logo' => $this->logo()])->assertRedirect();
        $first = $group->fresh()->logo_path;
        Storage::disk('public')->assertExists($first);
        $this->get('/districten')->assertSee('alt="Logo van '.$group->name.'"', false)->assertSee($first);

        // Saving without a new file keeps it; a new file replaces it.
        $this->put("/districten/groepen/{$group->id}", $fields)->assertRedirect();
        $this->assertSame($first, $group->fresh()->logo_path);
        $this->put("/districten/groepen/{$group->id}", $fields + ['logo' => $this->logo()])->assertRedirect();
        $second = $group->fresh()->logo_path;
        Storage::disk('public')->assertMissing($first);

        $this->put("/districten/groepen/{$group->id}", $fields + ['remove_logo' => '1'])->assertRedirect();
        $this->assertNull($group->fresh()->logo_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_a_new_group_can_have_a_logo_and_deleting_it_removes_the_file(): void
    {
        $this->actingAs($this->admin());

        $this->post('/districten/groepen', $this->validGroup(['logo' => $this->logo()]))->assertRedirect();
        $group = ScoutGroup::where('number', 'O3199G')->sole();
        Storage::disk('public')->assertExists($group->logo_path);

        $this->delete("/districten/groepen/{$group->id}");
        Storage::disk('public')->assertMissing($group->logo_path);
    }

    public function test_logos_must_be_jpg_png_or_webp(): void
    {
        $this->actingAs($this->admin());
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->post('/districten/groepen', $this->validGroup(['logo' => $svg]))->assertSessionHasErrors('logo');
        $this->put('/districten/klaas', ['logo' => $svg])->assertSessionHasErrors('logo');
    }

    public function test_admin_sets_and_removes_a_district_logo(): void
    {
        $this->get('/districten/klaas/bewerken')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->put('/districten/klaas', ['logo' => $this->logo()])->assertForbidden();

        $this->actingAs($this->admin());
        $this->get('/districten')->assertSee('Logo wijzigen');
        $this->get('/districten/klaas/bewerken')->assertOk()->assertSee('District Klaas bewerken');
        $this->get('/districten/brussel/bewerken')->assertNotFound();

        $this->put('/districten/klaas', ['logo' => $this->logo()])->assertRedirect(route('districten').'#district-klaas');
        $path = District::where('key', 'klaas')->value('logo_path');
        Storage::disk('public')->assertExists($path);
        $this->get('/districten')->assertSee('alt="Logo van district Klaas"', false);

        $this->put('/districten/klaas', ['remove_logo' => '1'])->assertRedirect();
        $this->assertNull(District::where('key', 'klaas')->value('logo_path'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_missing_logo_file_is_not_shown(): void
    {
        ScoutGroup::where('number', 'O3106G')->update(['logo_path' => 'logos/groepen/alleen-op-prod.png']);

        $this->get('/districten')->assertOk()->assertDontSee('alleen-op-prod');
    }

    public function test_logos_work_when_app_url_is_another_host(): void
    {
        // www vs no www in production: the CSP (img-src 'self') blocks images on APP_URL's host.
        Storage::fake('public', ['url' => 'https://gouwwaas.be/storage']);
        Storage::disk('public')->put('logos/groepen/groep.png', 'png');
        ScoutGroup::where('number', 'O3106G')->update(['logo_path' => 'logos/groepen/groep.png']);

        $this->get('https://www.gouwwaas.be/districten')
            ->assertSee('src="/storage/logos/groepen/groep.png"', false)
            ->assertDontSee('gouwwaas.be/storage');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validGroup(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sint-Test',
            'number' => 'o3199g',
            'type' => 'gemengd',
            'district' => 'klaas',
            'street' => 'Teststraat 1',
            'postal_code' => '9100',
            'city' => 'Sint-Niklaas',
            'latitude' => '51.1650000',
            'longitude' => '4.1400000',
            'website' => 'https://example.be/',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function mapData(string $html): array
    {
        return json_decode((string) str($html)->between('window.districtMap = ', ';</script>'), true);
    }

    public function test_the_groups_from_the_groepenzoeker_are_seeded(): void
    {
        $this->assertSame(35, ScoutGroup::count());
        $this->assertSame(35, ScoutGroup::whereNotNull('latitude')->count());
        $this->assertSame(['durme', 'klaas', 'land-van-aymon', 'lelie'], ScoutGroup::distinct()->orderBy('district')->pluck('district')->all());

        $group = ScoutGroup::where('number', 'O3106G')->sole();
        $this->assertSame('zeescouts', $group->type);
        $this->assertSame('Beeldstraat 2', $group->street);
    }

    public function test_map_page_has_a_panel_per_group_and_district_and_the_map_data(): void
    {
        $team = Role::create(['category' => Role::DISTRICT, 'key' => 'klaas', 'title' => 'District Klaas', 'description' => 'De ploeg van Klaas.']);
        $team->members()->create(['first_name' => 'Dries', 'last_name' => 'Van den Eede', 'title' => 'DC']);

        $response = $this->get('/districten')->assertOk()
            ->assertSee('35 scoutsgroepen')
            ->assertSee('data-panel="district-klaas"', false)
            ->assertSee('data-panel="groep-o3106g"', false)
            ->assertSee('Beeldstraat 2')
            ->assertSee('Zeescouts')
            ->assertSee('Dries Van den Eede')   // districtsploeg in the district panel
            ->assertSee('De ploeg van Klaas.')
            ->assertSee('vendor/leaflet/leaflet.js', false)
            ->assertDontSee('Groep toevoegen');

        $data = $this->mapData($response->getContent());
        $this->assertCount(4, $data['districts']);
        $this->assertCount(35, $data['groups']);
        $this->assertSame(['id', 'name', 'district', 'lat', 'lng', 'akabe'], array_keys($data['groups'][0]));
    }

    public function test_groups_without_location_are_listed_but_not_on_the_map(): void
    {
        ScoutGroup::where('number', 'O3106G')->update(['latitude' => null, 'longitude' => null]);

        $response = $this->get('/districten')->assertOk()
            ->assertSee('data-panel="groep-o3106g"', false)
            ->assertSee('Deze groep staat nog niet op de kaart.');

        $this->assertCount(34, $this->mapData($response->getContent())['groups']);
    }

    public function test_wie_is_wie_and_home_count_groups_from_the_database(): void
    {
        ScoutGroup::where('district', 'klaas')->limit(2)->get()->each->delete();

        $this->get('/')->assertSee('33 scoutsgroepen');
        $this->get('/wie-is-wie')->assertSee('10 groepen bekijken');
    }

    public function test_only_admins_manage_groups(): void
    {
        $group = ScoutGroup::first();

        $this->get('/districten/groepen/nieuw')->assertRedirect('/login');

        $this->actingAs(User::factory()->create());
        $this->get('/districten/groepen/nieuw')->assertForbidden();
        $this->post('/districten/groepen', $this->validGroup())->assertForbidden();
        $this->put("/districten/groepen/{$group->id}", $this->validGroup())->assertForbidden();
        $this->delete("/districten/groepen/{$group->id}")->assertForbidden();

        $this->actingAs($this->admin());
        $this->get('/districten')->assertSee('Groep toevoegen')->assertSee('Bewerken');
        $this->get('/districten/groepen/nieuw?district=durme')->assertOk()->assertSee('<option value="durme" selected', false);
        $this->get("/districten/groepen/{$group->id}/bewerken")->assertOk()->assertSee($group->name);
    }

    public function test_admin_adds_edits_and_removes_a_group(): void
    {
        $this->actingAs($this->admin());

        $this->post('/districten/groepen', $this->validGroup())->assertRedirect(route('districten').'#groep-o3199g');
        $group = ScoutGroup::where('number', 'O3199G')->sole(); // number is normalised to upper case
        $this->assertSame(51.165, $group->latitude);

        $this->put("/districten/groepen/{$group->id}", $this->validGroup([
            'number' => 'O3199G', 'name' => 'Sint-Test Hernoemd', 'type' => 'zeescouts', 'district' => 'durme',
            'latitude' => '', 'longitude' => '', 'website' => '',
        ]))->assertRedirect();

        $group->refresh();
        $this->assertSame(
            ['Sint-Test Hernoemd', 'zeescouts', 'durme', null, null],
            [$group->name, $group->type, $group->district, $group->latitude, $group->website],
        );

        $this->delete("/districten/groepen/{$group->id}")->assertRedirect('/districten');
        $this->assertModelMissing($group);
    }

    public function test_validation(): void
    {
        $this->actingAs($this->admin());
        $existing = ScoutGroup::first();

        $this->post('/districten/groepen', $this->validGroup([
            'number' => $existing->number,
            'type' => 'akela',
            'district' => 'brussel',
            'postal_code' => '91000',
            'latitude' => '48.0',
            'website' => 'javascript:alert(1)',
            'akabe' => 'kapoenen',
        ]))->assertSessionHasErrors(['number', 'type', 'district', 'postal_code', 'latitude', 'website', 'akabe']);

        $this->post('/districten/groepen', $this->validGroup(['number' => 'X12']))->assertSessionHasErrors('number');
        $this->post('/districten/groepen', $this->validGroup(['longitude' => '']))->assertSessionHasErrors('longitude');
    }

    public function test_without_akabe_groups_there_is_no_akabe_legend_or_panel(): void
    {
        $response = $this->get('/districten')->assertOk()->assertDontSee('data-panel="akabe"', false);

        $this->assertSame([null], array_values(array_unique(array_column($this->mapData($response->getContent())['groups'], 'akabe'))));
    }

    public function test_admin_marks_a_group_as_akabe_and_it_shows_on_the_map(): void
    {
        $this->actingAs($this->admin());
        $this->get('/districten/groepen/nieuw')->assertSee('Akabetak');

        $this->post('/districten/groepen', $this->validGroup(['akabe' => 'tak', 'akabe_note' => ' Akabetak De Durpers, 8 tot 25 jaar ']))->assertRedirect();
        $group = ScoutGroup::where('number', 'O3199G')->sole();
        $this->assertSame(['tak', 'Akabetak De Durpers, 8 tot 25 jaar'], [$group->akabe, $group->akabe_note]);

        $response = $this->get('/districten')->assertOk()
            ->assertSee('data-panel="akabe"', false)
            ->assertSee('data-select="akabe"', false)
            ->assertSee('1 groep')
            ->assertSee('Scouts en Gidsen (gemengd) · Akabe')
            ->assertSee('Akabetak De Durpers, 8 tot 25 jaar')
            ->assertSee('badge-akabe', false);

        $akabeById = array_column($this->mapData($response->getContent())['groups'], 'akabe', 'id');
        $this->assertSame('tak', $akabeById['groep-o3199g']);

        // Back to no Akabe: the note goes too.
        $this->put("/districten/groepen/{$group->id}", $this->validGroup(['number' => 'O3199G', 'akabe' => '', 'akabe_note' => 'blijft hangen']))->assertRedirect();
        $this->assertSame([null, null], [$group->fresh()->akabe, $group->fresh()->akabe_note]);
        $this->get('/districten')->assertDontSee('data-panel="akabe"', false);
    }
}
