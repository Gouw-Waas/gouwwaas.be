<?php

namespace App\Models;

use App\Support\PublicFile;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon $registration_deadline
 * @property string|null $image_path
 */
#[Fillable(['title', 'description', 'location', 'starts_at', 'ends_at', 'registration_deadline', 'price_cents', 'capacity', 'training_hours'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'price_cents' => 'integer',
            'training_hours' => 'decimal:1',
        ];
    }

    /** Where background images are stored (public disk, under activities/). */
    public const IMAGE_DISK = 'public';

    protected static function booted(): void
    {
        // The background image goes with the activity (deleted, cancelled or archived).
        static::deleted(fn (Activity $activity) => $activity->deleteImage());

        // The slug is fixed at creation, so shared links keep working after a title change.
        static::creating(function (Activity $activity) {
            $base = Str::slug($activity->title) ?: 'activiteit';
            $slug = $base;

            // "nieuw" is taken by the create page (/activiteiten/nieuw).
            for ($i = 2; $slug === 'nieuw' || static::where('slug', $slug)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }

            $activity->slug = $slug;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The background image for use on the site (without host), or null without one
     * (see PublicFile::url).
     */
    public function imageUrl(): ?string
    {
        return PublicFile::url(self::IMAGE_DISK, $this->image_path);
    }

    /**
     * The same image as a full URL on the host the visitor is on, for link previews
     * (Open Graph), which need an absolute URL.
     */
    public function imageAbsoluteUrl(): ?string
    {
        $url = $this->imageUrl();

        return $url ? url($url) : null;
    }

    /**
     * Replace the background image with $file, or remove it when $remove is set. Saves nothing else.
     */
    public function updateImage(?UploadedFile $file, bool $remove = false): void
    {
        if (! $file && ! $remove) {
            return;
        }

        $this->deleteImage();
        $this->image_path = $file ? ($file->store('activities', self::IMAGE_DISK) ?: null) : null;
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk(self::IMAGE_DISK)->delete($this->image_path);
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ActivityField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(ActivityField::class)->orderBy('position');
    }

    /** @return HasMany<Registration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class)->latest();
    }

    /**
     * Activities that aren't over yet. An activity is over once its end has passed, or, without
     * an end, one day after its start.
     */
    /** @param Builder<Activity> $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('ends_at', '>=', now())
            ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->subDay())));
    }

    /** @param Builder<Activity> $query */
    public function scopePast(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('ends_at', '<', now())
            ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '<', now()->subDay())));
    }

    public function isPast(): bool
    {
        return $this->endedAt()->isPast();
    }

    /**
     * When the activity is over: its end, or one day after its start when it has no end.
     */
    public function endedAt(): Carbon
    {
        return $this->ends_at ?? $this->starts_at->copy()->addDay();
    }

    /**
     * When the activity is archived automatically (config gouw.archive_after_months).
     */
    public function archivesAt(): Carbon
    {
        return $this->endedAt()->addMonths((int) config('gouw.archive_after_months'));
    }

    /**
     * The retention period in words, e.g. "2 jaar" or "18 maanden".
     */
    public static function retentionLabel(): string
    {
        $months = (int) config('gouw.archive_after_months');

        if ($months % 12 === 0) {
            return ($months / 12).' jaar';
        }

        return $months === 1 ? '1 maand' : "{$months} maanden";
    }

    /**
     * Activities that ended more than gouw.archive_after_months ago.
     *
     * @param  Builder<Activity>  $query
     */
    public function scopeArchivable(Builder $query): void
    {
        $cutoff = now()->subMonths((int) config('gouw.archive_after_months'));

        $query->where(fn (Builder $q) => $q
            ->where('ends_at', '<', $cutoff)
            ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '<', $cutoff->copy()->subDay())));
    }

    /**
     * Places taken: participants, plus payments in progress (see Registration::scopeTakingPlace).
     */
    public function takenPlaces(): int
    {
        return $this->registrations()->takingPlace()->count();
    }

    /**
     * Places left, or null when there is no maximum.
     */
    public function placesLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->takenPlaces());
    }

    public function isFull(): bool
    {
        return $this->placesLeft() === 0;
    }

    /**
     * "Nog 3 plaatsen", "Nog 1 plaats" or "Volzet"; null without a maximum.
     */
    public function placesLabel(): ?string
    {
        $left = $this->placesLeft();

        return match (true) {
            $left === null => null,
            $left === 0 => 'Volzet',
            $left === 1 => 'Nog 1 plaats',
            default => "Nog {$left} plaatsen",
        };
    }

    /**
     * Money for the organisers' overview, in cents: paid online (Mollie) and paid outside the
     * website (marked paid by an organiser), and what manual registrations still have to pay.
     * Cancelled registrations are removed, so refunded money isn't counted.
     *
     * @return array{online: int, online_count: int, manual: int, manual_count: int, outstanding: int, outstanding_count: int, total: int}
     */
    public function revenue(): array
    {
        $registrations = $this->registrations;
        $online = $registrations->filter(fn (Registration $registration) => $registration->paidOnline());
        $manual = $registrations->filter(fn (Registration $registration) => $registration->isPaid() && $registration->paid_manually);
        $outstanding = $registrations->filter(fn (Registration $registration) => $registration->isManual() && $registration->canBeMarkedPaid());

        return [
            'online' => (int) $online->sum('amount_cents'),
            'online_count' => $online->count(),
            'manual' => (int) $manual->sum('amount_cents'),
            'manual_count' => $manual->count(),
            'outstanding' => (int) $outstanding->sum('amount_cents'),
            'outstanding_count' => $outstanding->count(),
            'total' => (int) $online->sum('amount_cents') + (int) $manual->sum('amount_cents'),
        ];
    }

    public static function euro(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Activities with vormingsuren give attendees a participation certificate.
     */
    public function hasCertificates(): bool
    {
        return $this->training_hours !== null;
    }

    /**
     * "7,5" or "8"; null without vormingsuren.
     */
    public function trainingHoursLabel(): ?string
    {
        if ($this->training_hours === null) {
            return null;
        }

        $hours = (float) $this->training_hours;

        return fmod($hours, 1.0) === 0.0 ? (string) (int) $hours : number_format($hours, 1, ',', '');
    }

    /**
     * Who gets a certificate when the organiser clicks "Attesten versturen": present,
     * confirmed, and not sent yet. No deduplication by e-mail address: every registration
     * is its own participant.
     *
     * @param  list<int>|null  $ids  only these registrations (a selection on the overview)
     * @return Collection<int, Registration>
     */
    public function certificateRecipients(?array $ids = null): Collection
    {
        return $this->registrations
            ->filter(fn (Registration $registration) => ($ids === null || in_array($registration->id, $ids))
                && $registration->isPresent()
                && $registration->isConfirmed()
                && $registration->certificate_sent_at === null)
            ->values();
    }

    /**
     * The "Attesten versturen" button: vormingsuren, started, and someone to send to.
     */
    public function canSendCertificates(): bool
    {
        return $this->hasCertificates() && ! $this->starts_at->isFuture() && $this->certificateRecipients()->isNotEmpty();
    }

    /**
     * Whether any registration costs money (not the current price: someone may have
     * registered while the activity was still free). Shows the payment column and summary.
     */
    public function involvesMoney(): bool
    {
        return $this->registrations->contains(fn (Registration $registration) => ! $registration->isFree());
    }

    public function isRegistrationOpen(): bool
    {
        return now()->lte($this->registration_deadline);
    }

    public function isFree(): bool
    {
        return $this->price_cents === 0;
    }

    public function priceLabel(): string
    {
        return $this->isFree() ? 'Gratis' : '€ '.number_format($this->price_cents / 100, 2, ',', '.');
    }

    /**
     * E.g. "zaterdag 15 november 2026, 14:00 – 17:00" or a range over several days.
     */
    public function dateLabel(): string
    {
        $start = $this->starts_at->translatedFormat('l j F Y, H:i');

        if (! $this->ends_at) {
            return $start;
        }

        if ($this->ends_at->isSameDay($this->starts_at)) {
            return $start.' – '.$this->ends_at->format('H:i');
        }

        return $start.' – '.$this->ends_at->translatedFormat('l j F Y, H:i');
    }

    /**
     * Description as HTML paragraphs: blank lines split paragraphs, single newlines become line breaks.
     */
    public function descriptionHtml(): string
    {
        return collect(preg_split('/\R{2,}/', trim($this->description)))
            ->map(fn (string $paragraph) => '<p>'.nl2br(e($paragraph)).'</p>')
            ->implode("\n");
    }

    /**
     * Replace the form fields with the submitted list, keeping the ids of fields that still exist.
     *
     * @param  array<int, array{id?: int|string|null, label: string, type: string, required?: bool, options?: string|null}>  $fields
     */
    public function syncFields(array $fields): void
    {
        $existing = $this->fields()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($fields) as $position => $data) {
            $attributes = [
                'label' => trim($data['label']),
                'type' => $data['type'],
                'required' => (bool) ($data['required'] ?? false),
                'options' => $data['type'] === 'select' ? static::parseOptions($data['options'] ?? '') : null,
                'position' => $position,
            ];

            $field = $existing->get((int) ($data['id'] ?? 0));

            if ($field) {
                $field->update($attributes);
            } else {
                $field = $this->fields()->create($attributes);
            }

            $keep[] = $field->id;
        }

        $this->fields()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @return array<int, string>
     */
    public static function parseOptions(?string $options): array
    {
        return collect(preg_split('/\R/', (string) $options))
            ->map(fn (string $option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Columns for the registrations overview: current fields, plus fields that were
     * removed after people had already answered them.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    public function answerColumns(): Collection
    {
        $columns = $this->fields->map(fn (ActivityField $field) => ['id' => $field->id, 'label' => $field->label]);
        $current = $columns->pluck('id')->all();

        $removed = $this->registrations
            ->flatMap(fn (Registration $registration) => $registration->answers)
            ->reject(fn (array $answer) => in_array($answer['field_id'], $current, true))
            ->unique('field_id')
            ->map(fn (array $answer) => ['id' => $answer['field_id'], 'label' => $answer['label'].' (verwijderd veld)']);

        return $columns->concat($removed)->values();
    }

    /**
     * Validate a registration (name, e-mail and the activity's own questions).
     *
     * @param  bool  $optional  organisers adding someone by hand: the questions aren't required
     * @return array{name: string, email: string, answers: list<array{field_id: int, label: string, value: string}>}
     */
    public function validateRegistration(Request $request, bool $optional = false): array
    {
        $rules = ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']];
        $attributes = ['name' => 'naam', 'email' => 'e-mailadres'];

        foreach ($this->fields as $field) {
            $rules["answers.{$field->id}"] = $field->rules($optional);
            $attributes["answers.{$field->id}"] = '"'.$field->label.'"';
        }

        $validated = $request->validate($rules, [
            'answers.*.accepted' => 'Vink :attribute aan.',
            'answers.*.in' => 'Kies een geldige waarde voor :attribute.',
        ], $attributes);

        return [
            'name' => $validated['name'],
            'email' => $validated['email'],
            // Label stored along with the answer: renaming or removing a field later keeps it readable.
            'answers' => $this->fields->map(fn (ActivityField $field) => [
                'field_id' => $field->id,
                'label' => $field->label,
                'value' => $field->present($validated['answers'][$field->id] ?? null),
            ])->values()->all(),
        ];
    }
}
