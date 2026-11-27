# External Certificates (Extern attest) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admins send participation certificates for activities organised by a subgroup, from a pasted/uploaded name+e-mail list, without storing anything.

**Architecture:** A new admin tab *Organisatie → Extern attest* with a form. The controller parses the list (`ParticipantList`), builds unsaved `Activity`/`Registration` models in memory, renders them with the existing `CertificateRenderer` (plus an optional "georganiseerd door" line) and mails them with the existing `CertificateMail`. Failed lines are sent back into the form.

**Tech Stack:** Laravel, Blade, dompdf, PHPUnit feature tests (`php artisan test`).

**Spec:** `docs/superpowers/specs/2026-11-27-external-certificates-design.md`

## Global Constraints

- Nothing about an external activity or its participants is persisted: no DB rows, no stored upload, no participant names/addresses in logs.
- Admins only: routes inside the existing `can:admin` → `prefix('organisatie')` group in `routes/web.php`.
- UI copy is Dutch, in the tone of the existing Organisatie pages.
- Vormingsuren use the same rule and message as `ActivityController` (`regex:/^\d{1,2}([.,][05])?$/`, `not_regex:/^0+([.,]0)?$/`, "Vul een aantal uren in, bv. 3 of 7,5.").
- Max 1000 participants per send.
- Internal certificates must render exactly as before.

## Review Focus

- Excel CSV export: UTF-8 BOM, `;` separator, a header line like `Naam;E-mail`, CRLF line endings → header and BOM ignored, all people parsed (test in Task 1).
- Quoted CSV field with a comma inside (`"Peeters, An",an@example.com`) → name is `Peeters, An` (test in Task 1).
- Start date in the future → refused with "Attesten kun je pas versturen vanaf de start van de activiteit." like internal sends (my call; test in Task 3).
- Neither textarea nor file given / list with only a header → "Vul minstens één deelnemer in." and nothing sent (test in Task 3).
- SMTP failure for one person → others still sent, failed line back in the textarea, log entry without the address (test in Task 3).

---

### Task 1: ParticipantList parser

**Files:**
- Create: `app/Certificates/ParticipantList.php`
- Test: `tests/Feature/ParticipantListTest.php` (plain `Tests\TestCase`, no DB)

**Interfaces:**
- Produces:
  - `ParticipantList::parse(string $text): ParticipantList`
  - `public readonly array $participants` — `list<array{name: string, email: string}>`, input order, exact duplicates (same name + e-mail, case-insensitive e-mail) collapsed
  - `public readonly array $invalid` — `list<array{line: int, text: string}>` (1-based line numbers of the original text)
  - `ParticipantList::line(array{name: string, email: string} $participant): string` — `"Name;email"`, used to put failed people back in the textarea

Parsing rules: strip a leading UTF-8 BOM; split on `\r\n|\r|\n`; skip blank lines; split each line with `str_getcsv` using `;` if the line contains a `;`, else `,`; trim fields and drop empty ones; a line is valid when it has exactly two fields, exactly one of which passes `filter_var(..., FILTER_VALIDATE_EMAIL)`; the other is the name. A line whose fields contain no `@` at all and that is the first non-blank line is a header and skipped silently. Everything else is invalid.

- [ ] **Step 1: Write the failing tests**

```php
public function test_either_order_and_either_separator(): void
{
    $list = ParticipantList::parse("An Peeters;an@example.com\nbert@example.com, Bert Claes");
    $this->assertSame([
        ['name' => 'An Peeters', 'email' => 'an@example.com'],
        ['name' => 'Bert Claes', 'email' => 'bert@example.com'],
    ], $list->participants);
    $this->assertSame([], $list->invalid);
}

public function test_excel_export_with_bom_header_and_crlf(): void
{
    $list = ParticipantList::parse("\u{FEFF}Naam;E-mail\r\nZoë Ölçer;zoe@example.com\r\n\r\n");
    $this->assertSame([['name' => 'Zoë Ölçer', 'email' => 'zoe@example.com']], $list->participants);
    $this->assertSame([], $list->invalid);
}

public function test_quoted_comma_in_name(): void
{
    $list = ParticipantList::parse('"Peeters, An",an@example.com');
    $this->assertSame([['name' => 'Peeters, An', 'email' => 'an@example.com']], $list->participants);
}

public function test_invalid_lines_reported_with_line_numbers(): void
{
    $list = ParticipantList::parse("An;an@example.com\nBert\nCees;not-an-email\nDe;d@x.be;extra\n");
    $this->assertSame([
        ['line' => 2, 'text' => 'Bert'],
        ['line' => 3, 'text' => 'Cees;not-an-email'],
        ['line' => 4, 'text' => 'De;d@x.be;extra'],
    ], $list->invalid);
}

public function test_duplicates_collapsed(): void
{
    $list = ParticipantList::parse("An;an@example.com\nAn;AN@example.com\nLien;an@example.com");
    $this->assertCount(2, $list->participants); // siblings sharing an address both stay
}

public function test_line_round_trips(): void
{
    $p = ['name' => 'Peeters, An', 'email' => 'an@example.com'];
    $this->assertSame([$p], ParticipantList::parse(ParticipantList::line($p))->participants);
}
```

