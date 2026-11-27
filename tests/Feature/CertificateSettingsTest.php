<?php

namespace Tests\Feature;

use App\Certificates\Stamp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\SetsUpCertificates;
use Tests\TestCase;

class CertificateSettingsTest extends TestCase
{
    use RefreshDatabase, SetsUpCertificates;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCertificates();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function png(int $kilobytes = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('stempel.png', base64_decode(self::PNG).str_repeat("\0", max(0, $kilobytes * 1024 - 70)));
    }

    public function test_only_admins(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/beheer/attest')->assertForbidden();
        $this->actingAs($user)->put('/beheer/attest', ['stamp' => $this->png()])->assertForbidden();
        $this->actingAs($user)->get('/beheer/attest/voorbeeld.pdf')->assertForbidden();
    }

    public function test_upload_replaces_stamp(): void
    {
        Stamp::delete();

        $this->actingAs($this->admin)->put('/beheer/attest', ['stamp' => $this->png()])
            ->assertRedirect('/beheer/attest')
            ->assertSessionHas('status', 'De stempel is opgeslagen.');

        $this->assertTrue(Stamp::exists());
    }

    public function test_upload_validation(): void
    {
        $message = 'Laad een PNG-afbeelding op van maximaal 2 MB.';

        $this->actingAs($this->admin)->put('/beheer/attest', [])->assertSessionHasErrors(['stamp' => $message]);
        $this->actingAs($this->admin)->put('/beheer/attest', ['stamp' => UploadedFile::fake()->create('stempel.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['stamp' => $message]);
        $this->actingAs($this->admin)->put('/beheer/attest', ['stamp' => $this->png(2100)])->assertSessionHasErrors(['stamp' => $message]);
    }

    public function test_page_shows_signers_and_warns_when_missing(): void
    {
        $this->actingAs($this->admin)->get('/beheer/attest')
            ->assertOk()
            ->assertSee('An Peeters')
            ->assertSee('Bert Claes')
            ->assertSee('Apostelstraat 5')
            ->assertSee('data:image/png;base64,', false);

        Stamp::delete();
        $this->commissaris->members()->get()->each->delete();

        $this->actingAs($this->admin)->get('/beheer/attest')
            ->assertOk()
            ->assertSee('Er is nog geen stempel opgeladen.')
            ->assertSee('Er staat nog geen gouwcommissaris op Wie is wie.');
    }

    public function test_preview_is_a_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get('/beheer/attest/voorbeeld.pdf')->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        Stamp::delete();
        $this->actingAs($this->admin)->from('/beheer/attest')->get('/beheer/attest/voorbeeld.pdf')
            ->assertRedirect('/beheer/attest')
            ->assertSessionHasErrors('certificate');
    }
}
