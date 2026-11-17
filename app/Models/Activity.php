<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon $registration_deadline
 */
#[Fillable(['title', 'description', 'location', 'starts_at', 'ends_at', 'registration_deadline', 'price_cents'])]
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
        ];
    }

    protected static function booted(): void
    {
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
}