(`line()` must quote the name when it contains `;` or `"` so the round trip holds.)

- [ ] **Step 2: Run** `php artisan test --filter=ParticipantListTest` — Expected: FAIL (class not found)
- [ ] **Step 3: Implement `ParticipantList`** per the rules above.
- [ ] **Step 4: Run** `php artisan test --filter=ParticipantListTest` — Expected: PASS
- [ ] **Step 5: Commit** — `git add app/Certificates/ParticipantList.php tests/Feature/ParticipantListTest.php && git commit -m "Extern attest: parse name/e-mail lists"`

---

### Task 2: "Georganiseerd door" on the certificate

**Files:**
- Modify: `app/Certificates/CertificateRenderer.php` (`pdf`, `html`)
- Modify: `resources/views/attesten/attest.blade.php` (declaration paragraph)
- Test: `tests/Feature/CertificateTest.php`

**Interfaces:**
- Produces: `CertificateRenderer::html(Registration $registration, ?string $organiser = null): string` and `CertificateRenderer::pdf(Registration $registration, ?string $organiser = null): string`. The view gets `$organiser`.

Declaration with an organiser: `… heeft deelgenomen aan <strong>{title}</strong>, georganiseerd door {organiser}, te {location}, {dateText}.` Without one: unchanged.

- [ ] **Step 1: Write the failing tests** in `CertificateTest`:

```php
public function test_organiser_line_only_when_given(): void
{
    $this->assertStringNotContainsString('georganiseerd door', $this->renderer()->html($this->zoe));
    $this->assertStringContainsString(
        'georganiseerd door Scouts Sint-Niklaas, te Gouwhuis Karree',
        preg_replace('/\s+/', ' ', strip_tags($this->renderer()->html($this->zoe, 'Scouts Sint-Niklaas'))),
    );
}
```

- [ ] **Step 2: Run** `php artisan test --filter=CertificateTest` — Expected: the new test FAILs, the others pass.
- [ ] **Step 3: Implement** the optional parameter and the Blade condition.
- [ ] **Step 4: Run** `php artisan test --filter=Certificate` — Expected: PASS (all certificate tests)
- [ ] **Step 5: Commit** — `git commit -am "Attest: optional 'georganiseerd door' line"`

---

### Task 3: Organisatie → Extern attest (page + sending)

**Files:**
- Create: `app/Http/Controllers/Admin/ExternalCertificateController.php`
- Create: `resources/views/organisatie/extern-attest.blade.php`
- Modify: `routes/web.php` (inside the `organisatie` prefix group, after the `attest` routes)
- Modify: `resources/views/organisatie/_tabs.blade.php` (add tab; make the Attest tab not light up on the new routes)
- Modify: `README.md` if it documents the Organisatie tabs (one short paragraph)
- Test: `tests/Feature/ExternalCertificateTest.php` (uses `RefreshDatabase`, `SetsUpCertificates`, `Mail::fake()`)

**Interfaces:**
- Consumes: `ParticipantList::parse/line` (Task 1); `CertificateRenderer::pdf(Registration, ?string $organiser)`, `filename(Registration)`, `problems()` (Task 2); `CertificateMail(Registration $registration, string $pdf, string $filename)`.
- Produces routes:
  - `GET /organisatie/extern-attest` → `edit()` → name `attest.extern`
  - `POST /organisatie/extern-attest` → `send(Request, CertificateRenderer)` → name `attest.extern.versturen`

Tabs: `['Attest', route('attest.instellingen'), ['attest.instellingen*', 'attest.voorbeeld']]` and `['Extern attest', route('attest.extern'), ['attest.extern*']]`; call `request()->routeIs(...$patterns)`.

Form fields (names): `title` (required, max 255), `location` (required, max 255), `starts_on` (required date), `ends_on` (nullable date, `after_or_equal:starts_on`), `training_hours` (required, rule from Global Constraints), `organiser` (nullable, max 255), `participants` (nullable string), `file` (nullable, `file`, `mimes:csv,txt`, max 1024 KB). Show `problems()` as flash errors like `organisatie/attest.blade.php` and disable the submit button while any exist. The form uses `data-confirm="Attesten versturen?"` with `data-confirm-text="Iedereen in de lijst krijgt een attest per e-mail. Er wordt niets bewaard."` and `enctype="multipart/form-data"`.

`send()` flow:
1. Validate fields. Combine textarea + file contents (`$request->file('file')->get()`, never `store()`) with `"\n"` and parse.
2. Errors go to the `participants` key and `withInput()`:
   - any `invalid` → `"Deze regels kloppen niet (naam en e-mailadres per regel): regel 2: Bert; regel 3: …"`, nothing sent;
   - zero participants → `"Vul minstens één deelnemer in."`;
   - more than 1000 → `"Maximaal 1000 deelnemers per keer."`.
