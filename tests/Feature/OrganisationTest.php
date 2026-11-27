<?php

namespace Tests\Feature;

use App\Certificates\CertificateRenderer;
use App\Models\Organisation;
use App\Models\User;
use App\Support\OrganisationLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SetsUpCertificates;
use Tests\TestCase;

class OrganisationTest extends TestCase
{
    use RefreshDatabase, SetsUpCertificates;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return [
            'name' => 'Gouw Test',
            'building' => 'Testhuis',
            'street' => 'Teststraat 1',
            'city' => '9000 Gent',
            'email' => 'bureau@example.com',
            ...$overrides,
        ];
    }

    public function test_only_admins(): void
    {
        $this->get('/organisatie')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/organisatie')->assertForbidden();
        $this->actingAs(User::factory()->create())->put('/organisatie', $this->details())->assertForbidden();
    }

    public function test_defaults_come_from_config_until_saved(): void
    {
        $this->assertSame('Gouw Waas', Organisation::current()->name);

        $this->actingAs(User::factory()->admin()->create())->get('/organisatie')
            ->assertOk()
            ->assertSee('value="Apostelstraat 5"', false)
            ->assertSeeInOrder(['Gegevens', 'Gebruikers', 'Attest']);
    }

    public function test_admin_saves_details_and_they_are_used_everywhere(): void
    {
        $this->setUpCertificates();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/organisatie', $this->details())
            ->assertRedirect('/organisatie')
            ->assertSessionHas('status', 'De gegevens van de organisatie zijn opgeslagen.');
        $this->assertSame(1, Organisation::count());

        // Saving again updates the same row.
        $this->actingAs($admin)->put('/organisatie', $this->details(['building' => '']));
        $this->assertSame(1, Organisation::count());
        $this->assertNull(Organisation::current()->building);

        $this->get('/')->assertSee('Teststraat 1')->assertSee('mailto:bureau@example.com', false);
        $this->get('/privacy')->assertSee('Gouw Test')->assertSee('9000 Gent');

        $renderer = app(CertificateRenderer::class);
        $html = $renderer->html($renderer->sampleRegistration());
        $this->assertStringContainsString('Gouw Test – Scouts en Gidsen Vlaanderen', $html);
        $this->assertStringContainsString('Teststraat 1', $html);
    }

    public function test_logo_upload_and_reset_to_default(): void
    {
        Storage::fake(OrganisationLogo::DISK);
        $admin = User::factory()->admin()->create();
        $default = OrganisationLogo::dataUri();
        $this->assertNotNull($default);

        $png = UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG));
        $this->actingAs($admin)->put('/organisatie', $this->details(['logo' => $png]))->assertSessionHasNoErrors();
        $this->assertTrue(OrganisationLogo::isUploaded());
        $this->assertSame('data:image/png;base64,'.self::PNG, OrganisationLogo::dataUri());
        $this->actingAs($admin)->get('/organisatie')->assertSee('Standaardlogo terugzetten');

        // Saving without a file keeps the uploaded logo.
        $this->actingAs($admin)->put('/organisatie', $this->details());
        $this->assertTrue(OrganisationLogo::isUploaded());

        $this->actingAs($admin)->put('/organisatie', $this->details(['remove_logo' => '1']));
        $this->assertFalse(OrganisationLogo::isUploaded());
        $this->assertSame($default, OrganisationLogo::dataUri());

        $this->actingAs($admin)
            ->put('/organisatie', $this->details(['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors(['logo' => 'Laad een PNG-afbeelding op van maximaal 2 MB.']);
    }

    public function test_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/organisatie', $this->details(['name' => '', 'street' => '', 'email' => 'geen-adres']))
            ->assertSessionHasErrors(['name', 'street', 'email']);

        $this->assertSame(0, Organisation::count());
    }
}
