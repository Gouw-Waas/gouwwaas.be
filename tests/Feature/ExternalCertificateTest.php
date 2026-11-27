<?php

namespace Tests\Feature;

use App\Certificates\CertificateRenderer;
use App\Certificates\Stamp;
use App\Mail\CertificateMail;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Concerns\SetsUpCertificates;
use Tests\TestCase;

class ExternalCertificateTest extends TestCase
{
    use RefreshDatabase, SetsUpCertificates;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpCertificates();
        $this->travelTo(now()->setDate(2026, 11, 27));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return TestResponse<Response>
     */
    private function send(array $overrides = []): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())->post('/organisatie/extern-attest', $overrides + [
            'title' => 'Groepsleidingsweekend',
            'location' => 'Lokeren',
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-22',
            'training_hours' => '7,5',
            'organiser' => 'Scouts Lokeren',
            'participants' => "An Peeters;an@example.com\nbert@example.com,Bert Claes",
        ]);
    }

    public function test_admin_sees_the_tab_others_do_not(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/organisatie/extern-attest')
            ->assertOk()->assertSee('Extern attest')->assertSee('Georganiseerd door');

        $this->actingAs(User::factory()->create())->get('/organisatie/extern-attest')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/organisatie/extern-attest')->assertForbidden();
    }

    public function test_sends_one_mail_per_participant_and_stores_nothing(): void
    {
        $this->send()
            ->assertRedirect('/organisatie/extern-attest')
            ->assertSessionHas('status', '2 attesten verstuurd.')
            ->assertSessionHasNoErrors();

        Mail::assertSent(CertificateMail::class, 2);
        Mail::assertSent(CertificateMail::class, fn (CertificateMail $mail) => $mail->hasTo('an@example.com')
            && $mail->filename === 'attest-groepsleidingsweekend-an-peeters.pdf'
            && str_starts_with($mail->pdf, '%PDF-'));
        Mail::assertSent(CertificateMail::class, fn (CertificateMail $mail) => $mail->hasTo('bert@example.com'));

        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('registrations', 0);
        $this->assertNull(session()->getOldInput('participants'));
    }

    public function test_certificate_names_organiser_dates_and_hours(): void
    {
        $this->send();

        $registration = null;
        Mail::assertSent(CertificateMail::class, function (CertificateMail $mail) use (&$registration) {
            $registration ??= $mail->hasTo('an@example.com') ? $mail->registration : null;

            return true;
        });
        $this->assertInstanceOf(Registration::class, $registration);

        $text = preg_replace('/\s+/', ' ', strip_tags(app(CertificateRenderer::class)->html($registration, 'Scouts Lokeren')));
        foreach (['An Peeters', 'Groepsleidingsweekend, georganiseerd door Scouts Lokeren, te Lokeren', 'van 20 november 2026 tot 22 november 2026', '7,5 vormingsuren'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
    }

    public function test_mail_shows_dates_without_midnight_times(): void
    {
        $this->send(['ends_on' => '']);

        Mail::assertSent(CertificateMail::class, function (CertificateMail $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('20 november 2026', $html);
            $this->assertStringNotContainsString('00:00', $html);

            return true;
        });
    }

    public function test_csv_upload_is_used_and_not_stored(): void
    {
        $this->send([
            'participants' => '',
            'file' => UploadedFile::fake()->createWithContent('lijst.csv', "\u{FEFF}Naam;E-mail\r\nZoë;zoe@example.com\r\n"),
        ])->assertSessionHas('status', '1 attest verstuurd.');

        Mail::assertSent(CertificateMail::class, fn (CertificateMail $mail) => $mail->hasTo('zoe@example.com'));
        $this->assertSame([Stamp::PATH], Storage::disk(Stamp::DISK)->allFiles());
    }

    public function test_invalid_line_sends_nothing_and_keeps_the_form(): void
    {
        $response = $this->send(['participants' => "An;an@example.com\nBert"]);

        $response->assertSessionHasErrors('participants')->assertSessionHasInput('title', 'Groepsleidingsweekend');
        $this->assertStringContainsString('regel 2: Bert', session('errors')->first('participants'));
        Mail::assertNothingSent();
    }

    public function test_empty_list_refused(): void
    {
        $this->send(['participants' => 'Naam;E-mail'])
            ->assertSessionHasErrors(['participants' => 'Vul minstens één deelnemer in.']);
        Mail::assertNothingSent();
    }

    public function test_too_many_refused(): void
    {
        $lines = implode("\n", array_map(fn (int $i) => "P{$i};p{$i}@example.com", range(1, 1001)));

        $this->send(['participants' => $lines])
            ->assertSessionHasErrors(['participants' => 'Maximaal 1000 deelnemers per keer.']);
        Mail::assertNothingSent();
    }

    public function test_activity_fields_and_hours_validated(): void
    {
        $this->send(['title' => '', 'training_hours' => '0', 'ends_on' => '2026-11-19'])
            ->assertSessionHasErrors(['title', 'training_hours' => 'Vul een aantal uren in, bv. 3 of 7,5.', 'ends_on']);
        Mail::assertNothingSent();
    }

    public function test_future_start_refused(): void
    {
        $this->send(['starts_on' => '2026-11-28', 'ends_on' => ''])
            ->assertSessionHasErrors(['certificate' => 'Attesten kun je pas versturen vanaf de start van de activiteit.']);
        Mail::assertNothingSent();
    }

    public function test_missing_stamp_refused(): void
    {
        Stamp::delete();

        $this->send()->assertSessionHasErrors('certificate');
        Mail::assertNothingSent();
    }

    public function test_failed_mail_goes_back_into_the_list(): void
    {
        $this->app->bind(CertificateRenderer::class, fn () => new class extends CertificateRenderer
        {
            public function pdf(Registration $registration, ?string $organiser = null): string
            {
                if ($registration->email === 'bert@example.com') {
                    throw new RuntimeException('Mail server says no to bert@example.com');
                }

                return parent::pdf($registration, $organiser);
            }
        });
        $log = Log::spy();

        $this->send()
            ->assertSessionHas('status', '1 attest verstuurd.')
            ->assertSessionHasInput('participants', 'Bert Claes;bert@example.com')
            ->assertSessionHasInput('title', 'Groepsleidingsweekend');
        $this->assertStringContainsString('Bert Claes', session('errors')->first('participants'));

        Mail::assertSent(CertificateMail::class, 1);
        $log->shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => ! str_contains($message.json_encode($context), 'bert@example.com'));
    }
}
