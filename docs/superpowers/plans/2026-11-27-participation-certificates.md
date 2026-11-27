# Attendance and Participation Certificates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Organisers mark who attended an activity. For activities with vormingsuren, one click e-mails each confirmed attendee a stamped PDF certificate (attest).

**Architecture:** Attendance is a `present_at` timestamp on registrations, toggled from the registrations overview. A small `App\Certificates` namespace builds the certificate data (organisation from `config/gouw.php`, signers from the Gouwcommissaris role, a stamp image on the private disk), renders a Blade view to PDF with dompdf, and a controller sends one mail per attendee directly in the request. This is the same pattern as `ParticipantMailController`, because production has no queue worker.

**Tech Stack:** Laravel 13, PHP 8.4, `dompdf/dompdf` ^3.1 (used directly with no Laravel wrapper, so framework version support can't block us), PHPUnit 12 with sqlite in memory.

**Spec:** `docs/superpowers/specs/2026-11-27-participation-certificates-design.md`

**Worktree:** `C:\Users\robin\PhpstormProjects\gouwwaas.be-attesten`, branch `feature/attesten`. Run all commands there.

## Global Constraints

- All user-facing text is Dutch. Code, comments and commit messages are English, as in the rest of the repo.
- It must run on shared hosting: no queue worker, no Node or Chrome. Mails are sent synchronously with `set_time_limit(max(120, $count * 2))`.
- Authorisation for everything on an activity's registrations is `Gate::authorize('viewRegistrations', $activity)`. The stamp page is admin only (`can:admin` group).
- Certificates only go to registrations that are **present and confirmed** (`Registration::isConfirmed()`).
- An activity with `training_hours === null` has no certificates: no send button, no download (404).
- Sending is only possible once the activity has started (`starts_at <= now()`).
- Each registration gets its certificate mail at most once (`certificate_sent_at`).
- Signers are **all** members of the Gouwcommissaris role (`category = bureau`, `key = commissaris`), ordered by `position`, shown with `RoleMember::fullName()`.
- No signature image, only the stamp.
- dompdf can't read webp, so the logo is `public/images/logo-600.png`.
- Inline scripts are blocked by the CSP. Any JS behaviour goes in `public/js/site.js` through a `data-` attribute.
- Run `vendor/bin/pint` and `vendor/bin/phpstan analyse` (larastan) before each commit, and `php artisan test` at the end of each task.

## Review Focus

1. **Siblings with one e-mail address** (a parent registering two children): each registration is its own participant, so each gets its own certificate mail. That's unlike the participant mail, which deduplicates by address. Test in Task 4.
2. **Names with accents** ("Zoë Van Kerckhove-Ölçer") must render in the PDF. Use the DejaVu Sans font that ships with dompdf, never a core font. The download file name must be ASCII-safe (`Str::slug`). Tests in Task 3.
3. **A registration from another activity in the URL** (`/activiteiten/a/inschrijvingen/{id of b}/aanwezig`) must return 404, not toggle. Use scoped route bindings. Test in Task 1.
4. **Hours removed after certificates were sent**: the button disappears and downloads return 404. The "attest verstuurd" labels stay. Test in Task 4.
5. **One mail failing mid-batch**: the others still go out, the failed one keeps `certificate_sent_at = null`, and the organiser sees who failed. Test in Task 4.

---

### Task 1: Attendance

**Files:**
- Create: `database/migrations/2026_11_27_120000_add_present_at_to_registrations.php`
- Create: `app/Http/Controllers/AttendanceController.php`
- Modify: `app/Models/Registration.php` (cast, `isPresent()`)
- Modify: `routes/web.php` (auth group, next to the other `inschrijvingen` routes)
- Modify: `resources/views/activiteiten/inschrijvingen.blade.php` (column and "Iedereen aanwezig" button)
- Modify: `app/Http/Controllers/RegistrationController.php` (`export`: "Aanwezig" column)
- Modify: `public/js/site.js` (`data-autosubmit`)
- Modify: `resources/views/privacy.blade.php` (one sentence)
- Test: `tests/Feature/AttendanceTest.php`

**Interfaces:**
- Produces: column `registrations.present_at` (nullable timestamp, cast `datetime`); `Registration::isPresent(): bool`; routes `activiteiten.aanwezigheid` (`POST /activiteiten/{activity}/inschrijvingen/{registration:id}/aanwezig`, body `present=0|1`) and `activiteiten.aanwezigheid.alle` (`POST /activiteiten/{activity}/aanwezig`).

- [ ] **Step 1: Write the failing tests** in `tests/Feature/AttendanceTest.php` (setup style as in `ParticipantMailTest`: one activity with `price_cents` 1000, registrations `Jan` paid, `Piet` paid, `Joris` expired):
  - `test_only_the_organiser_or_an_admin_can_mark_attendance`: guest is redirected to `/login`, another user gets 403, the organiser's POST with `present=1` gives a redirect back and `Jan->fresh()->present_at` is not null.
  - `test_unticking_clears_attendance`: POST `present=0` makes `present_at` null.
  - `test_everyone_present_only_marks_confirmed_registrations`: POST to `/activiteiten/{slug}/aanwezig` gives Jan and Piet present and Joris not, with session status `2 deelnemers aangeduid als aanwezig.`
  - `test_everyone_present_keeps_existing_times`: a registration already present since yesterday keeps its `present_at`.
  - `test_unconfirmed_registration_can_be_ticked_by_hand`: Joris with `present=1` becomes present.
  - `test_registration_of_another_activity_is_not_found`: 404, and that registration stays untouched.
  - `test_overview_shows_attendance_and_export_has_column`: the overview `assertSee('Aanwezig')` and `assertSee('Iedereen aanwezig')`. The export response is streamed, so use `streamedContent()` with openspout's reader, or assert via the header row like the existing export test in `RegistrationTest` if there is one. Follow whichever style that file uses.

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test --filter=AttendanceTest`
Expected: FAIL (no route, no column).

- [ ] **Step 3: Migration**, adding `$table->timestamp('present_at')->nullable()->after('confirmation_sent_at');` with a matching `down()`.

- [ ] **Step 4: Model.** Add a `'present_at' => 'datetime'` cast, the `@property Carbon|null $present_at` docblock, and `isPresent(): bool`. Leave `present_at` out of `#[Fillable]` and use `forceFill`, as `payment_status` does.

- [ ] **Step 5: Controller and routes.** `AttendanceController::update(Request, Activity, Registration): RedirectResponse` validates `present` as a required boolean and sets `present_at` to `now()` (only if it was null) or null. `AttendanceController::markAll(Activity): RedirectResponse` sets `present_at = now()` on `$activity->registrations()->confirmed()->whereNull('present_at')` and returns `back()->with('status', "{$n} ".($n === 1 ? 'deelnemer' : 'deelnemers').' aangeduid als aanwezig.')`. Both begin with `Gate::authorize('viewRegistrations', $activity)`. Register the routes with `->scopeBindings()` so a registration from another activity gives 404.

- [ ] **Step 6: Overview.** Add an "Aanwezig" column right after "Naam". Each cell holds a small form with a checkbox (`name="present"`, `value="1"`, `data-autosubmit`, labelled for screen readers as "{naam} aanwezig") and a hidden `present=0` before it. Add a "Iedereen aanwezig" button in the `header-actions`, as a POST form. In `public/js/site.js`, add a `change` listener that calls `form.requestSubmit()` for `[data-autosubmit]`.

- [ ] **Step 7: Export.** Add an "Aanwezig" column after "Bedrag", with the value `Ja` or empty.

- [ ] **Step 8: Privacy.** In the section about registrations, add: "Organisatoren kunnen aanduiden wie aanwezig was. Dat gebruiken we om deelnameattesten te maken."

- [ ] **Step 9: Run the tests**

Run: `php artisan test`
Expected: all pass.

- [ ] **Step 10: Commit.** Message: `Attendance on the registrations overview`

---

### Task 2: Vormingsuren on activities

**Files:**
- Create: `database/migrations/2026_11_27_130000_add_training_hours_to_activities.php`
- Modify: `app/Models/Activity.php` (fillable, cast, `trainingHoursLabel()`, `hasCertificates()`)
- Modify: `app/Http/Controllers/ActivityController.php` (`validated()`)
- Modify: `resources/views/activiteiten/form.blade.php` (field after "maximum aantal deelnemers")
- Test: `tests/Feature/ActivityTest.php`

**Interfaces:**
- Produces: `activities.training_hours` (`decimal(4,1)`, nullable, cast `'decimal:1'`); `Activity::hasCertificates(): bool` (`training_hours !== null`); `Activity::trainingHoursLabel(): ?string` (`7,5` / `8`, comma decimal, no trailing `,0`).

- [ ] **Step 1: Failing tests** in `ActivityTest`:
  - `test_vormingsuren_are_optional_and_accept_half_hours`: storing with `training_hours` set to `'7,5'` saves `7.5`, and `trainingHoursLabel()` is `'7,5'`. Storing `'8'` gives the label `'8'`. Empty gives `null` and `hasCertificates()` false.
  - `test_vormingsuren_validation`: `'abc'`, `'0'` and `'7,25'` give an error on `training_hours`, with the message `Vul een aantal uren in, bv. 3 of 7,5.`

- [ ] **Step 2: Run and see them fail**, with `php artisan test --filter=ActivityTest`.

- [ ] **Step 3: Migration and model.** Use `$table->decimal('training_hours', 4, 1)->nullable()->after('capacity');`.

- [ ] **Step 4: Validation.** Add the rule `'training_hours' => ['nullable', 'regex:/^\d{1,2}([.,][05])?$/', 'not_regex:/^0+([.,]0)?$/']`, with attribute name `vormingsuren` and the message above for both rules. Map it as `filled(...) ? (float) str_replace(',', '.', ...) : null`.

- [ ] **Step 5: Form field.** Label `Vormingsuren`, `inputmode="decimal"`, placeholder `bv. 7,5`, and help text: "Alleen nodig als deelnemers een deelnameattest krijgen. Leeg = geen attesten."

- [ ] **Step 6: Run the tests**, with `php artisan test`. All should pass.

- [ ] **Step 7: Commit.** Message: `Vormingsuren on activities`

---

### Task 3: The certificate PDF, organisation config and single download

**Files:**
- Modify: `composer.json` (`composer require dompdf/dompdf:^3.1`)
- Modify: `config/gouw.php` (`organisation` block)
- Modify: `resources/views/activiteiten/form.blade.php` (location placeholder from config) and `resources/views/privacy.blade.php` (address from config)
- Create: `app/Certificates/Stamp.php`
- Create: `app/Certificates/CertificateRenderer.php`
- Create: `app/Certificates/CertificateUnavailable.php` (exception with a Dutch user-facing message)
- Create: `resources/views/attesten/attest.blade.php`
- Create: `app/Http/Controllers/CertificateController.php` (method `download`)
- Modify: `routes/web.php`, `resources/views/activiteiten/inschrijvingen.blade.php` (download link per row)
- Test: `tests/Feature/CertificateTest.php`

**Interfaces:**
- Consumes: `Registration::isPresent()`, `isConfirmed()` (Task 1); `Activity::hasCertificates()`, `trainingHoursLabel()` (Task 2).
- Produces:
  - `config('gouw.organisation')` = `['name' => 'Gouw Waas', 'full_name' => 'Gouw Waas – Scouts en Gidsen Vlaanderen', 'building' => 'Gouwhuis Karree', 'street' => 'Apostelstraat 5', 'city' => '9100 Sint-Niklaas', 'email' => 'gouwbureau@gouwwaas.be', 'logo' => 'images/logo-600.png']` (`logo` is relative to `public/`).
  - `Stamp::DISK = 'local'`, `Stamp::PATH = 'attesten/stempel.png'`; static `exists(): bool`, `store(UploadedFile $file): void`, `delete(): void`, `dataUri(): ?string`.
  - `CertificateRenderer::signers(): Collection<int, string>` (full names of the Gouwcommissaris members, by position).
  - `CertificateRenderer::ensureAvailable(Activity $activity): void` throws `CertificateUnavailable` with one of these messages, in this order: `Deze activiteit heeft geen vormingsuren, dus geen attesten.` / `Er is nog geen stempel opgeladen. Een beheerder kan dat doen via Beheer → Attest.` / `Er staat nog geen gouwcommissaris op Wie is wie.`
  - `CertificateRenderer::pdf(Registration $registration): string` (PDF bytes).
  - `CertificateRenderer::filename(Registration $registration): string` gives `attest-{activity-slug}-{Str::slug(name)}.pdf`.
  - `CertificateRenderer::html(Registration $registration): string` (the rendered Blade view, so tests can assert on content).
  - Route `activiteiten.attest` = `GET /activiteiten/{activity}/inschrijvingen/{registration:id}/attest.pdf` (scoped bindings).

- [ ] **Step 1: Failing tests** in `CertificateTest`. In setup: `Storage::fake('local')`, store a 1×1 PNG as the stamp, create the Gouwcommissaris role with members `An Peeters` (position 1) and `Bert Claes` (position 2), and an activity "Animatorcursus" (`training_hours` 7.5, location "Gouwhuis Karree", 12–13 Dec 2026) with `Zoë Van Kerckhove-Ölçer` (free, present).
  - `test_certificate_contains_organisation_activity_participant_and_signers`: `html()` contains `Gouw Waas – Scouts en Gidsen Vlaanderen`, `Apostelstraat 5`, `9100 Sint-Niklaas`, `Zoë Van Kerckhove-Ölçer`, `Animatorcursus`, `van 12 december 2026 tot 13 december 2026`, `7,5 vormingsuren`, `An Peeters`, `Bert Claes`, and `data:image/png;base64,` (the stamp).
  - `test_single_day_reads_op`: an activity on 12 Dec with no end, or ending the same day, gives `op 12 december 2026`.
  - `test_pdf_is_a_pdf`: `pdf()` starts with `%PDF-`.
  - `test_organiser_downloads_a_certificate`: GET returns 200, `Content-Type: application/pdf`, and a `Content-Disposition` containing `attest-animatorcursus-zoe-van-kerckhove-olcer.pdf`. Another user gets 403.
  - `test_no_certificate_without_hours_or_for_absent_or_unconfirmed`: hours null gives 404; not present gives 404; present but unconfirmed (paid activity, `payment_status` expired, not manual) gives 404.
  - `test_missing_stamp_or_commissaris_redirects_with_message`: with the stamp deleted, you're redirected back with errors containing the stamp message. The same holds for no members on the role, with its message.

- [ ] **Step 2: Run and see them fail**, with `php artisan test --filter=CertificateTest`.

- [ ] **Step 3: Install dompdf**, with `composer require dompdf/dompdf:^3.1`.

- [ ] **Step 4: Config.** Add the `organisation` block, with a comment saying it's printed on certificates and shown on the privacy page. Use it in the privacy address and in the activity form's location placeholder (`bv. {building}, {street}, {city}`). Note for whoever merges `feature/karree`: switch the address on `karree/index.blade.php` over to this config too.

- [ ] **Step 5: `Stamp`.** Follow the style of `App\Support\PublicFile`. `store()` saves the upload as PNG at `PATH`, overwriting. `dataUri()` returns `'data:image/png;base64,'.base64_encode(...)` or null.

- [ ] **Step 6: `CertificateRenderer`.** Load the logo with `public_path(config('gouw.organisation.logo'))` as a data URI. Render the view `attesten.attest` with `activity`, `registration`, `organisation`, `signers`, `stamp`, `logo`, `dateText` and `issuedOn` (`now()` in the format `j F Y`, locale `nl`). For dompdf, use `new Dompdf(new Options(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]))`, `setPaper('A4', 'portrait')`, `loadHtml`, `render`, and `output`. Date text: if `ends_at` is null or on the same calendar day as `starts_at`, use `op {starts_at j F Y}`, otherwise `van {start} tot {end}`, with Carbon `locale('nl')->translatedFormat('j F Y')`.

- [ ] **Step 7: View `attesten/attest.blade.php`.** Use table-based layout only (dompdf), inline `<style>`, and `font-family: 'DejaVu Sans'`. Layout from top to bottom: logo at top left with the organisation name and address next to it; the title `Deelnameattest`; the declaration text (fixed copy):

  > {organisation name} verklaart dat **{naam}** heeft deelgenomen aan **{activiteit}** te {locatie}, {dateText}.
  > Deze activiteit is goed voor **{uren} vormingsuren**.

  Then `Opgemaakt te Sint-Niklaas op {issuedOn}.`, and a signature block with each signer's name followed by `Gouwcommissaris` (or `Gouwcommissarissen` when there are several, with the names one per line) and the stamp image (max 45 mm wide). Escape everything with `{{ }}`.

- [ ] **Step 8: Download route and controller.** `CertificateController::download(Activity, Registration)`: authorise; `abort_unless($activity->hasCertificates() && $registration->isPresent() && $registration->isConfirmed(), 404)`; catch `CertificateUnavailable` and redirect back with errors `['certificate' => $e->getMessage()]`; return `response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="..."'])`. In the overview, add a "Attest" download link in each row's actions, shown only under the same three conditions. Make sure the overview shows `$errors->first('certificate')`, as it does for `cancel`.

- [ ] **Step 9: Run the tests**, with `php artisan test`. All should pass. Also open one generated PDF by hand (`php artisan tinker`, write it to `storage/app/test-attest.pdf`) and check that the accents, logo and stamp look right. Delete the file afterwards.

- [ ] **Step 10: Commit.** Message: `Participation certificate PDF with single download`

---

### Task 4: Sending certificates

**Files:**
- Create: `database/migrations/2026_11_27_140000_add_certificate_sent_at_to_registrations.php`
- Create: `app/Mail/CertificateMail.php`, `resources/views/mail/attest.blade.php` (markdown mail)
- Modify: `app/Http/Controllers/CertificateController.php` (method `send`)
- Modify: `app/Models/Activity.php` (`certificateRecipients()`, `canSendCertificates()`)
- Modify: `app/Models/Registration.php` (cast for `certificate_sent_at`)
- Modify: `routes/web.php`, `resources/views/activiteiten/inschrijvingen.blade.php`
- Test: `tests/Feature/CertificateMailTest.php`

**Interfaces:**
- Consumes: `CertificateRenderer::ensureAvailable`, `pdf`, `filename` (Task 3).
- Produces: `registrations.certificate_sent_at` (nullable timestamp); `Activity::certificateRecipients(): Collection<int, Registration>` (present, confirmed, `certificate_sent_at` null, **no** deduplication by e-mail); `Activity::canSendCertificates(): bool` (`hasCertificates() && starts_at <= now() && certificateRecipients()->isNotEmpty()`); route `activiteiten.attesten.versturen` = `POST /activiteiten/{activity}/attesten`; `CertificateMail(Registration $registration, string $pdf, string $filename)`.

- [ ] **Step 1: Failing tests** in `CertificateMailTest` (`Mail::fake()`, the same stamp and commissaris setup as Task 3, factored into a trait `tests/Feature/Concerns/SetsUpCertificates.php` that both test classes use). Use an activity that started yesterday with `training_hours` 3, and registrations Jan (present), Jan's sister on the same e-mail (present), Piet (absent), Joris (present but unconfirmed).
  - `test_certificates_go_to_present_confirmed_participants_once`: POST gives a redirect to the overview with status `2 attesten verstuurd.` (Jan and his sister). `CertificateMail` is sent twice: two separate mails to the same address, each with one attachment named after its registration (`hasAttachment` / check `attachments` on the mailable). Both get `certificate_sent_at`. A second POST sends nothing more and redirects with errors `Er zijn geen nieuwe attesten om te versturen.`
  - `test_latecomer_gets_only_their_own`: after the first send, tick Piet and POST again. Exactly 1 more mail goes out, to Piet.
  - `test_unticking_after_sending_keeps_sent_label`: untick Jan after sending. The overview still shows `attest verstuurd` for him.
  - `test_button_hidden_before_start_without_hours_or_without_attendees`: the overview doesn't show `Attesten versturen` for an activity starting tomorrow, nor for one without hours, nor for one where nobody is present. A POST in those cases redirects with an error and sends nothing.
  - `test_button_shows_count`: the overview shows `Attesten versturen (2)`.
  - `test_hours_removed_after_sending`: set hours to null after sending. The button is gone, the download returns 404, and `attest verstuurd` is still shown.
  - `test_one_failing_mail_does_not_stop_the_rest`: make the mailer throw for Jan's sister only. Partially mock with `Mail::shouldReceive`, or bind a fake `Mailer` that throws when the recipient name matches. Use whichever is simplest in Laravel 13. Jan gets `certificate_sent_at`, the sister stays null, and the errors contain `Niet verstuurd naar: ` plus her name.
  - `test_mail_content`: the rendered mail contains the activity title, the participant's name and `in bijlage`.

- [ ] **Step 2: Run and see them fail**, with `php artisan test --filter=CertificateMailTest`.

- [ ] **Step 3: Migration and cast.**

- [ ] **Step 4: `CertificateMail`.** Subject: `Deelnameattest: {activity title}`. Attach with `Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')`. Copy, in Dutch: greeting with the name, "In bijlage vind je je deelnameattest voor {activiteit} ({dateLabel}). Je kunt het gebruiken als bewijs van deelname, bijvoorbeeld bij je gemeente.", then sign off from Gouw Waas. Reply-to is `config('gouw.organisation.email')`.

- [ ] **Step 5: `CertificateController::send(Request, Activity)`.** Authorise. If `! canSendCertificates()`, redirect back with the right error (no hours, not started, nobody new). Call `ensureAvailable` and catch `CertificateUnavailable` as in Task 3. Set the time limit as in the Global Constraints. For each recipient, in a try/catch with `report($e)`: render the PDF, send it, then `forceFill(['certificate_sent_at' => now()])->save()`. Redirect to `activiteiten.inschrijvingen` with status `{n} attest(en) verstuurd.` (`1 attest verstuurd.` / `n attesten verstuurd.`), plus errors listing failed names, as in `ParticipantMailController`.

- [ ] **Step 6: Overview.** Show the "Attesten versturen ({count})" button in `header-actions` when `canSendCertificates()`, as a POST form with a `data-confirm` (see `public/js/confirm.js`) reading "{count} attesten versturen?". In the attendance cell, show a small `attest verstuurd` label next to the checkbox when `certificate_sent_at` is set.

- [ ] **Step 7: Run the tests**, with `php artisan test`. All should pass. Then send one for real locally to Mailpit (`composer dev:local`, http://localhost:8025) and open the attachment.

- [ ] **Step 8: Commit.** Message: `Send participation certificates to attendees`

---

### Task 5: Stamp upload page for admins

**Files:**
- Create: `app/Http/Controllers/Admin/CertificateSettingsController.php` (`edit`, `update`, `preview`)
- Create: `resources/views/attesten/instellingen.blade.php`
- Modify: `routes/web.php` (`can:admin` group): `GET /beheer/attest` (`attest.instellingen`), `PUT /beheer/attest` (`attest.instellingen.update`), `GET /beheer/attest/voorbeeld.pdf` (`attest.voorbeeld`)
- Modify: `app/Support/MainMenu.php` or wherever admin links live (follow how "Gebruikers" is linked)
- Test: `tests/Feature/CertificateSettingsTest.php`

**Interfaces:**
- Consumes: `Stamp` (Task 3); `CertificateRenderer`. Add `CertificateRenderer::samplePdf(): string`, which renders an unsaved `Activity` ("Voorbeeldactiviteit", Gouwhuis Karree, today, 3 vormingsuren) and an unsaved `Registration` ("Voornaam Achternaam") without touching the database. Refactor `pdf()` and `html()` internally to accept those models, without changing their public signatures.

- [ ] **Step 1: Failing tests**
  - `test_only_admins`: a normal user gets 403 on all three routes.
  - `test_upload_replaces_stamp`: a PUT with a PNG under 2 MB gives `Stamp::exists()` and status `De stempel is opgeslagen.` Validation: `required|image|mimes:png|max:2048`, with the message `Laad een PNG-afbeelding op van maximaal 2 MB.`
  - `test_page_shows_signers_and_warns_when_missing`: the page shows `An Peeters`. With no members, it shows the commissaris message from Task 3, and the same goes for the stamp message.
  - `test_preview_is_a_pdf`: the preview route returns `%PDF-` once the stamp and commissaris exist, and redirects back with the message otherwise.

- [ ] **Step 2: Run and see them fail.**

- [ ] **Step 3: Implement the controller, view and link.** The page shows the current stamp (inline `<img>` with `Stamp::dataUri()`), the upload form (PNG, transparent background recommended), who signs (with a link to `/wie-is-wie` to change it), the organisation details from config (read-only, with a note that they're changed in `config/gouw.php`), and a "Voorbeeld bekijken" link.

- [ ] **Step 4: Run the tests**, with `php artisan test`. All should pass.

- [ ] **Step 5: Commit.** Message: `Admin page for the certificate stamp`

---

### Task 6: README

**Files:**
- Modify: `README.md` (activities section, after "Financial summary")

- [ ] **Step 1: Document it.** Write three short paragraphs in the README's existing style. **Attendance** covers the checkbox, "Iedereen aanwezig" (confirmed only) and the export column. **Certificates** covers vormingsuren, the send button and its conditions, sending once per registration, single download, the code in `app/Certificates/`, and dompdf. **Stamp** covers `/beheer/attest`, the private disk path, signers from Wie is wie, and the organisation details in `config/gouw.php`. Mention that the Karree page should use the organisation config once both branches are on `main`.

- [ ] **Step 2: Final check**, running `php artisan test`, `vendor/bin/pint --test` and `vendor/bin/phpstan analyse`. All should be clean.

- [ ] **Step 3: Commit.** Message: `README: attendance and participation certificates`
