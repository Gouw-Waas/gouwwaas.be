# Attendance and participation certificates (attesten)

Date: 2026-11-27

## Intent

Participants in some Gouw Waas activities (trainings, courses) can claim money back
from their municipality, or count the activity as training, if they can prove they took
part. Today the organiser has to make these certificates by hand. The site should
produce them: once the organiser has confirmed who was there, one click e-mails every
attendee a ready-made, signed and stamped PDF certificate.

Recording attendance is useful on its own (knowing who actually came), so it is a
separate feature that every activity gets. Certificates build on top of it.

## Goals

- Organisers mark attendance per registrant on the registrations overview, during
  the activity or all at once afterwards.
- Activities can have a number of *vormingsuren*. Only activities that have one get
  certificates.
- One button e-mails each attendee a personal PDF certificate.
- The certificate looks official: organisation details, activity details, hours,
  participant name, the Gouwcommissaris's name and the gouw stamp.

## Non-goals

- Municipality-specific forms. We produce our own certificate, and people hand that in.
- QR or self check-in by participants.
- Participants downloading their certificate themselves from their registration page.
- Fiscal certificates (such as childcare tax certificates).
- Keeping certificates beyond the existing 24-month archive. Attendance and
  "certificate sent" are deleted with the registration, as everything else is.

## Anti-goals

- **No-shows receiving proof of attendance.** Nothing is sent automatically. Only
  people the organiser marked present get a certificate.
- **Sending someone the same certificate twice** because the organiser clicked the
  button again.
- **Fragile infrastructure.** It must work on the current shared hosting, which has
  no queue worker, Node or Chrome.

## Constraints

- Laravel 13, PHP 8.4, shared hosting. Mails are sent directly in the request
  (see `ParticipantMailController`, which raises the time limit for large groups).
- The site and certificates are in Dutch.
- Who may manage attendance and send certificates: the same people who may see the
  registrations overview today (the activity's creator and admins, `ActivityPolicy`).

## Approaches considered

1. **dompdf: a Blade view rendered to PDF in plain PHP. Chosen.** It runs on any PHP
   host without setup. Its CSS support is limited (tables and simple positioning, no
   flex or grid), which is enough for a one-page certificate.
2. **Headless Chrome (Browsershot).** It renders best, but it needs Node and Chromium
   on the server, and shared hosting rules that out.
3. **No PDF, just a secret printable page per participant.** It's the cheapest, but
   people would forward links instead of handing in a document, and that defeats the
   purpose.

The user picked 1.

## What was decided

### Attendance (every activity)

- Each registration gets a nullable `present_at` timestamp (null means not marked present).
- The registrations overview (`/activiteiten/{slug}/inschrijvingen`) shows an
  "Aanwezig" checkbox per registrant, unticked by default, on every activity.
- An "Iedereen aanwezig" button marks everyone present at once. Individuals can still
  be unticked afterwards.
- The xlsx export of the registrations gets an "Aanwezig" column.

### Vormingsuren

- New optional field on the activity form: number of vormingsuren (`activities.training_hours`,
  nullable, decimal with one digit, so 7,5 is possible). Empty means no certificates
  for this activity.

### Sending

- An "Attesten versturen" button on the registrations overview, visible only when the
  activity has vormingsuren, has started, **and** at least one confirmed registrant is
  marked present.
- It e-mails each present registrant who hasn't received a certificate yet, with their
  own PDF attached, and records `certificate_sent_at` on the registration.
- The button shows how many mails will go out ("Attesten versturen (3)"). Clicking
  again later, for example after ticking a latecomer, only mails the new ones.
- Unticking someone after their certificate was sent doesn't undo anything. The
  overview shows "attest verstuurd" next to people who got one.
- The organiser can download any single present registrant's certificate from the
  overview, for example to resend it by hand.

### The certificate

One A4 page, in Dutch:

- **Organisation:** the Gouw Waas logo, name ("Gouw Waas – Scouts en Gidsen Vlaanderen")
  and address (Gouwhuis Karree, Apostelstraat 5, 9100 Sint-Niklaas), all taken from the new
  organisation config (below).
- **Activity:** title, location and dates. A single day reads "op 12 december 2026",
  several days read "van … tot …".
- **Participant:** the registration's name.
- **Declaration:** fixed text, roughly "Gouw Waas verklaart dat [naam] heeft
  deelgenomen aan [activiteit] te [locatie] [datum], goed voor [X] vormingsuren."
- **Signature block:** the name(s) of the current Gouwcommissaris(sen) and the stamp image.
  No handwritten signature image: the stamp is enough.

### Organisation details in config

- The address is currently typed out in views (the Karree page on `feature/karree`, the
  placeholder on the activity form). Add an `organisation` block to `config/gouw.php`
  holding name, street, postcode and city, and the logo path (`public/images/logo-600.png`;
  dompdf can't read webp), and use it on the certificate.
- Replace the hard-coded address on the activity form with the config. The Karree page
  isn't on `main` yet, so whichever of the two branches merges second switches that page
  over to the config too.

### Which registrations count

- "Iedereen aanwezig" only ticks **confirmed** registrations (`Registration::isConfirmed()`).
  Unconfirmed ones ("open", payment not finished, failed or expired) can still be ticked by
  hand, for example after the organiser marks them paid.
- Certificates (sending, the count on the button and single downloads) only go to
  registrations that are both present **and** confirmed.
- The send button only appears once the activity has started, so certificates can't be
  sent in advance by accident (sending at the end of the last day still works).

## Calls made by Claude (correct if wrong)

- **Signer:** taken from `/wie-is-wie`. Gouwcommissaris is a fixed bureau role
  (`config/gouw.php`, key `commissaris`), so when the person changes, the certificates
  follow. If there are several members, all of them are named (in their order on
  `/wie-is-wie`). If there are none, sending and downloading are blocked with a clear message.
- **Stamp:** one image (PNG, ideally transparent), uploaded by admins, stored on the
  private `local` disk at a fixed path, and embedded in the PDF. It goes on a small
  admin page that also shows a preview certificate with sample data. If no stamp is
  uploaded, sending and downloading are blocked with a clear message.
- **Participant name:** the registration's `name` field as-is.
- **Sending:** synchronous like the participant mail, with a raised time limit. A
  failure for one person doesn't stop the rest. Those people simply get no
  `certificate_sent_at` and are included the next time someone clicks.
- **Privacy statement:** add a line that attendance is recorded and used for
  certificates.

## Left to the builder

- Exact layout and styling of the certificate, within dompdf's limits.
- Routes, controller split and naming. Follow the existing Dutch URL style.
- Exact Dutch wording of the e-mail and the certificate text, within the gist above.
- PDF file name, for example `attest-{activity-slug}-{name}.pdf`.
- Tests in `tests/Feature`, following the existing activity tests.
- README section, as for the other activity features.
