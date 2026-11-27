<?php

namespace Tests\Feature;

use App\Certificates\CertificateRenderer;
use App\Mail\CertificateMail;
use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Concerns\SetsUpCertificates;
use Tests\TestCase;

class CertificateMailTest extends TestCase
{
    use RefreshDatabase, SetsUpCertificates;

    private Activity $activity;

    /** @var array<string, Registration> */
    private array $registrations = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpCertificates();

        $this->activity = Activity::factory()->create([
            'title' => 'Animatorcursus',
            'price_cents' => 1000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(8),
            'registration_deadline' => now()->subWeek(),
            'training_hours' => 3,
        ]);

        $add = function (string $name, string $email, string $status, bool $present) {
            $registration = $this->activity->registrations()
                ->create(['name' => $name, 'email' => $email, 'answers' => [], 'amount_cents' => 1000]);
            $registration->forceFill(['payment_status' => $status, 'present_at' => $present ? now() : null])->save();
            $this->registrations[$name] = $registration;
        };

        $add('Jan', 'jan@example.com', 'paid', true);
        $add('Lien', 'jan@example.com', 'paid', true); // Jan's sister, same address
        $add('Piet', 'piet@example.com', 'paid', false);
        $add('Joris', 'joris@example.com', 'expired', true); // present, but never paid
    }

    /** @return TestResponse<Response> */
    private function send(): TestResponse
    {
        return $this->actingAs($this->activity->user)->post("/activiteiten/{$this->activity->slug}/attesten");
    }

    /** @return TestResponse<Response> */
    private function overview(): TestResponse
    {
        return $this->actingAs($this->activity->user)->get("/activiteiten/{$this->activity->slug}/inschrijvingen")->assertOk();
    }

    public function test_certificates_go_to_present_confirmed_participants_once(): void
    {
        $this->send()
            ->assertRedirect("/activiteiten/{$this->activity->slug}/inschrijvingen")
            ->assertSessionHas('status', '2 attesten verstuurd.');

        Mail::assertSent(CertificateMail::class, 2);
        foreach (['Jan', 'Lien'] as $name) {
            Mail::assertSent(CertificateMail::class, fn (CertificateMail $mail) => $mail->hasTo('jan@example.com')
                && $mail->registration->is($this->registrations[$name])
                && $mail->filename === 'attest-'.$this->activity->slug.'-'.strtolower($name).'.pdf'
                && str_starts_with($mail->pdf, '%PDF-')
                && $mail->hasAttachment(Attachment::fromData(fn () => $mail->pdf, $mail->filename)->withMime('application/pdf')));
            $this->assertNotNull($this->registrations[$name]->fresh()->certificate_sent_at);
        }
        $this->assertNull($this->registrations['Joris']->fresh()->certificate_sent_at);

        $this->send()->assertSessionHasErrors(['certificate' => 'Er zijn geen nieuwe attesten om te versturen.']);
        Mail::assertSent(CertificateMail::class, 2);
    }

    public function test_latecomer_gets_only_their_own(): void
    {
        $this->send();
        $this->registrations['Piet']->forceFill(['present_at' => now()])->save();

        $this->send()->assertSessionHas('status', '1 attest verstuurd.');

        Mail::assertSent(CertificateMail::class, 3);
        Mail::assertSent(CertificateMail::class, fn (CertificateMail $mail) => $mail->hasTo('piet@example.com'));
    }

    public function test_unticking_after_sending_keeps_sent_label(): void
    {
        $this->send();
        $this->registrations['Jan']->forceFill(['present_at' => null])->save();

        $this->overview()->assertSee('attest verstuurd');
    }

    public function test_button_shows_count(): void
    {
        $this->overview()->assertSee('Attesten versturen (2)');
    }

    public function test_button_hidden_before_start_without_hours_or_without_attendees(): void
    {
        $this->activity->update(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(8), 'registration_deadline' => now()]);
        $this->overview()->assertDontSee('Attesten versturen');
        $this->send()->assertSessionHasErrors(['certificate' => 'Attesten kun je pas versturen vanaf de start van de activiteit.']);

        $this->activity->update(['starts_at' => now()->subDay(), 'training_hours' => null]);
        $this->overview()->assertDontSee('Attesten versturen');
        $this->send()->assertSessionHasErrors(['certificate' => 'Deze activiteit heeft geen vormingsuren, dus geen attesten.']);

        $this->activity->update(['training_hours' => 3]);
        $this->activity->registrations()->update(['present_at' => null]);
        $this->overview()->assertDontSee('Attesten versturen');
        $this->send()->assertSessionHasErrors(['certificate' => 'Er zijn geen nieuwe attesten om te versturen.']);

        Mail::assertNothingSent();
    }

    public function test_hours_removed_after_sending(): void
    {
        $this->send();
        $this->activity->update(['training_hours' => null]);

        $this->overview()->assertDontSee('Attesten versturen')->assertSee('attest verstuurd');
        $this->actingAs($this->activity->user)
            ->get("/activiteiten/{$this->activity->slug}/inschrijvingen/{$this->registrations['Jan']->id}/attest.pdf")
            ->assertNotFound();
    }

    public function test_one_failing_certificate_does_not_stop_the_rest(): void
    {
        $this->app->bind(CertificateRenderer::class, fn () => new class extends CertificateRenderer
        {
            public function pdf(Registration $registration): string
            {
                if ($registration->name === 'Lien') {
                    throw new RuntimeException('Mail server says no');
                }

                return parent::pdf($registration);
            }
        });

        $this->send()
            ->assertSessionHas('status', '1 attest verstuurd.')
            ->assertSessionHasErrors(['certificate' => 'Niet verstuurd naar: Lien. Probeer later opnieuw: wie al een attest kreeg, krijgt het niet nog eens.']);

        $this->assertNotNull($this->registrations['Jan']->fresh()->certificate_sent_at);
        $this->assertNull($this->registrations['Lien']->fresh()->certificate_sent_at);
    }

    public function test_a_concurrent_send_never_mails_someone_twice(): void
    {
        // A second click while this request is still busy: it claims Lien before this request gets to her.
        $this->app->bind(CertificateRenderer::class, fn () => new class extends CertificateRenderer
        {
            public function pdf(Registration $registration): string
            {
                if ($registration->name === 'Jan') {
                    Registration::where('name', 'Lien')->update(['certificate_sent_at' => now()]);
                }

                return parent::pdf($registration);
            }
        });

        $this->send()->assertSessionHas('status', '1 attest verstuurd.');

        Mail::assertSent(CertificateMail::class, 1);
        Mail::assertNotSent(CertificateMail::class, fn (CertificateMail $mail) => $mail->registration->name === 'Lien');
    }

    public function test_certificates_for_a_selection_only(): void
    {
        $this->actingAs($this->activity->user)
            ->post("/activiteiten/{$this->activity->slug}/attesten", ['ids' => [$this->registrations['Lien']->id]])
            ->assertSessionHas('status', '1 attest verstuurd.');

        Mail::assertSent(CertificateMail::class, 1);
        $this->assertNull($this->registrations['Jan']->fresh()->certificate_sent_at);
    }

    public function test_selection_without_anyone_eligible(): void
    {
        $this->actingAs($this->activity->user)
            ->post("/activiteiten/{$this->activity->slug}/attesten", ['ids' => [$this->registrations['Piet']->id, $this->registrations['Joris']->id]])
            ->assertSessionHasErrors(['certificate' => 'Geen van de geselecteerde deelnemers kan nog een attest krijgen.']);

        Mail::assertNothingSent();
    }

    public function test_certificate_selection_ignores_foreign_ids(): void
    {
        $other = Activity::factory()->create(['user_id' => $this->activity->user_id, 'training_hours' => 2]);
        $stranger = $other->registrations()->create(['name' => 'Vreemde', 'email' => 'v@example.com', 'answers' => []]);
        $stranger->forceFill(['present_at' => now()])->save();

        $this->actingAs($this->activity->user)
            ->post("/activiteiten/{$this->activity->slug}/attesten", ['ids' => [$stranger->id, $this->registrations['Jan']->id]]);

        Mail::assertSent(CertificateMail::class, 1);
        Mail::assertSent(CertificateMail::class, fn (CertificateMail $mail) => $mail->registration->is($this->registrations['Jan']));
    }

    public function test_mail_content(): void
    {
        $jan = $this->registrations['Jan'];
        $html = (new CertificateMail($jan, '%PDF-', 'attest.pdf'))->render();

        $this->assertStringContainsString('Animatorcursus', $html);
        $this->assertStringContainsString('Jan', $html);
        $this->assertStringContainsString('in bijlage', $html);
        $this->assertTrue((new CertificateMail($jan, '%PDF-', 'attest.pdf'))->hasSubject('Deelnameattest: Animatorcursus'));
    }
}
