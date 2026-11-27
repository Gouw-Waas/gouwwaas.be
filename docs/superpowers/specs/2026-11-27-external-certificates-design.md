# External certificates (extern attest) — design

## Why
Sometimes a subgroup organises an activity and the gouw is only asked to provide the
participation certificates (attesten). Admins need to send those attests without the
activity or its participants living on the site. **We do not want to keep that data.**

## Goals
- Admin enters activity details + a list of name/e-mail pairs and mails each person the
  same PDF attest + mail as for internal activities.
- Nothing is persisted: no activity, no registrations, no uploaded file.

## Non-goals / anti-goals
- No history, no resending later, no calendar entry, no registrations.
- Anti-goal: storing participant data in any form (DB, disk, logs).

## Approaches
- **Send once (chosen):** a form that builds unsaved `Activity`/`Registration` models in
  memory (like `CertificateRenderer::sampleRegistration()`), renders and mails, then forgets.
  Chosen because the user does not want to keep the data.
- Saved "extern" activity with imported manual registrations: reuses retries/badges/history,
  rejected because it stores participant data.

## Design
- **Where:** new tab *Organisatie → Extern attest*, admins only (same middleware as the
  other Organisatie tabs).
- **Form fields:** title, location, start date, optional end date, vormingsuren
  (same rules as the activity form), optional *Georganiseerd door*, participants as a
  textarea and/or CSV upload.
- **Participants format:** one per line, name and e-mail separated by `;` or `,`, either
  order (the part containing `@` is the e-mail). Blank lines and an optional header line
  are ignored. Duplicate e-mail+name lines are collapsed.
- **Validation:** all lines validated before sending; any invalid line → nothing sent,
  the invalid lines are listed and the input is kept in the form.
- **Confirm:** "N attesten versturen?" before submit (existing `data-confirm` pattern).
- **Sending:** synchronous, like `CertificateController::send` (`set_time_limit` scaled to
  count). Same `CertificateRenderer::ensureAvailable` checks (stamp, signer) minus the
  per-activity checks; same `CertificateMail`.
- **Certificate text:** when *Georganiseerd door* is filled, the declaration reads
  "… heeft deelgenomen aan **{title}**, georganiseerd door {organiser}, te {location}, {date}."
  Internal certificates are unchanged.
- **Failures:** result shows "N attesten verstuurd"; failed lines are put back into the
  participants textarea (with the activity fields kept) so the admin resends only those.
  That's the only duplicate protection, since nothing is stored.

## Left to the builder
- Exact CSV parsing details (BOM, quoting), max number of lines (suggest 1000, matching
  the existing `ids` limit), how the organiser value reaches the view (unsaved attribute
  on the in-memory Activity is fine).
