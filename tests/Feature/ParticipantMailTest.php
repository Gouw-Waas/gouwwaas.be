<?php

namespace Tests\Feature;

use App\Mail\ParticipantMessage;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ParticipantMailTest extends TestCase
{
    use RefreshDatabase;

    private Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->activity = Activity::factory()->create(['price_cents' => 1000, 'title' => 'Gouwweekend']);
        $add = fn (string $name, string $email, ?string $status) => $this->activity->registrations()
            ->create(['name' => $name, 'email' => $email, 'answers' => [], 'amount_cents' => 1000])
            ->forceFill(['payment_status' => $status])->save();

        $add('Jan', 'jan@example.com', 'paid');
        $add('Jan (broer)', 'JAN@example.com ', 'paid'); // same address: one e-mail
        $add('Piet', 'piet@example.com', 'paid');
        $add('Joris', 'joris@example.com', 'expired'); // never finished paying
    }

    public function test_only_the_organiser_or_an_admin_can_mail_participants(): void
    {
        $url = "/activiteiten/{$this->activity->slug}/deelnemers-mailen";

        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->post($url, ['subject' => 'Hoi', 'message' => 'Test'])->assertForbidden();

        $this->actingAs($this->activity->user)->get($url)->assertOk()->assertSee('2 deelnemers');

        Mail::assertNothingSent();
    }

    public function test_the_message_goes_to_each_participant_once_with_replies_to_the_sender(): void
    {
        $organiser = $this->activity->user;

        $this->actingAs($organiser)
            ->post("/activiteiten/{$this->activity->slug}/deelnemers-mailen", [
                'subject' => 'Praktische info',
                'message' => "Dag allemaal,\n\nVergeet je <slaapzak> niet!",
                'send_copy' => '0',
            ])
            ->assertRedirect("/activiteiten/{$this->activity->slug}/inschrijvingen")
            ->assertSessionHas('status', 'Je bericht is verstuurd naar 2 deelnemers.');

        Mail::assertSent(ParticipantMessage::class, 2);
        Mail::assertSent(ParticipantMessage::class, fn (ParticipantMessage $mail) => $mail->hasTo('jan@example.com')
            && $mail->hasReplyTo($organiser->email)
            && $mail->hasSubject('Praktische info'));
        Mail::assertSent(ParticipantMessage::class, fn (ParticipantMessage $mail) => $mail->hasTo('piet@example.com'));
        Mail::assertNotSent(ParticipantMessage::class, fn (ParticipantMessage $mail) => $mail->hasTo('joris@example.com'));

        $html = (new ParticipantMessage($this->activity, $organiser, 'Praktische info', "Dag allemaal,\n\nVergeet je <slaapzak> niet!"))->render();
        $this->assertStringContainsString('&lt;slaapzak&gt;', $html);
        $this->assertStringNotContainsString('<slaapzak>', $html);
    }

    public function test_unconfirmed_registrations_and_a_copy_on_request(): void
    {
        $organiser = $this->activity->user;

        $this->actingAs($organiser)
            ->post("/activiteiten/{$this->activity->slug}/deelnemers-mailen", [
                'subject' => 'Nog plaats',
                'message' => 'Er is nog plaats, rond je betaling af.',
                'include_unconfirmed' => '1',
                'send_copy' => '1',
            ])
            ->assertSessionHas('status', 'Je bericht is verstuurd naar 3 deelnemers.');

        Mail::assertSent(ParticipantMessage::class, fn (ParticipantMessage $mail) => $mail->hasTo('joris@example.com'));
        Mail::assertSent(ParticipantMessage::class, fn (ParticipantMessage $mail) => $mail->hasTo($organiser->email)
            && $mail->hasSubject('[Kopie] Nog plaats'));
        Mail::assertSent(ParticipantMessage::class, 4);
    }

    public function test_subject_and_message_are_required(): void
    {
        $this->actingAs($this->activity->user)
            ->post("/activiteiten/{$this->activity->slug}/deelnemers-mailen", ['subject' => '', 'message' => ''])
            ->assertSessionHasErrors(['subject', 'message']);

        Mail::assertNothingSent();
    }
}
