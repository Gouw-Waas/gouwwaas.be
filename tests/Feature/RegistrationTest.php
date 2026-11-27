<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $state */
    private function activityWithFields(array $state = []): Activity
    {
        $activity = Activity::factory()->create($state);
        $activity->syncFields([
            ['label' => 'Groep', 'type' => 'text', 'required' => true],
            ['label' => 'Tak', 'type' => 'select', 'required' => true, 'options' => "Kapoenen\nWelpen"],
            ['label' => 'Akkoord met de afspraken', 'type' => 'checkbox', 'required' => true],
            ['label' => 'Aantal personen', 'type' => 'number', 'required' => false],
        ]);

        return $activity->load('fields');
    }

    /**
     * @param  array<int, string|null>  $values  answers in the order of the activity's fields
     * @return array<int, string|null> field id => answer
     */
    private function answers(Activity $activity, array $values): array
    {
        return collect($activity->fields)->mapWithKeys(fn ($field, $i) => [$field->id => $values[$i] ?? null])->all();
    }

    public function test_anyone_can_register_and_answers_are_stored(): void
    {
        $activity = $this->activityWithFields();

        $this->post("/activiteiten/{$activity->slug}/inschrijven", [
            'name' => 'Jan Peeters',
            'email' => 'jan@example.com',
            'answers' => $this->answers($activity, ['Sint-Jozef', 'Welpen', '1', '3']),
        ])->assertRedirect(); // to the registration's own page, see CheckoutTest

        $registration = $activity->registrations()->firstOrFail();
        $this->assertSame('Jan Peeters', $registration->name);
        $this->assertSame('Welpen', $registration->answerFor($activity->fields[1]->id));
        $this->assertSame('Ja', $registration->answerFor($activity->fields[2]->id));
        $this->assertSame('3', $registration->answerFor($activity->fields[3]->id));
    }

    public function test_required_fields_and_choices_are_validated(): void
    {
        $activity = $this->activityWithFields();
        [$groep, $tak, $akkoord, $aantal] = $activity->fields->pluck('id')->all();

        $this->post("/activiteiten/{$activity->slug}/inschrijven", [
            'name' => '',
            'email' => 'geen-email',
            'answers' => [$groep => '', $tak => 'Pioniers', $aantal => 'veel'],
        ])->assertSessionHasErrors(['name', 'email', "answers.{$groep}", "answers.{$tak}", "answers.{$akkoord}", "answers.{$aantal}"]);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_registration_is_closed_after_the_deadline(): void
    {
        $activity = Activity::factory()->closed()->create();

        $this->get("/activiteiten/{$activity->slug}")->assertSee('afgesloten');

        $this->post("/activiteiten/{$activity->slug}/inschrijven", ['name' => 'Jan', 'email' => 'jan@example.com'])
            ->assertSessionHasErrors('registration');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_owner_sees_registrations_including_answers_to_removed_fields(): void
    {
        $activity = $this->activityWithFields();
        $this->post("/activiteiten/{$activity->slug}/inschrijven", [
            'name' => 'Jan Peeters',
            'email' => 'jan@example.com',
            'answers' => $this->answers($activity, ['Sint-Jozef', 'Welpen', '1']),
        ]);

        // The owner later drops the "Groep" question.
        $activity->syncFields($activity->fields->slice(1)->map(fn ($f) => [
            'id' => $f->id, 'label' => $f->label, 'type' => $f->type, 'required' => $f->required,
            'options' => implode("\n", $f->options ?? []),
        ])->values()->all());

        $this->actingAs($activity->user)->get("/activiteiten/{$activity->slug}/inschrijvingen")
            ->assertOk()
            ->assertSee('Jan Peeters')
            ->assertSee('Welpen')
            ->assertSee('Groep (verwijderd veld)')
            ->assertSee('Sint-Jozef');
    }

    public function test_overview_has_filter_and_selection_markup(): void
    {
        $activity = $this->activityWithFields();
        $this->post("/activiteiten/{$activity->slug}/inschrijven", [
            'name' => 'Zoë', 'email' => 'zoe@example.com',
            'answers' => $this->answers($activity, ['Sint-Jozef', 'Welpen', '1']),
        ]);
        $tak = $activity->fields->firstWhere('label', 'Tak');
        $groep = $activity->fields->firstWhere('label', 'Groep');

        $html = $this->actingAs($activity->user)->get("/activiteiten/{$activity->slug}/inschrijvingen")->assertOk()->getContent();

        $this->assertStringContainsString('data-registrations', $html);
        $this->assertStringContainsString('data-filter="search"', $html);
        $this->assertMatchesRegularExpression('/data-filter="col-'.$tak->id.'"[^>]*>.*?<option value="Welpen">/s', $html);
        $this->assertMatchesRegularExpression('/data-filter="col-'.$groep->id.'"[^>]*data-match="contains"/', $html);
        $this->assertStringContainsString('data-col-'.$tak->id.'="Welpen"', $html);
        $this->assertStringContainsString('data-select-row', $html);
        $this->assertStringContainsString('data-selection-bar', $html);
        $this->assertStringContainsString('js/registrations.js', $html);
    }

    public function test_export_is_an_excel_file_with_answers_as_plain_text(): void
    {
        $activity = $this->activityWithFields();
        $this->post("/activiteiten/{$activity->slug}/inschrijven", [
            'name' => '=HYPERLINK("http://evil")',
            'email' => 'jan@example.com',
            'answers' => $this->answers($activity, ['Sint-Jozef', 'Welpen', '1']),
        ]);

        $response = $this->actingAs($activity->user)->get("/activiteiten/{$activity->slug}/inschrijvingen.xlsx")->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("inschrijvingen-{$activity->slug}.xlsx", $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $rows = $this->readXlsx($content);

        $this->assertSame(['Ingeschreven op', 'Naam', 'E-mailadres', 'Betaling', 'Bedrag', 'Aanwezig', 'Groep', 'Tak', 'Akkoord met de afspraken', 'Aantal personen'], $rows[0]);
        $this->assertInstanceOf(\DateTimeInterface::class, $rows[1][0]); // a real date cell, sortable in Excel
        $this->assertSame(['=HYPERLINK("http://evil")', 'jan@example.com', 'Gratis', '', '', 'Sint-Jozef', 'Welpen', 'Ja'], array_slice($rows[1], 1, 8));

        // Stored as text, never as a formula.
        $this->assertStringNotContainsString('<f>', $this->sheetXml($content));
    }

    private function sheetXml(string $xlsx): string
    {
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $xlsx);
        $zip = new \ZipArchive;
        $zip->open($file);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($file);

        return $xml;
    }

    public function test_others_cannot_export_or_delete_registrations(): void
    {
        $activity = $this->activityWithFields();
        $this->post("/activiteiten/{$activity->slug}/inschrijven", [
            'name' => 'Jan', 'email' => 'jan@example.com',
            'answers' => $this->answers($activity, ['Sint-Jozef', 'Welpen', '1']),
        ]);
        $registration = $activity->registrations()->firstOrFail();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/activiteiten/{$activity->slug}/inschrijvingen.xlsx")->assertForbidden();
        $this->actingAs($stranger)->delete("/activiteiten/{$activity->slug}/inschrijvingen/{$registration->id}")->assertForbidden();

        $this->actingAs($activity->user)->delete("/activiteiten/{$activity->slug}/inschrijvingen/{$registration->id}");
        $this->assertModelMissing($registration);
    }

    public function test_a_registration_of_another_activity_cannot_be_deleted_via_this_one(): void
    {
        $mine = Activity::factory()->create();
        $other = Activity::factory()->create();
        $registration = $other->registrations()->create(['name' => 'X', 'email' => 'x@example.com', 'answers' => []]);

        $this->actingAs($mine->user)->delete("/activiteiten/{$mine->slug}/inschrijvingen/{$registration->id}")->assertNotFound();
        $this->assertModelExists($registration);
    }
}