3. `problems()` non-empty → error `certificate` with the first problem.
4. `starts_on` in the future (after today) → error `certificate` `"Attesten kun je pas versturen vanaf de start van de activiteit."`.
5. Build `new Activity(['title', 'location', 'starts_at' => starts_on 00:00, 'ends_at' => ends_on 00:00 or null, 'training_hours' => float])`, set `slug = Str::slug(title)`; per participant `new Registration(['name', 'email'])` with `setRelation('activity', $activity)`. Do not call `save()` on either.
6. `set_time_limit(max(120, count * 2))`; for each: `Mail::to($email, $name)->send(new CertificateMail($registration, $renderer->pdf($registration, $organiser), $renderer->filename($registration)))`. On `Throwable`: `Log::warning('Extern attest niet verstuurd.', ['exception' => $e::class])` (no message, it can hold the address) and collect the participant.
7. Redirect to `attest.extern` with status `"1 attest verstuurd."` / `"N attesten verstuurd."`. With failures: also `withInput(all activity fields + 'participants' => failed lines joined by "\n")` and error `participants` `"Niet verstuurd naar: An, Bert. Ze staan nog in de lijst: verstuur opnieuw om enkel hen een attest te sturen."`. Without failures: no input flashed (form empty).

Note: `dateText()` in the renderer uses `isSameDay`, so midnight start/end dates work; the mail view's `$activity->dateLabel()` must not crash on midnight times — check it in Step 4 and adapt only if it shows "00:00".

- [ ] **Step 1: Write the failing tests**

```php
private function post(array $overrides = []): TestResponse
{
    return $this->actingAs(User::factory()->create(['is_admin' => true]))->post('/organisatie/extern-attest', $overrides + [
        'title' => 'Groepsleidingsweekend', 'location' => 'Lokeren', 'starts_on' => '2026-11-20',
        'ends_on' => '2026-11-22', 'training_hours' => '7,5', 'organiser' => 'Scouts Lokeren',
        'participants' => "An Peeters;an@example.com\nbert@example.com,Bert Claes",
    ]);
}

public function test_admin_sees_tab_non_admin_gets_403(): void
// admin GET /organisatie/extern-attest → 200, sees 'Extern attest'; plain user → 403

public function test_sends_one_mail_per_participant_and_stores_nothing(): void
// assertSessionHas('status', '2 attesten verstuurd.'); Mail::assertSent(CertificateMail::class, 2);
// a mail hasTo('an@example.com'), filename 'attest-groepsleidingsweekend-an-peeters.pdf', pdf starts with '%PDF-';
// assertDatabaseCount('activities', 0); assertDatabaseCount('registrations', 0)

public function test_certificate_names_organiser_and_hours(): void
// capture the CertificateMail for An, then app(CertificateRenderer::class)->html($mail->registration, 'Scouts Lokeren')
// contains 'georganiseerd door Scouts Lokeren', 'van 20 november 2026 tot 22 november 2026', '7,5 vormingsuren'

public function test_csv_upload_is_used_and_not_stored(): void
// Storage::fake('local') is already done by SetsUpCertificates; post with participants '' and
// 'file' => UploadedFile::fake()->createWithContent('lijst.csv', "\u{FEFF}Naam;E-mail\r\nZoë;zoe@example.com\r\n")
// → 1 mail; Storage::disk('local')->allFiles() equals [Stamp::PATH]

public function test_invalid_line_sends_nothing(): void
// participants "An;an@example.com\nBert" → assertSessionHasErrors('participants'), error contains 'regel 2: Bert';
// Mail::assertNothingSent(); old input kept

public function test_empty_list_refused(): void
// participants 'Naam;E-mail' → error participants 'Vul minstens één deelnemer in.'

public function test_future_start_refused(): void
// starts_on now()->addDay()->toDateString(), ends_on null → error certificate 'Attesten kun je pas versturen vanaf de start van de activiteit.'

public function test_missing_stamp_refused(): void
// Stamp::delete() → assertSessionHasErrors('certificate'); Mail::assertNothingSent()

public function test_failed_mail_goes_back_into_the_list(): void
// Mail::shouldReceive / a fake mailer that throws for bert@example.com (see how CertificateMailTest
// simulates failure with RuntimeException and reuse that approach);
// status '1 attest verstuurd.'; old('participants') === 'Bert Claes;bert@example.com';
// old('title') === 'Groepsleidingsweekend'; error contains 'Bert Claes'
```

- [ ] **Step 2: Run** `php artisan test --filter=ExternalCertificateTest` — Expected: FAIL (404 route)
- [ ] **Step 3: Implement** routes, tabs, controller, view per the flow above.
- [ ] **Step 4: Run** `php artisan test` — Expected: all PASS. Then open `/organisatie/extern-attest` locally as admin, send to a test address, and check the PDF and mail text.
- [ ] **Step 5: Commit** — `git add app/Http/Controllers/Admin/ExternalCertificateController.php resources/views/organisatie routes/web.php tests/Feature/ExternalCertificateTest.php README.md && git commit -m "Organisatie → Extern attest: send certificates for activities a subgroup organised"` (do not add the unrelated untracked `app/Policies/KarreeReservationPolicy.php` / `public/js/calendar.js`; add files by path).
