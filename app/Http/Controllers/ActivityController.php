<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityField;
use App\Payments\RegistrationCancellation;
use App\Support\ActivityCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivityController extends Controller
{
    /**
     * Past activities are only listed for admins, who can archive them.
     */
    public function index(Request $request): View
    {
        $isAdmin = (bool) $request->user()?->is_admin;

        return view('activiteiten.index', [
            'upcoming' => Activity::upcoming()->orderBy('starts_at')->get(),
            'past' => $isAdmin
                ? Activity::past()->withCount('registrations')->orderByDesc('starts_at')->get()
                : collect(),
            'mine' => $request->user()
                ? $request->user()->activities()
                    ->unless($isAdmin, fn ($query) => $query->upcoming())
                    ->withCount('registrations')
                    ->orderByDesc('starts_at')
                    ->get()
                : collect(),
        ]);
    }

    /**
     * Admins: permanently delete a past activity and its registrations. Nothing is refunded and
     * nobody is e-mailed (unlike deleting an upcoming activity).
     */
    public function archive(Activity $activity): RedirectResponse
    {
        if (! $activity->isPast()) {
            return redirect()->route('activiteiten.index')
                ->withErrors(['archive' => "\"{$activity->title}\" is nog niet voorbij en kan dus niet gearchiveerd worden."]);
        }

        $count = $activity->registrations()->count();
        $activity->delete(); // registrations go with it (cascade)

        return redirect()->route('activiteiten.index')->with('status',
            "\"{$activity->title}\" is gearchiveerd: de activiteit en {$count} ".($count === 1 ? 'inschrijving zijn' : 'inschrijvingen zijn').' definitief verwijderd.');
    }

    public function show(Activity $activity): View
    {
        return view('activiteiten.show', ['activity' => $activity->load('fields')]);
    }

    /**
     * The activity as a calendar file (.ics), for "Zet in je agenda".
     */
    public function calendar(Activity $activity): Response
    {
        $calendar = ActivityCalendar::for($activity);

        return response($calendar->ics(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$calendar->filename().'"',
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Activity::class);

        return view('activiteiten.form', ['activity' => new Activity]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Activity::class);

        [$attributes, $fields] = $this->validated($request);

        $activity = DB::transaction(function () use ($request, $attributes, $fields) {
            $activity = $request->user()->activities()->make($attributes);
            $activity->updateImage($request->file('image'));
            $activity->save();
            $activity->syncFields($fields);

            return $activity;
        });

        return redirect()->route('activiteiten.show', $activity)->with('status', 'De activiteit is aangemaakt.');
    }

    public function edit(Activity $activity): View
    {
        Gate::authorize('update', $activity);

        return view('activiteiten.form', ['activity' => $activity->load('fields')->loadCount('registrations')]);
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);

        [$attributes, $fields] = $this->validated($request);

        DB::transaction(function () use ($request, $activity, $attributes, $fields) {
            $activity->fill($attributes);
            $activity->updateImage($request->file('image'), $request->boolean('remove_image'));
            $activity->save();
            $activity->syncFields($fields);
        });

        return redirect()->route('activiteiten.show', $activity)->with('status', 'De activiteit is bijgewerkt.');
    }

    /**
     * Delete the activity: every paid registration is refunded in full and everyone who was
     * registered gets a cancellation e-mail. If some refunds fail, the activity stays with
     * just those registrations, so deleting can be tried again.
     */
    public function destroy(Activity $activity, RegistrationCancellation $cancellation): RedirectResponse
    {
        Gate::authorize('delete', $activity);

        $result = $cancellation->cancelActivity($activity);

        if ($result['failed']) {
            return redirect()->route('activiteiten.edit', $activity)->withErrors(['cancel' => 'De activiteit is niet verwijderd: voor '.implode(', ', $result['failed']).' lukte de terugbetaling niet '
                .'(of wordt een betaling nog verwerkt). De andere deelnemers zijn wel terugbetaald en verwittigd. '
                .'Kijk in het Mollie-dashboard of er genoeg saldo is en probeer opnieuw.',
            ]);
        }

        $status = "\"{$activity->title}\" is verwijderd.";
        if ($result['cancelled']) {
            $status .= " {$result['cancelled']} ".($result['cancelled'] === 1 ? 'deelnemer kreeg' : 'deelnemers kregen').' een annuleringsmail';
            $status .= '.';
            if ($result['refunded']) {
                $status .= ' De terugbetalingen (samen € '.number_format($result['refunded'] / 100, 2, ',', '.').') worden verwerkt.';
            }
        }

        return redirect()->route('activiteiten.index')->with('status', $status);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_deadline' => ['required', 'date', 'before_or_equal:starts_at'],
            'price' => ['nullable', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            // Usually made smaller in the browser before uploading (see the form).
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array', 'max:30'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(array_keys(ActivityField::TYPES))],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'string', 'max:5000'],
        ], [
            'ends_at.after_or_equal' => 'Het einde moet na het begin liggen.',
            'registration_deadline.before_or_equal' => 'De inschrijvingsdeadline moet voor het begin van de activiteit liggen.',
            'price.regex' => 'Vul een bedrag in, bv. 12 of 12,50.',
            'image.image' => 'Kies een afbeelding (jpg, png of webp).',
            'image.mimes' => 'Kies een afbeelding (jpg, png of webp).',
            'image.max' => 'De afbeelding mag maximaal 5 MB groot zijn.',
            'fields.*.label.required' => 'Geef elk veld een naam.',
        ], [
            'title' => 'titel',
            'description' => 'beschrijving',
            'location' => 'locatie',
            'starts_at' => 'begin',
            'ends_at' => 'einde',
            'registration_deadline' => 'inschrijvingsdeadline',
            'price' => 'prijs',
            'capacity' => 'maximum aantal deelnemers',
            'image' => 'achtergrondafbeelding',
        ]);

        // A dropdown needs at least one option to choose from.
        foreach ($validated['fields'] ?? [] as $index => $field) {
            if ($field['type'] === 'select' && Activity::parseOptions($field['options'] ?? '') === []) {
                throw ValidationException::withMessages([
                    "fields.{$index}.options" => 'Geef een keuzelijst minstens één keuze (één per regel).',
                ]);
            }
        }

        $attributes = [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'location' => $validated['location'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'registration_deadline' => $validated['registration_deadline'],
            'price_cents' => (int) round(((float) str_replace(',', '.', $validated['price'] ?? '0')) * 100),
            'capacity' => filled($validated['capacity'] ?? null) ? (int) $validated['capacity'] : null,
        ];

        return [$attributes, $validated['fields'] ?? []];
    }
}
