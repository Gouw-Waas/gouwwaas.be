<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Payments\RegistrationCheckout;
use App\Support\ActivityCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private function activity(): Activity
    {
        return Activity::factory()->create([
            'title' => 'Gouwweekend; met eten, drinken & spelletjes',
            'location' => 'Gouwhuis Karree, Sint-Niklaas',
            'description' => "Een weekend vol spel.\n\nNeem je slaapzak mee. ".str_repeat('Héél lang verhaal. ', 20),
            // Summer time in Brussels: UTC+2.
            'starts_at' => Carbon::parse('2027-06-12 14:00', 'Europe/Brussels'),
            'ends_at' => Carbon::parse('2027-06-13 16:30', 'Europe/Brussels'),
        ]);
    }

    public function test_the_activity_can_be_downloaded_as_a_calendar_file(): void
    {
        $activity = $this->activity();

        $response = $this->get("/activiteiten/{$activity->slug}/agenda.ics")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', "attachment; filename=\"{$activity->slug}.ics\"");

        $ics = (string) $response->getContent();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("UID:activity-{$activity->id}@", $ics);
        $this->assertStringContainsString("DTSTART:20270612T120000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20270613T143000Z\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:Gouwweekend\; met eten\, drinken & spelletjes', $ics);
        $this->assertStringContainsString('LOCATION:Gouwhuis Karree\, Sint-Niklaas', $ics);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), "Line too long: {$line}");
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'Folding broke a multi-byte character');
        }

        // Unfolded, the description is intact: paragraphs as \n, and a link to the activity.
        $unfolded = str_replace("\r\n ", '', $ics);
        $this->assertStringContainsString('DESCRIPTION:Een weekend vol spel.\n\nNeem je slaapzak mee. Héél lang', $unfolded);
        $this->assertStringContainsString(route('activiteiten.show', $activity), $unfolded);
    }

    public function test_without_an_end_the_event_lasts_one_day(): void
    {
        $activity = Activity::factory()->create([
            'starts_at' => Carbon::parse('2027-01-10 19:00', 'Europe/Brussels'),
            'ends_at' => null,
        ]);

        $ics = ActivityCalendar::for($activity)->ics();

        $this->assertStringContainsString("DTSTART:20270110T180000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20270111T180000Z\r\n", $ics);
        $this->assertStringContainsString('dates=20270110T180000Z%2F20270111T180000Z', ActivityCalendar::for($activity)->googleUrl());
    }

    public function test_the_confirmation_mail_has_the_calendar_file_and_links(): void
    {
        // The real (array) mail transport, to see the message as it is sent.
        $activity = $this->activity();
        $registration = $activity->registrations()->create(['name' => 'Jan', 'email' => 'jan@example.com', 'answers' => [], 'amount_cents' => 0]);

        app(RegistrationCheckout::class)->sendConfirmation($registration);

        $transport = app('mailer')->getSymfonyTransport();
        if (! $transport instanceof ArrayTransport) {
            $this->fail('Tests should use the array mail transport (phpunit.xml).');
        }
        $email = $transport->messages()->sole()->getOriginalMessage();
        if (! $email instanceof Email) {
            $this->fail('Expected an e-mail message.');
        }
        $attachment = collect($email->getAttachments())->sole();

        $this->assertSame("{$activity->slug}.ics", $attachment->getFilename());
        $this->assertStringStartsWith('text/calendar', $attachment->getContentType());
        $this->assertStringContainsString('BEGIN:VEVENT', $attachment->getBody());

        $html = (string) $email->getHtmlBody();
        $this->assertStringContainsString('Zet in je agenda', $html);
        $this->assertStringContainsString('calendar.google.com', $html);
        $this->assertStringContainsString(e(route('activiteiten.agenda', $activity)), $html);

        $this->get("/inschrijvingen/{$registration->token}")->assertSee('Zet in je agenda');
    }
}
