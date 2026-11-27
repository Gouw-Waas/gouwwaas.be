<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Herfstontmoeting',
            'description' => "Eerste alinea.\n\nTweede alinea.",
            'location' => 'Gouwhuis Karree',
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->addHours(4)->format('Y-m-d\TH:i'),
            'registration_deadline' => now()->addWeeks(3)->format('Y-m-d\TH:i'),
            'price' => '12,50',
            'fields' => [
                ['label' => 'Groep', 'type' => 'text', 'required' => '1'],
                ['label' => 'Tak', 'type' => 'select', 'required' => '1', 'options' => "Kapoenen\nWelpen\nJonggivers"],
                ['label' => 'Ik blijf eten', 'type' => 'checkbox', 'required' => '0'],
            ],
            ...$overrides,
        ];
    }

    public function test_past_activities_are_only_listed_for_admins(): void
    {
        $upcoming = Activity::factory()->create(['title' => 'Komende activiteit']);
        $past = Activity::factory()->past()->create(['title' => 'Voorbije activiteit']);

        $this->get('/activiteiten')->assertOk()->assertSee($upcoming->title)->assertDontSee($past->title)->assertDontSee('Voorbije activiteiten');

        // Not even for the organiser of the past activity, in "Mijn activiteiten".
        $this->actingAs($past->user)->get('/activiteiten')->assertDontSee($past->title);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/activiteiten')
            ->assertSeeInOrder([$upcoming->title, 'Voorbije activiteiten', $past->title])
            ->assertSee('data-confirm="&quot;Voorbije activiteit&quot; archiveren?"', false)
            ->assertSee('De activiteit wordt definitief verwijderd. Er zijn geen inschrijvingen.');

        $past->registrations()->create(['name' => 'Ann', 'email' => 'ann@example.com', 'answers' => []]);
        $past->registrations()->create(['name' => 'Bert', 'email' => 'bert@example.com', 'answers' => []]);
        $this->get('/activiteiten')
            ->assertSee('De activiteit en alle 2 inschrijvingen worden definitief verwijderd. Er wordt niets terugbetaald en niemand krijgt een e-mail.');
    }

    public function test_when_an_activity_is_past(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $endedJustNow = Activity::factory()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subMinute(), 'registration_deadline' => now()->subDay()]);
        $endsSoon = Activity::factory()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->addMinute(), 'registration_deadline' => now()->subDay()]);
        // Without an end: over one day after the start.
        $startedYesterdayNoon = Activity::factory()->create(['starts_at' => now()->subDay()->subMinute(), 'ends_at' => null, 'registration_deadline' => now()->subDays(2)]);
        $startedThisMorning = Activity::factory()->create(['starts_at' => now()->subHours(23), 'ends_at' => null, 'registration_deadline' => now()->subDays(2)]);

        $this->assertTrue($endedJustNow->isPast());
        $this->assertFalse($endsSoon->isPast());
        $this->assertTrue($startedYesterdayNoon->isPast());
        $this->assertFalse($startedThisMorning->isPast());

        $this->assertEqualsCanonicalizing([$endedJustNow->id, $startedYesterdayNoon->id], Activity::past()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$endsSoon->id, $startedThisMorning->id], Activity::upcoming()->pluck('id')->all());
    }

    public function test_admins_archive_a_past_activity_without_refunds_or_mails(): void
    {
        Mail::fake();
        $gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $gateway);

        $past = Activity::factory()->past()->create(['title' => 'Oud weekend', 'price_cents' => 1000]);
        $paid = $past->registrations()->create(['name' => 'Bert', 'email' => 'bert@example.com', 'answers' => [], 'amount_cents' => 1000]);
        $paid->forceFill(['payment_status' => 'paid', 'mollie_payment_id' => 'tr_bert'])->save();
        $past->registrations()->create(['name' => 'Ann', 'email' => 'ann@example.com', 'answers' => [], 'amount_cents' => 1000]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post("/activiteiten/{$past->slug}/archiveren")
            ->assertRedirect('/activiteiten')
            ->assertSessionHas('status', '"Oud weekend" is gearchiveerd: de activiteit en 2 inschrijvingen zijn definitief verwijderd.');

        $this->assertModelMissing($past);
        $this->assertModelMissing($paid);
        $this->assertSame([], $gateway->refunds);
        Mail::assertNothingSent();
    }

    public function test_only_admins_archive_and_only_past_activities(): void
    {
        $past = Activity::factory()->past()->create();
        $upcoming = Activity::factory()->create();

        $this->post("/activiteiten/{$past->slug}/archiveren")->assertRedirect('/login');
        $this->actingAs($past->user)->post("/activiteiten/{$past->slug}/archiveren")->assertForbidden();
        $this->assertModelExists($past);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post("/activiteiten/{$upcoming->slug}/archiveren")
            ->assertSessionHasErrors('archive');
        $this->assertModelExists($upcoming);
    }

    public function test_guests_cannot_create_activities(): void
    {
        $this->get('/activiteiten/nieuw')->assertRedirect('/login');
        $this->post('/activiteiten', $this->payload())->assertRedirect('/login');
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_user_can_create_an_activity_with_form_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/activiteiten', $this->payload())
            ->assertRedirect('/activiteiten/herfstontmoeting');

        $activity = Activity::firstOrFail();
        $this->assertTrue($activity->user->is($user));
        $this->assertSame(1250, $activity->price_cents);
        $this->assertSame(['Groep', 'Tak', 'Ik blijf eten'], $activity->fields->pluck('label')->all());
        $this->assertSame(['Kapoenen', 'Welpen', 'Jonggivers'], $activity->fields[1]->options);

        $this->get('/activiteiten/herfstontmoeting')
            ->assertOk()
            ->assertSee('€ 12,50')
            ->assertSee('<p>Eerste alinea.</p>', false);
    }

    public function test_empty_price_means_free(): void
    {
        $this->actingAs(User::factory()->create())->post('/activiteiten', $this->payload(['price' => '']));

        $this->assertSame('Gratis', Activity::firstOrFail()->priceLabel());
    }

    public function test_vormingsuren_are_optional_and_accept_half_hours(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/activiteiten', $this->payload(['title' => 'Cursus', 'training_hours' => '7,5']));
        $activity = Activity::where('title', 'Cursus')->firstOrFail();
        $this->assertEquals(7.5, $activity->training_hours);
        $this->assertSame('7,5', $activity->trainingHoursLabel());
        $this->assertTrue($activity->hasCertificates());

        $this->actingAs($user)->post('/activiteiten', $this->payload(['title' => 'Dag', 'training_hours' => '8']));
        $this->assertSame('8', Activity::where('title', 'Dag')->firstOrFail()->trainingHoursLabel());

        $this->actingAs($user)->post('/activiteiten', $this->payload(['title' => 'Feest', 'training_hours' => '']));
        $feest = Activity::where('title', 'Feest')->firstOrFail();
        $this->assertNull($feest->training_hours);
        $this->assertNull($feest->trainingHoursLabel());
        $this->assertFalse($feest->hasCertificates());
    }

    public function test_vormingsuren_validation(): void
    {
        foreach (['abc', '0', '7,25'] as $value) {
            $this->actingAs(User::factory()->create())
                ->post('/activiteiten', $this->payload(['training_hours' => $value]))
                ->assertSessionHasErrors(['training_hours' => 'Vul een aantal uren in, bv. 3 of 7,5.']);
        }

        $this->assertSame(0, Activity::count());
    }

    public function test_deadline_must_be_before_the_start_and_dropdowns_need_options(): void
    {
        $this->actingAs(User::factory()->create())->post('/activiteiten', $this->payload([
            'registration_deadline' => now()->addMonths(2)->format('Y-m-d\TH:i'),
            'fields' => [['label' => 'Tak', 'type' => 'select', 'options' => "\n"]],
        ]))->assertSessionHasErrors('registration_deadline');

        $this->actingAs(User::factory()->create())->post('/activiteiten', $this->payload([
            'fields' => [['label' => 'Tak', 'type' => 'select', 'options' => "\n  \n"]],
        ]))->assertSessionHasErrors('fields.0.options');
    }

    public function test_slug_never_collides_with_the_create_page(): void
    {
        $this->actingAs(User::factory()->create())->post('/activiteiten', $this->payload(['title' => 'Nieuw']));

        $this->assertSame('nieuw-2', Activity::firstOrFail()->slug);
    }

    public function test_only_the_owner_or_an_admin_can_manage_an_activity(): void
    {
        $activity = Activity::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/activiteiten/{$activity->slug}/bewerken")->assertForbidden();
        $this->actingAs($stranger)->put("/activiteiten/{$activity->slug}", $this->payload())->assertForbidden();
        $this->actingAs($stranger)->get("/activiteiten/{$activity->slug}/inschrijvingen")->assertForbidden();
        $this->actingAs($stranger)->delete("/activiteiten/{$activity->slug}")->assertForbidden();
        $this->actingAs($stranger)->get("/activiteiten/{$activity->slug}")->assertDontSee('Bewerken');

        $this->actingAs($activity->user)->get("/activiteiten/{$activity->slug}/bewerken")->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get("/activiteiten/{$activity->slug}/inschrijvingen")->assertOk();
    }

    public function test_owner_can_update_fields_without_losing_their_ids(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/activiteiten', $this->payload());
        $activity = Activity::firstOrFail();
        $groep = $activity->fields[0];

        $this->actingAs($user)->put("/activiteiten/{$activity->slug}", $this->payload([
            'title' => 'Nieuwe titel',
            'fields' => [
                ['id' => $groep->id, 'label' => 'Scoutsgroep', 'type' => 'text', 'required' => '1'],
            ],
        ]))->assertRedirect("/activiteiten/{$activity->slug}");

        $activity->refresh();
        $this->assertSame('Nieuwe titel', $activity->title);
        $this->assertSame('herfstontmoeting', $activity->slug, 'slug stays the same so shared links keep working');
        $this->assertSame([$groep->id], $activity->fields->pluck('id')->all());
        $this->assertSame('Scoutsgroep', $activity->fields[0]->label);
    }

    public function test_owner_can_delete_an_activity(): void
    {
        $activity = Activity::factory()->create();

        $this->actingAs($activity->user)->delete("/activiteiten/{$activity->slug}")->assertRedirect('/activiteiten');
        $this->assertModelMissing($activity);
    }
}
