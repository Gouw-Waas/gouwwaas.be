<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhoIsWhoTest extends TestCase
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

    private function role(string $category, ?string $key = null): Role
    {
        $this->get('/wie-is-wie'); // creates the fixed functions

        return Role::where('category', $category)->when($key, fn ($q) => $q->where('key', $key))->firstOrFail();
    }

    private function photo(): UploadedFile
    {
        // A real 1x1 PNG, so the image rule's mime check passes without GD.
        return UploadedFile::fake()->createWithContent('foto.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
    }

    public function test_page_lists_all_three_levels_and_shows_gezocht_for_empty_functions(): void
    {
        $response = $this->get('/wie-is-wie')->assertOk()
            ->assertSeeInOrder(['Gouwvoorzitter', 'Gouwcommissaris', 'Gouwbeheerder'])
            ->assertSee('Technieken')
            ->assertSee('Districtsploegen')
            ->assertSee('Gezocht!')
            ->assertSee('images/gezocht.png')
            ->assertDontSee('Vacant')
            ->assertDontSee('Bewerken');

        foreach (config('gouw.districts') as $district) {
            $response->assertSee('District '.$district['name']);
        }

        $this->assertSame(3, Role::where('category', Role::BUREAU)->count());
        $this->assertSame(count(config('gouw.districts')), Role::where('category', Role::DISTRICT)->count());
    }

    public function test_functions_without_people_are_listed_at_the_bottom_with_a_call_to_action(): void
    {
        $filled = $this->role(Role::BUREAU, 'voorzitter');
        $filled->members()->create(['first_name' => 'An', 'last_name' => 'Peeters']);
        $vacant = Role::where('key', 'commissaris')->firstOrFail();

        $this->get('/wie-is-wie')->assertOk()
            // Filled functions in their own section, the vacant one only under "Openstaande functies".
            ->assertSeeInOrder(['Het gouwbureau', 'Gouwvoorzitter', 'An Peeters', 'Nog open:', 'Gouwcommissaris', 'Adjuncten', 'Districtsploegen', 'Openstaande functies', 'Gouwcommissaris', 'Gezocht!', 'Zin om mee te helpen?'], false)
            ->assertSee('href="#functie-'.$vacant->id.'"', false)
            ->assertSee('mailto:gouwbureau@gouwwaas.be', false);
    }

    public function test_three_or_more_people_are_shown_side_by_side(): void
    {
        $small = $this->role(Role::BUREAU, 'voorzitter');
        $small->members()->createMany([['first_name' => 'A', 'last_name' => 'A'], ['first_name' => 'B', 'last_name' => 'B']]);
        $big = Role::where('key', 'klaas')->firstOrFail();
        $big->members()->createMany([['first_name' => 'C', 'last_name' => 'C'], ['first_name' => 'D', 'last_name' => 'D'], ['first_name' => 'E', 'last_name' => 'E']]);

        $html = $this->get('/wie-is-wie')->assertOk()->getContent();

        $card = fn (Role $role) => str($html)->after('id="functie-'.$role->id.'"')->before('</article>');
        $this->assertStringContainsString('people-compact', $card($big));
        $this->assertStringNotContainsString('people-compact', $card($small));
    }

    public function test_no_open_functions_section_when_everything_is_filled(): void
    {
        $this->get('/wie-is-wie');
        Role::all()->each(fn (Role $role) => $role->members()->create(['first_name' => 'X', 'last_name' => 'Y']));

        $this->get('/wie-is-wie')->assertOk()
            ->assertDontSee('Openstaande functies')
            ->assertDontSee('Nog open:')
            ->assertDontSee('Gezocht!');
    }

    public function test_fixed_functions_follow_config_renames(): void
    {
        $this->get('/wie-is-wie');
        config(['gouw.bureau.voorzitter' => ['Gouwvoorzitster', 'Iets anders']]);

        $this->get('/wie-is-wie')->assertSee('Gouwvoorzitster')->assertDontSee('Gouwvoorzitter');

        // The description is managed on the website, so it isn't overwritten.
        $this->assertSame('Verantwoordelijk voor de coördinatie binnen de gouw.', Role::where('key', 'voorzitter')->value('description'));
    }

    public function test_only_admins_can_edit(): void
    {
        $role = $this->role(Role::BUREAU, 'voorzitter');

        $this->get("/wie-is-wie/{$role->id}/bewerken")->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get("/wie-is-wie/{$role->id}/bewerken")->assertForbidden();
        $this->actingAs(User::factory()->create())->put("/wie-is-wie/{$role->id}", ['description' => 'x'])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/wie-is-wie/adjuncten/nieuw')->assertForbidden();

        $this->actingAs($this->admin())->get('/wie-is-wie')->assertSee('Bewerken')->assertSee('Adjunct toevoegen');
        $this->get("/wie-is-wie/{$role->id}/bewerken")->assertOk()->assertSee('Gouwvoorzitter bewerken');
    }

    public function test_admin_edits_a_bureau_function_with_people_and_contact_details(): void
    {
        $role = $this->role(Role::BUREAU, 'voorzitter');

        $this->actingAs($this->admin())->put("/wie-is-wie/{$role->id}", [
            'description' => 'Coördineert alles.',
            'email' => 'voorzitter@gouwwaas.be',
            'instagram' => '@gouw.waas',
            'members' => [
                ['first_name' => 'An', 'last_name' => 'Peeters', 'totem' => 'Bever', 'title' => 'Gouwvoorzitter', 'photo' => $this->photo()],
                ['first_name' => 'Bert', 'last_name' => 'Janssens'],
            ],
        ])->assertRedirect(route('wie-is-wie').'#functie-'.$role->id);

        $role->refresh()->load('members');
        $this->assertSame('https://www.instagram.com/gouw.waas/', $role->instagram);
        $this->assertSame(['An', 'Bert'], $role->members->pluck('first_name')->all());
        Storage::disk('public')->assertExists($role->members[0]->photo_path);

        $this->get('/wie-is-wie')
            ->assertSee('Coördineert alles.')
            ->assertSee('An Peeters')
            ->assertSee('Bever')
            ->assertSee('mailto:voorzitter@gouwwaas.be', false)
            ->assertSee('@gouw.waas')
            ->assertSee('BJ') // initials when there is no photo
            ->assertDontSee('Facebook');
    }

    public function test_fixed_functions_cannot_be_renamed_or_deleted(): void
    {
        $bureau = $this->role(Role::BUREAU, 'voorzitter');
        $district = $this->role(Role::DISTRICT, 'klaas');
        $this->actingAs($this->admin());

        $this->put("/wie-is-wie/{$bureau->id}", ['title' => 'Baas'])->assertSessionHasErrors('title');
        $this->assertSame('Gouwvoorzitter', $bureau->fresh()->title);

        $this->delete("/wie-is-wie/{$bureau->id}")->assertForbidden();
        $this->delete("/wie-is-wie/{$district->id}")->assertForbidden();
        $this->assertModelExists($district);
    }

    public function test_admin_creates_renames_and_deletes_an_adjunct(): void
    {
        $this->actingAs($this->admin());

        $this->post('/wie-is-wie', [
            'title' => 'Vorming',
            'members' => [['first_name' => 'Cas', 'last_name' => 'Wouters', 'photo' => $this->photo()]],
        ])->assertRedirect();

        $role = Role::where('title', 'Vorming')->firstOrFail();
        $this->assertSame(Role::ADJUNCT, $role->category);
        $this->assertSame(Role::where('category', Role::ADJUNCT)->max('position'), $role->position);
        $photo = $role->members()->first()->photo_path;

        $this->put("/wie-is-wie/{$role->id}", ['title' => 'Vormingen'])->assertRedirect();
        $this->assertSame('Vormingen', $role->fresh()->title);
        $this->assertSame(0, $role->members()->count()); // no people submitted = Gezocht!
        Storage::disk('public')->assertMissing($photo);

        $this->delete("/wie-is-wie/{$role->id}")->assertRedirect('/wie-is-wie');
        $this->assertModelMissing($role);
    }

    public function test_deleting_an_adjunct_removes_the_photos(): void
    {
        $role = $this->role(Role::ADJUNCT);
        $this->actingAs($this->admin())->put("/wie-is-wie/{$role->id}", [
            'title' => $role->title,
            'members' => [['first_name' => 'Dirk', 'last_name' => 'Maes', 'photo' => $this->photo()]],
        ]);
        $photo = $role->members()->first()->photo_path;

        $this->delete("/wie-is-wie/{$role->id}");

        Storage::disk('public')->assertMissing($photo);
    }

    public function test_photos_work_when_app_url_is_another_host(): void
    {
        // www vs no www in production: the CSP (img-src 'self') blocks images on APP_URL's host.
        Storage::fake('public', ['url' => 'https://gouwwaas.be/storage']);
        Storage::disk('public')->put('wie-is-wie/an.png', 'png');
        $this->role(Role::BUREAU, 'voorzitter')->members()->create([
            'first_name' => 'An', 'last_name' => 'Peeters', 'photo_path' => 'wie-is-wie/an.png', 'position' => 0,
        ]);

        $this->get('https://www.gouwwaas.be/wie-is-wie')
            ->assertSee('src="/storage/wie-is-wie/an.png"', false)
            ->assertDontSee('gouwwaas.be/storage');
    }

    public function test_existing_people_keep_their_photo_unless_removed_or_replaced(): void
    {
        $role = $this->role(Role::DISTRICT, 'lelie');
        $this->actingAs($this->admin())->put("/wie-is-wie/{$role->id}", [
            'members' => [['first_name' => 'Eva', 'last_name' => 'Claes', 'photo' => $this->photo()]],
        ]);
        $member = $role->members()->first();

        // Plain save keeps the photo and the person.
        $this->put("/wie-is-wie/{$role->id}", [
            'members' => [['id' => $member->id, 'first_name' => 'Eva', 'last_name' => 'Claes', 'title' => 'DC']],
        ]);
        $this->assertSame($member->photo_path, $member->fresh()->photo_path);
        $this->assertSame('DC', $member->fresh()->title);

        // Replacing deletes the old file.
        $this->put("/wie-is-wie/{$role->id}", [
            'members' => [['id' => $member->id, 'first_name' => 'Eva', 'last_name' => 'Claes', 'photo' => $this->photo()]],
        ]);
        Storage::disk('public')->assertMissing($member->photo_path);
        $replaced = $member->fresh()->photo_path;
        Storage::disk('public')->assertExists($replaced);

        // Removing clears it.
        $this->put("/wie-is-wie/{$role->id}", [
            'members' => [['id' => $member->id, 'first_name' => 'Eva', 'last_name' => 'Claes', 'remove_photo' => '1']],
        ]);
        $this->assertNull($member->fresh()->photo_path);
        Storage::disk('public')->assertMissing($replaced);
    }

    public function test_validation(): void
    {
        $role = $this->role(Role::BUREAU, 'beheerder');

        $this->actingAs($this->admin())->put("/wie-is-wie/{$role->id}", [
            'email' => 'geen-email',
            'website' => 'javascript:alert(1)',
            'members' => [['first_name' => '', 'last_name' => 'X', 'photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]],
        ])->assertSessionHasErrors(['email', 'website', 'members.0.first_name', 'members.0.photo']);

        $this->post('/wie-is-wie', [])->assertSessionHasErrors('title');
    }
}
