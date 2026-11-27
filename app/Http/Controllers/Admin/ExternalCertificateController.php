<?php

namespace App\Http\Controllers\Admin;

use App\Certificates\CertificateRenderer;
use App\Certificates\ParticipantList;
use App\Http\Controllers\Controller;
use App\Mail\CertificateMail;
use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Organisatie → Extern attest: participation certificates for an activity a subgroup organised.
 * The activity and its participants only exist for the length of the request: nothing is
 * stored, so there's no history and no protection against sending twice beyond the form.
 */
class ExternalCertificateController extends Controller
{
    public const MAX_PARTICIPANTS = 1000;

    private const ACTIVITY_FIELDS = ['title', 'location', 'starts_on', 'ends_on', 'training_hours', 'organiser'];

    public function edit(CertificateRenderer $renderer): View
    {
        return view('organisatie.extern-attest', ['problems' => $renderer->problems()]);
    }

    public function send(Request $request, CertificateRenderer $renderer): RedirectResponse
    {
        $hoursMessage = 'Vul een aantal uren in, bv. 3 of 7,5.';
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'training_hours' => ['required', 'regex:/^\d{1,2}([.,][05])?$/', 'not_regex:/^0+([.,]0)?$/'],
            'organiser' => ['nullable', 'string', 'max:255'],
            'participants' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:1024'],
        ], [
            'training_hours.regex' => $hoursMessage,
            'training_hours.not_regex' => $hoursMessage,
            'ends_on.after_or_equal' => 'De einddatum kan niet voor de startdatum liggen.',
        ], [
            'title' => 'titel',
            'location' => 'locatie',
            'starts_on' => 'startdatum',
            'ends_on' => 'einddatum',
            'training_hours' => 'vormingsuren',
            'organiser' => 'georganiseerd door',
            'participants' => 'deelnemers',
            'file' => 'bestand',
        ]);

        // Read in memory only: the upload is never stored.
        $text = ($validated['participants'] ?? '')."\n".($request->hasFile('file') ? (string) $request->file('file')->get() : '');
        $list = ParticipantList::parse($text);

        if ($list->invalid) {
            $lines = collect($list->invalid)->map(fn (array $invalid) => "regel {$invalid['line']}: {$invalid['text']}")->implode('; ');

            return $this->backToForm(['participants' => "Deze regels kloppen niet (naam en e-mailadres per regel): {$lines}."]);
        }

        if (! $list->participants) {
            return $this->backToForm(['participants' => 'Vul minstens één deelnemer in.']);
        }

        if (count($list->participants) > self::MAX_PARTICIPANTS) {
            return $this->backToForm(['participants' => 'Maximaal '.self::MAX_PARTICIPANTS.' deelnemers per keer.']);
        }

        if ($problem = $renderer->problems()[0] ?? null) {
            return $this->backToForm(['certificate' => $problem]);
        }

        $startsAt = Carbon::createFromFormat('Y-m-d', $validated['starts_on'])->startOfDay();
        if ($startsAt->isAfter(today())) {
            return $this->backToForm(['certificate' => 'Attesten kun je pas versturen vanaf de start van de activiteit.']);
        }

        $activity = new Activity([
            'title' => $validated['title'],
            'location' => $validated['location'],
            'starts_at' => $startsAt,
            'ends_at' => filled($validated['ends_on'] ?? null) ? Carbon::createFromFormat('Y-m-d', $validated['ends_on'])->startOfDay() : null,
            'training_hours' => (float) str_replace(',', '.', $validated['training_hours']),
        ]);
        $activity->slug = Str::slug($activity->title) ?: 'activiteit';
        $organiser = filled($validated['organiser'] ?? null) ? $validated['organiser'] : null;

        // Sent straight away (no queue worker in production): give large groups the time they need.
        set_time_limit(max(120, count($list->participants) * 2));

        $sent = 0;
        $failed = [];
        foreach ($list->participants as $participant) {
            $registration = new Registration(['name' => $participant['name'], 'email' => $participant['email']]);
            $registration->setRelation('activity', $activity);

            try {
                Mail::to($participant['email'], $participant['name'])
                    ->send(new CertificateMail($registration, $renderer->pdf($registration, $organiser), $renderer->filename($registration)));
                $sent++;
            } catch (Throwable $e) {
                // Only the type: the message can contain the address, and we keep nothing about participants.
                Log::warning('Extern attest niet verstuurd.', ['exception' => $e::class]);
                $failed[] = $participant;
            }
        }

        $redirect = redirect()->route('attest.extern')
            ->with('status', $sent === 1 ? '1 attest verstuurd.' : "{$sent} attesten verstuurd.");

        if ($failed) {
            $redirect
                ->withInput($request->only(self::ACTIVITY_FIELDS) + [
                    'participants' => collect($failed)->map(ParticipantList::line(...))->implode("\n"),
                ])
                ->withErrors(['participants' => 'Niet verstuurd naar: '.collect($failed)->pluck('name')->implode(', ')
                    .'. Ze staan nog in de lijst: verstuur opnieuw om enkel hen een attest te sturen.']);
        }

        return $redirect;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function backToForm(array $errors): RedirectResponse
    {
        return redirect()->route('attest.extern')->withErrors($errors)->withInput(request()->except('file'));
    }
}
