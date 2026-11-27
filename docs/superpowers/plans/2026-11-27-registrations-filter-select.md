# Registrations Filter, Sort, Select Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Organisers filter, sort and select registrations on the overview, and apply Mailen, Aanwezig, Niet aanwezig and Attesten versturen to the selection. The financial summary and Betaling column hide when no registration involves money.

**Architecture:** The server side accepts an optional `ids[]` on three existing actions (the mail page and its send, bulk attendance, certificate send), always scoped to `$activity->registrations()`. A new `public/js/registrations.js` adds filtering, sorting and selection to the rendered table using `data-*` attributes. Without JS, the extra controls stay `hidden`.

**Tech Stack:** Laravel 13 and Blade, plain JavaScript (no build step), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-27-registrations-filter-select-design.md`

## Global Constraints

- Dutch UI copy. English code and comments.
- Download (Excel) stays everyone: no change to `export`.
- No bulk Annuleren.
- Ids that don't belong to the activity are ignored. Query through `$activity->registrations()->whereKey($ids)`.
- Every action keeps `Gate::authorize('viewRegistrations', $activity)`.
- Scripts go in files loaded with `<script src>` (the CSP blocks inline scripts without a nonce). The new script is loaded only on the overview.
- Watch out for the Blade quirk: never put an inline `@php(...)` before an `@php … @endphp` block in the same view. Add new variables to the existing top block.

## Review Focus

1. **A filtered-out row that was selected** must be deselected, so an action never reaches it. This is JS, so check it by hand (Task 4 step).
2. **Ids from another activity** must be ignored by all three actions. Tests in Tasks 2 and 3.
3. **Mail selection with two registrations on one address** gives one e-mail, as without a selection. Test in Task 2.
4. **Selecting unconfirmed people and choosing Aanwezig** marks them, since the organiser chose them. Without ids, only confirmed people are marked, as before. Test in Task 3.
5. **A selection where nobody qualifies for a certificate** gives the error `Geen van de geselecteerde deelnemers kan nog een attest krijgen.` and sends nothing. Test in Task 3.

---

### Task 1: Money rule

**Files:** Modify `app/Models/Activity.php`, `resources/views/activiteiten/inschrijvingen.blade.php` (3 occurrences of `@unless ($activity->isFree() && $activity->registrations->every->isFree())`). Test: `tests/Feature/RevenueTest.php`.

**Interfaces:** Produces `Activity::involvesMoney(): bool` (any loaded registration with `amount_cents > 0`).

- [ ] **Step 1: Failing test** `test_no_summary_or_payment_column_when_nobody_paid_anything`. A paid activity (`price_cents` 600) has one registration with `amount_cents` 0. The overview `assertDontSee('Financieel overzicht')` and `assertDontSee('Betaling')`. The existing tests stay.
- [ ] **Step 2: Run it** with `php artisan test --filter=RevenueTest`. Expected: FAIL.
- [ ] **Step 3: Implement** `involvesMoney()`, and replace the 3 conditions with `@if ($activity->involvesMoney())`.
- [ ] **Step 4: Run** `php artisan test`. Expected: all pass.
- [ ] **Step 5: Commit.** Message: `Hide money parts when no registration involves money`

### Task 2: Mail a selection

**Files:** Modify `app/Http/Controllers/ParticipantMailController.php`, `resources/views/activiteiten/mail-deelnemers.blade.php`. Test: `tests/Feature/ParticipantMailTest.php`.

**Interfaces:** `create` and `store` accept an optional `ids[]` (integers). Produces the private `recipients(Activity $activity, bool $includeUnconfirmed, ?array $ids): Collection`. With ids it uses `$activity->registrations()->whereKey($ids)` with no confirmed filter, deduplicated by address as now.

- [ ] **Step 1: Failing tests** (setup as existing: Jan, Jan's brother on the same address, Piet, Joris expired):
  - `test_mailing_a_selection`: GET `deelnemers-mailen?ids[]={piet}&ids[]={joris}` assertSee `2 geselecteerde deelnemers` and the names Piet and Joris, and assertDontSee `Ook naar wie de online betaling`. POSTing with those ids sends exactly 2 `ParticipantMessage`s (piet, joris), with status `Je bericht is verstuurd naar 2 deelnemers.`
  - `test_selection_on_one_address_mails_once`: ids Jan and his brother give 1 mail.
  - `test_foreign_ids_are_ignored`: an id from another activity plus Piet gives 1 mail, to Piet only.
  - `test_an_empty_selection_after_filtering_foreign_ids`: only foreign ids give the error `Er is niemand om te mailen.`
- [ ] **Step 2: Run** with `php artisan test --filter=ParticipantMailTest`. Expected: FAIL.
- [ ] **Step 3: Implement.** Validate `ids` as `nullable|array|max:1000`, `ids.*` as `integer`. In the view, with ids: the "Aan wie?" card says `Naar <strong>n geselecteerde deelnemers</strong>, elk een eigen e-mail…`, lists the names inside `<details><summary>Wie?</summary>…</details>`, and adds hidden `ids[]` inputs. The include-unconfirmed checkbox is hidden. The confirm dialog's count is the selection count. The back link and Annuleren still go to the overview.
- [ ] **Step 4: Run** `php artisan test`. Expected: all pass.
- [ ] **Step 5: Commit.** Message: `Mail a selection of participants`

### Task 3: Bulk attendance and certificates for a selection

**Files:** Modify `app/Http/Controllers/AttendanceController.php` (`markAll`), `app/Http/Controllers/CertificateController.php` (`send`), `app/Models/Activity.php` (`certificateRecipients`). Tests: `tests/Feature/AttendanceTest.php`, `tests/Feature/CertificateMailTest.php`.

**Interfaces:**
- `POST activiteiten.aanwezigheid.alle` takes optional `ids[]` and `present` (`1` default, or `0`).
  - Without ids: today's behaviour.
  - With ids: set `present_at = now()` where it's null (present=1), or set it to null (present=0), on `$activity->registrations()->whereKey($ids)`, confirmed or not.
  - Status: `{n} deelnemer(s) aangeduid als aanwezig.` or `{n} deelnemer(s) aangeduid als niet aanwezig.` (`deelnemer` for 1, `deelnemers` otherwise; n = rows changed).
- `Activity::certificateRecipients(?array $ids = null): Collection` filters to those ids when given.
- `POST activiteiten.attesten.versturen` takes optional `ids[]`. If ids are given and there are no recipients: error `Geen van de geselecteerde deelnemers kan nog een attest krijgen.`

- [ ] **Step 1: Failing tests**
  - `AttendanceTest::test_selection_marks_exactly_those_including_unconfirmed`: ids Piet and Joris with present=1. Both are present, Jan isn't. Status `2 deelnemers aangeduid als aanwezig.`
  - `AttendanceTest::test_selection_can_mark_absent`: Jan and Piet are present. ids Jan with present=0. Jan is cleared, Piet stays. Status `1 deelnemer aangeduid als niet aanwezig.`
  - `AttendanceTest::test_selection_ignores_foreign_ids`: a foreign registration stays untouched.
  - `CertificateMailTest::test_certificates_for_a_selection_only`: ids Lien gives 1 mail (Lien). Jan stays unsent.
  - `CertificateMailTest::test_selection_without_anyone_eligible`: ids Piet (absent) and Joris (unconfirmed) give the error above and send nothing.
  - `CertificateMailTest::test_certificate_selection_ignores_foreign_ids`: a foreign id plus Jan gives 1 mail.
- [ ] **Step 2: Run** with `php artisan test --filter="AttendanceTest|CertificateMailTest"`. Expected: FAIL.
- [ ] **Step 3: Implement** as in Interfaces. Validate ids as in Task 2.
- [ ] **Step 4: Run** `php artisan test`. Expected: all pass.
- [ ] **Step 5: Commit.** Message: `Attendance and certificates for a selection`

### Task 4: Filter, sort and select in the browser

**Files:** Create `public/js/registrations.js`. Modify `resources/views/activiteiten/inschrijvingen.blade.php`, `public/css/app.css`. Test: `tests/Feature/RegistrationTest.php` (markup only).

**Interfaces:**
- Consumes the routes from Tasks 2 and 3 (`activiteiten.mail` GET with `ids[]`, `activiteiten.aanwezigheid.alle`, `activiteiten.attesten.versturen`).
- Markup contract for the JS:
  - `table[data-registrations]`; `tr[data-row]` with `data-id`, `data-search` (name and e-mail), `data-created` (unix timestamp), `data-present` (0/1), `data-payment` (label);
  - one `data-col-{fieldId}` per answer (value or empty);
  - `th[data-sort="created|name|email|present|payment|col-{id}"]` with `data-type="text|number|date"`;
  - filter controls `[data-filter="search|present|payment|col-{id}"]` with `data-match="contains|equals"`;
  - `[data-filter-count]`, `[data-filter-reset]`, `[data-more-filters]`;
  - selection checkboxes `input[data-select-row]` (value = id) and `input[data-select-all]`;
  - the bar `[data-selection-bar]` with `[data-selection-count]`, containing forms `[data-selection-form]`. JS fills each form's hidden `ids[]` inputs before submit. The Mailen form is a GET form.
  - All of the above except the table and rows render with `hidden`. JS removes `hidden` on init.

- [ ] **Step 1: Failing test** `RegistrationTest::test_overview_has_filter_and_selection_markup`, for an activity with a select field "Tak" (Kapoenen/Welpen) and a text field "Groep" and one registration. Assert:
  - `data-registrations`;
  - `data-filter="search"`;
  - `<option value="Welpen">` inside a `data-filter="col-{takId}"` select;
  - `data-match="contains"` on the Groep filter;
  - `data-select-row`;
  - `data-selection-bar`;
  - `js/registrations.js`.
  Use `assertSee(..., false)`.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Markup.**
  - Filter bar in a `.registrations-filters` card under the toolbar:
    - search box (`placeholder="Zoek op naam of e-mail"`);
    - Aanwezig select (Alle / Aanwezig / Niet aanwezig);
    - Betaling select (only when `involvesMoney()`, with the distinct labels);
    - per question: `select` gives its options (`Activity::parseOptions` / `$field->options`), `checkbox` gives Alle / Ja / Nee, others give a text box;
    - removed-field columns get a text box;
    - with more than 3 questions, the per-question filters go in `<div data-more-filters hidden>` behind a "Meer filters" button;
    - `Filters wissen` and `<span data-filter-count></span>`.
  - Selection column first, with a header checkbox labelled for screen readers "Alles selecteren".
  - Sticky bar: `<span data-selection-count>` plus forms Mailen (GET to `activiteiten.mail`), Aanwezig and Niet aanwezig (POST to `aanwezigheid.alle` with `present` 1/0), and Attesten versturen (POST, only when `hasCertificates()`, `data-confirm` like the toolbar button but "Attesten versturen aan de selectie?").
  - Load `js/registrations.js` with the `?v=filemtime` pattern from `layouts/app.blade.php`, using `@push('scripts')` if the layout has a stack, otherwise at the end of the content section.
- [ ] **Step 4: `registrations.js`.**
  - Normalise text with `toLocaleLowerCase('nl').normalize('NFD').replace(/\p{Diacritic}/gu, '')`.
  - A row is shown when every active filter matches: `contains` is a substring test, `equals` compares exactly; `checkbox` values are `Ja` or empty, so Nee matches empty.
  - Hiding a row unticks its selection checkbox.
  - The count reads `n van m getoond`.
  - Sorting:
    - a click cycles asc → desc → original (the original order is kept in an array at init); set `aria-sort` on the th;
    - `number` uses `parseFloat` with the comma replaced; `date` and `created` compare numerically or by ISO string; text uses `localeCompare(b, 'nl', {sensitivity: 'base', numeric: true})`;
    - empty values always go last.
  - Select-all toggles the visible rows only.
  - The bar shows while ≥1 is selected, with the count `n geselecteerd`. On a selection form's submit, add one hidden `ids[]` per selected id.
- [ ] **Step 5: CSS.** `.registrations-filters` (grid of compact fields that wraps), `.selection-bar` (`position: sticky; bottom: 16px`, a card with a shadow, flex with gap), and a narrow selection column.
- [ ] **Step 6: Run** `php artisan test`. Expected: all pass.
- [ ] **Step 7: Manual check in a browser.**
  - Render the overview with sample data (two questions, 6 registrations) and drive it with headless Chrome.
  - Check: search for "zoë" finds "Zoë"; the Tak filter; sorting; select-all then filtering clears the hidden ones; the bar's count.
  - Screenshot desktop.
- [ ] **Step 8: Commit.** Message: `Filter, sort and select registrations on the overview`

### Task 5: README

- [ ] **Step 1:** Add a short paragraph under "Attendance" covering filters, sorting, selection and its actions, the money rule, and that downloads are always everyone. Run `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`.
- [ ] **Step 2: Commit.** Message: `README: filtering and selecting registrations`
