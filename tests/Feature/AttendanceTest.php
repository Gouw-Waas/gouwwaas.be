<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Activity $activity;

    /** @var array<string, Registration> */
    private array $registrations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->activity = Activity::factory()->create(['price_cents' => 1000]);
        foreach (['Jan' => 'paid', 'Piet' => 'paid', 'Joris' => 'expired'] as $name => $status) {
            $registration = $this->activity->registrations()
                ->create(['name' => $name, 'email' => strtolower($name).'@example.com', 'answers' => [], 'amount_cents' => 1000]);
            $registration->forceFill(['payment_status' => $status])->save();
            $this->registrations[$name] = $registration;
        }
    }

    private function url(Registration $registration): string
    {
        return "/activiteiten/{$this->activity->slug}/inschrijvingen/{$registration->id}/aanwezig";
    }

    public function test_only_the_organiser_or_an_admin_can_mark_attendance(): void
    {
        $jan = $this->registrations['Jan'];

        $this->post($this->url($jan), ['present' => '1'])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post($this->url($jan), ['present' => '1'])->assertForbidden();
        $this->assertNull($jan->fresh()->present_at);

        $this->actingAs($this->activity->user)->post($this->url($jan), ['present' => '1'])->assertRedirect();
        $this->assertNotNull($jan->fresh()->present_at);
    }

    public function test_unticking_clears_attendance(): void
    {
        $jan = $this->registrations['Jan'];
        $jan->forceFill(['present_at' => now()])->save();

        $this->actingAs($this->activity->user)->post($this->url($jan), ['present' => '0'])->assertRedirect();

        $this->assertNull($jan->fresh()->present_at);
    }

    public function test_everyone_present_only_marks_confirmed_registrations(): void
    {
        $this->actingAs($this->activity->user)
            ->post("/activiteiten/{$this->activity->slug}/aanwezig")
            ->assertRedirect()
            ->assertSessionHas('status', '2 deelnemers aangeduid als aanwezig.');

        $this->assertTrue($this->registrations['Jan']->fresh()->isPresent());
        $this->assertTrue($this->registrations['Piet']->fresh()->isPresent());
        $this->assertFalse($this->registrations['Joris']->fresh()->isPresent());
    }

    public function test_everyone_present_keeps_existing_times(): void
    {
        $yesterday = now()->subDay()->startOfSecond();
        $this->registrations['Jan']->forceFill(['present_at' => $yesterday])->save();

        $this->actingAs($this->activity->user)
            ->post("/activiteiten/{$this->activity->slug}/aanwezig")
            ->assertSessionHas('status', '1 deelnemer aangeduid als aanwezig.');

        $this->assertTrue($this->registrations['Jan']->fresh()->present_at->equalTo($yesterday));
    }

    public function test_unconfirmed_registration_can_be_ticked_by_hand(): void
    {
        $joris = $this->registrations['Joris'];

        $this->actingAs($this->activity->user)->post($this->url($joris), ['present' => '1'])->assertRedirect();

        $this->assertTrue($joris->fresh()->isPresent());
    }

    public function test_registration_of_another_activity_is_not_found(): void
    {
        $other = Activity::factory()->create(['user_id' => $this->activity->user_id]);
        $stranger = $other->registrations()->create(['name' => 'Vreemde', 'email' => 'v@example.com', 'answers' => []]);

        $this->actingAs($this->activity->user)->post($this->url($stranger), ['present' => '1'])->assertNotFound();

        $this->assertNull($stranger->fresh()->present_at);
    }

    public function test_overview_shows_attendance_and_export_has_column(): void
    {
        $this->registrations['Jan']->forceFill(['present_at' => now()])->save();

        $this->actingAs($this->activity->user)
            ->get("/activiteiten/{$this->activity->slug}/inschrijvingen")
            ->assertOk()
            ->assertSee('Aanwezig')
            ->assertSee('Iedereen aanwezig')
            ->assertSee('Jan aanwezig');

        $content = $this->actingAs($this->activity->user)
            ->get("/activiteiten/{$this->activity->slug}/inschrijvingen.xlsx")
            ->streamedContent();
        $rows = $this->readXlsx($content);

        $column = array_search('Aanwezig', $rows[0], true);
        $this->assertNotFalse($column);
        $byName = collect(array_slice($rows, 1))->keyBy(fn (array $row) => (string) $row[1]);
        $this->assertSame('Ja', $byName['Jan'][$column]);
        $this->assertSame('', $byName['Piet'][$column] ?? '');
    }
}
