<?php

namespace App\Certificates;

use App\Models\Activity;
use App\Models\Registration;
use App\Models\Role;
use App\Models\RoleMember;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Participation certificates (attesten) as PDF: organisation from config('gouw.organisation'),
 * signed by every Gouwcommissaris on Wie is wie, with the stamp (see Stamp).
 */
class CertificateRenderer
{
    /**
     * Full names of the Gouwcommissaris(sen), in their order on Wie is wie.
     *
     * @return Collection<int, string>
     */
    public function signers(): Collection
    {
        $role = Role::where('category', Role::BUREAU)->where('key', 'commissaris')->first();

        return $role ? $role->members()->get()->map(fn (RoleMember $member) => $member->fullName())->values() : collect();
    }

    /**
     * @throws CertificateUnavailable when this activity can't have certificates (yet)
     */
    public function ensureAvailable(Activity $activity): void
    {
        if (! $activity->hasCertificates()) {
            throw new CertificateUnavailable('Deze activiteit heeft geen vormingsuren, dus geen attesten.');
        }

        if ($problem = $this->problems()[0] ?? null) {
            throw new CertificateUnavailable($problem);
        }
    }

    /**
     * What's missing before any certificate can be made (stamp, signer), for every activity.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if (! Stamp::exists()) {
            $problems[] = 'Er is nog geen stempel opgeladen. Een beheerder kan dat doen via Beheer → Attest.';
        }

        if ($this->signers()->isEmpty()) {
            $problems[] = 'Er staat nog geen gouwcommissaris op Wie is wie.';
        }

        return $problems;
    }

    /**
     * A certificate with sample data, for the admin page (nothing is saved).
     */
    public function samplePdf(): string
    {
        return $this->pdf($this->sampleRegistration());
    }

    public function sampleRegistration(): Registration
    {
        $activity = new Activity([
            'title' => 'Voorbeeldactiviteit',
            'location' => config('gouw.organisation.building'),
            'starts_at' => now()->startOfDay()->setTime(9, 0),
            'ends_at' => now()->startOfDay()->setTime(17, 0),
            'training_hours' => 3,
        ]);
        $activity->slug = 'voorbeeld';

        $registration = new Registration(['name' => 'Voornaam Achternaam']);
        $registration->setRelation('activity', $activity);

        return $registration;
    }

    public function pdf(Registration $registration): string
    {
        $dompdf = new Dompdf(new Options(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->html($registration));
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function html(Registration $registration): string
    {
        $activity = $registration->activity;
        $organisation = config('gouw.organisation');
        $logo = public_path($organisation['logo']);

        return view('attesten.attest', [
            'activity' => $activity,
            'registration' => $registration,
            'organisation' => $organisation,
            'signers' => $this->signers(),
            'stamp' => Stamp::dataUri(),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
            'dateText' => $this->dateText($activity),
            'issuedOn' => $this->longDate(now()),
        ])->render();
    }

    public function filename(Registration $registration): string
    {
        return 'attest-'.$registration->activity->slug.'-'.Str::slug($registration->name).'.pdf';
    }

    /**
     * "op 12 december 2026", or "van 12 december 2026 tot 13 december 2026".
     */
    private function dateText(Activity $activity): string
    {
        if (! $activity->ends_at || $activity->ends_at->isSameDay($activity->starts_at)) {
            return 'op '.$this->longDate($activity->starts_at);
        }

        return 'van '.$this->longDate($activity->starts_at).' tot '.$this->longDate($activity->ends_at);
    }

    private function longDate(Carbon $date): string
    {
        return $date->copy()->locale('nl')->translatedFormat('j F Y');
    }
}
