# Registrations overview: filter, sort, select and bulk actions

Date: 2026-11-27 · Branch: `feature/attesten` (builds on attendance and certificates)

## Intent

Organisers with a long registrations list want to find people quickly (by name or by
their answers), put the list in a useful order, and act on just a group of people. For
example: mail everyone in the Welpen tak, or mark one group present at once.

## Goals

- Search by name or e-mail. Filter on each question, on Aanwezig and on Betaling.
- Sort by clicking column headers.
- Select rows with checkboxes, including "select all" (of the rows currently shown).
- Apply actions to the selection: Mailen, Aanwezig, Niet aanwezig, Attesten versturen.
- Hide the financial summary and Betaling column when no registration involves money.

## Non-goals

- Download (Excel) stays "everyone", with no filtered or selected export.
- No bulk Annuleren, because every paid cancellation needs its own refund choice.
- Filters and sorting aren't remembered between visits and aren't in the URL.

## Anti-goals

- An action reaching people who aren't selected (or who aren't shown).
- Bulk actions bypassing the server's checks. The server still decides who gets a
  certificate (present, confirmed, not yet sent), and still authorises every request.
- A page that breaks without JavaScript. Without JS it stays what it is today.

## Approaches considered

1. **Plain JavaScript on the rendered table. Chosen.** It matches the existing `site.js`
   and `confirm.js`, needs no build step, filters instantly and keeps the selection while
   filtering.
2. **Server-side filtering through query strings.** Every change reloads the page and
   the selection is lost.
3. **Alpine or Vue.** A new dependency, and the project has no build step.

The user agreed to 1.

## Design

### Money rule

The Betaling column and the "Financieel overzicht" show only when at least one
registration has `amount_cents > 0`. The activity's current price no longer matters. A
registration made while the activity was free stays free.

### Filtering and sorting (client side, `public/js/registrations.js`)

- Each row carries `data-*` values: name, e-mail, registration timestamp, present (0/1),
  payment label, and one value per answer column.
- A filter bar above the table, inside the existing toolbar card or directly under it:
  - **Zoeken:** one text box, matched case- and accent-insensitively against name and e-mail.
  - **Aanwezig:** Alle / Aanwezig / Niet aanwezig.
  - **Betaling:** Alle plus each payment label present in the list. Only shown when the
    Betaling column is.
  - **One filter per question:**
    - `select`: a dropdown of its options;
    - `checkbox`: Alle / Ja / Nee;
    - other types: a text box ("bevat").
    - Columns of removed fields get a text box too.
  - **"Filters wissen"**, plus a count: "12 van 40 getoond".
- **Sorting:** the column headers become buttons. Clicking cycles through ascending,
  descending, and back to the original order, with `aria-sort` set. Numbers and dates
  sort as such (`number` and `date` fields, and "Ingeschreven op"). Text sorts in Dutch
  locale order (`localeCompare('nl')`). Empty values go last.
- On activities with many questions, the per-question filters sit behind a "Meer filters"
  toggle, collapsed by default when there are more than 3 questions.

### Selection

- A checkbox column at the start of the table. The header checkbox selects or deselects
  all rows **currently shown**.
- Rows hidden by a filter are deselected, so an action never reaches someone you can't see.
- While something is selected, a sticky bar shows "4 geselecteerd" with these buttons:
  - **Mailen:** submits the selected ids to the mail page (GET
    `/activiteiten/{slug}/deelnemers-mailen?ids[]=…`). That page shows "Aan: 4
    geselecteerde deelnemers", and the names are listed in a `<details>`. On sending, the
    ids go along as hidden fields. The existing "Ook wie nog niet betaald heeft" option is
    replaced by the selection, since the organiser chose these people deliberately.
    Addresses are still deduplicated.
  - **Aanwezig / Niet aanwezig:** POST to a new bulk endpoint, `/activiteiten/{slug}/aanwezig`
    (the existing route) with an optional `ids[]` and `present=1|0`. Without ids it keeps
    today's behaviour ("Iedereen aanwezig": all confirmed). With ids it sets or clears
    `present_at` on exactly those registrations, including unconfirmed ones, because the
    organiser picked them. Status: "4 deelnemers aangeduid als aanwezig." or "… als niet
    aanwezig."
  - **Attesten versturen:** POST to the existing send route with `ids[]`. The recipients are
    the selected registrations that are also present, confirmed and not sent. Only shown on
    activities with vormingsuren. Status as today. If none of the selection qualifies:
    "Geen van de geselecteerde deelnemers kan nog een attest krijgen."
- The server ignores ids that don't belong to the activity: it always queries
  `$activity->registrations()->whereKey($ids)`.

### Without JavaScript

The filter bar, the selection column and the sticky bar are rendered `hidden`, and only
JS reveals them. Everything else works as it does today.

## Calls made by Claude

- Filters live in the browser only. A reload resets them.
- With a selection, the mail page's "include unconfirmed" checkbox is replaced by the
  selection.
- "Meer filters" collapses when there are more than 3 questions.
- Sorting goes ascending, then descending, then back to the original order.

## Left to the builder

- Exact markup and CSS of the filter bar and the sticky selection bar, in the style of the
  toolbar added earlier.
- Accent-insensitive matching: use `normalize('NFD')` and strip diacritics.
- Tests: the server-side parts in feature tests (money rule, mail page with ids, bulk
  attendance with ids, certificates with ids, foreign ids ignored). The JS isn't covered by
  automated tests here. Check it by hand in a browser screenshot.
