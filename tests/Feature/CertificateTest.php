<?php

namespace Tests\Feature;

use App\Certificates\CertificateRenderer;
use App\Certificates\Stamp;
use App\Models\Activity;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SetsUpCertificates;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase, SetsUpCertificates;

    private Activity $activity;

    private Registration $zoe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCertificates();

        $this->activity = Activity::factory()->create([
            'title' => 'Animatorcursus',
            'location' => 'Gouwhuis Karree',
            'starts_at' => Carbon::parse('2026-12-12 09:00'),
            'ends_at' => Carbon::parse('2026-12-13 17:00'),
            'registration_deadline' => Carbon::parse('2026-12-01 00:00'),
            'training_hours' => 7.5,
        ]);
        $this->zoe = $this->activity->registrations()->create(['name' => 'Zoë Van Kerckhove-Ölçer', 'email' => 'zoe@example.com', 'answers' => []]);
        $this->zoe->forceFill(['present_at' => now()])->save();
    }

    private function renderer(): CertificateRenderer
    {
        return app(CertificateRenderer::class);
    }

    private function url(Registration $registration): string
    {
        return "/activiteiten/{$registration->activity->slug}/inschrijvingen/{$registration->id}/attest.pdf";
    }

    public function test_certificate_contains_organisation_activity_participant_and_signers(): void
    {
        $html = $this->renderer()->html($this->zoe);

        foreach ([
            'Gouw Waas – Scouts en Gidsen Vlaanderen', 'Apostelstraat 5', '9100 Sint-Niklaas',
            'Zoë Van Kerckhove-Ölçer', 'Animatorcursus', 'van 12 december 2026 tot 13 december 2026',
            '7,5 vormingsuren', 'An Peeters', 'Bert Claes', 'data:image/png;base64,',
        ] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertLessThan(strpos($html, 'Bert Claes'), strpos($html, 'An Peeters'));
    }

    public function test_single_day_reads_op(): void
    {
        $this->activity->update(['ends_at' => Carbon::parse('2026-12-12 17:00')]);
        $this->assertStringContainsString('op 12 december 2026', $this->renderer()->html($this->zoe->fresh()));

        $this->activity->update(['ends_at' => null]);
        $this->assertStringContainsString('op 12 december 2026', $this->renderer()->html($this->zoe->fresh()));
    }

    public function test_pdf_is_a_pdf(): void
    {
        $this->assertStringStartsWith('%PDF-', $this->renderer()->pdf($this->zoe));
    }

    public function test_organiser_downloads_a_certificate(): void
    {
        $response = $this->actingAs($this->activity->user)->get($this->url($this->zoe))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attest-animatorcursus-zoe-van-kerckhove-olcer.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $this->actingAs(User::factory()->create())->get($this->url($this->zoe))->assertForbidden();
    }

    public function test_no_certificate_without_hours_or_for_absent_or_unconfirmed(): void
    {
        $organiser = $this->activity->user;

        $this->zoe->forceFill(['present_at' => null])->save();
        $this->actingAs($organiser)->get($this->url($this->zoe))->assertNotFound();

        $this->zoe->forceFill(['present_at' => now()])->save();
        $this->activity->update(['training_hours' => null]);
        $this->actingAs($organiser)->get($this->url($this->zoe))->assertNotFound();

        $paid = Activity::factory()->create(['user_id' => $organiser->id, 'price_cents' => 1000, 'training_hours' => 3]);
        $joris = $paid->registrations()->create(['name' => 'Joris', 'email' => 'joris@example.com', 'answers' => [], 'amount_cents' => 1000]);
        $joris->forceFill(['payment_status' => 'expired', 'present_at' => now()])->save();
        $this->actingAs($organiser)->get($this->url($joris))->assertNotFound();
    }

    public function test_missing_stamp_or_commissaris_redirects_with_message(): void
    {
        $organiser = $this->activity->user;

        Stamp::delete();
        $this->actingAs($organiser)->from('/vorige')->get($this->url($this->zoe))
            ->assertRedirect('/vorige')
            ->assertSessionHasErrors(['certificate' => 'Er is nog geen stempel opgeladen. Een beheerder kan dat doen via Beheer → Attest.']);

        Storage::disk(Stamp::DISK)->put(Stamp::PATH, base64_decode(self::PNG));
        $this->commissaris->members()->get()->each->delete();
        $this->actingAs($organiser)->from('/vorige')->get($this->url($this->zoe))
            ->assertSessionHasErrors(['certificate' => 'Er staat nog geen gouwcommissaris op Wie is wie.']);
    }
}
