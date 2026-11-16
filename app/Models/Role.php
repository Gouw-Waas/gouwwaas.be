<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

/**
 * A function on /wie-is-wie. Every function belongs to a category:
 *
 * - bureau:   the three gouwbureau functions, fixed in config('gouw.bureau')
 * - adjunct:  free to create, rename and delete
 * - district: one per district in config('gouw.districts')
 *
 * Fixed functions keep their title and order from the config; only their description,
 * contact details and people are edited. A function without people shows "Gezocht!".
 */
#[Fillable(['category', 'key', 'title', 'description', 'email', 'instagram', 'facebook', 'website', 'position'])]
class Role extends Model
{
    public const BUREAU = 'bureau';

    public const ADJUNCT = 'adjunct';

    public const DISTRICT = 'district';

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    protected static function booted(): void
    {
        // Through the models, so the photos are removed too.
        static::deleting(fn (Role $role) => $role->members()->get()->each->delete());
    }

    public function members(): HasMany
    {
        return $this->hasMany(RoleMember::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The level of the organisation, as shown on a card outside its own section.
     */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::BUREAU => 'Gouwbureau',
            self::ADJUNCT => 'Adjunct',
            self::DISTRICT => 'Districtsploeg',
            default => '',
        };
    }

    public function isAdjunct(): bool
    {
        return $this->category === self::ADJUNCT;
    }

    /**
     * The fixed functions as configured, keyed by "category:key".
     *
     * @return array<string, array{category: string, key: string, title: string, description: ?string, position: int}>
     */
    public static function fixedDefinitions(): array
    {
        $definitions = [];

        foreach (array_keys(config('gouw.bureau')) as $position => $key) {
            [$title, $description] = config("gouw.bureau.{$key}");
            $definitions[self::BUREAU.':'.$key] = ['category' => self::BUREAU, 'key' => $key, 'title' => $title, 'description' => $description, 'position' => $position];
        }

        foreach (array_keys(config('gouw.districts')) as $position => $key) {
            $title = 'District '.config("gouw.districts.{$key}.name");
            $definitions[self::DISTRICT.':'.$key] = ['category' => self::DISTRICT, 'key' => $key, 'title' => $title, 'description' => null, 'position' => $position];
        }

        return $definitions;
    }

    /**
     * Create missing fixed functions and apply title/order changes from the config.
     * Returns whether anything changed, so callers know to reload.
     *
     * @param  Collection<int, Role>  $roles  all functions currently in the database
     */
    public static function syncFixed(Collection $roles): bool
    {
        $existing = $roles->keyBy(fn (Role $role) => $role->category.':'.$role->key);
        $changed = false;

        foreach (static::fixedDefinitions() as $id => $definition) {
            $role = $existing->get($id);

            if (! $role) {
                static::create($definition);
                $changed = true;
            } elseif ($role->title !== $definition['title'] || $role->position !== $definition['position']) {
                $role->update(['title' => $definition['title'], 'position' => $definition['position']]);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * True for adjuncten and for fixed functions still in the config.
     */
    public function isListed(): bool
    {
        return $this->isAdjunct() || array_key_exists($this->category.':'.$this->key, static::fixedDefinitions());
    }

    /**
     * The configured contact details, in display order.
     *
     * @return array<int, array{type: string, url: string, label: string}>
     */
    public function contactLinks(): array
    {
        $links = [];

        if ($this->email) {
            $links[] = ['type' => 'email', 'url' => 'mailto:'.$this->email, 'label' => $this->email];
        }

        if ($this->instagram) {
            $handle = static::instagramHandle($this->instagram);
            $links[] = ['type' => 'instagram', 'url' => $this->instagram, 'label' => $handle ? '@'.$handle : 'Instagram'];
        }

        if ($this->facebook) {
            $links[] = ['type' => 'facebook', 'url' => $this->facebook, 'label' => 'Facebook'];
        }

        if ($this->website) {
            $host = preg_replace('/^www\./', '', (string) parse_url($this->website, PHP_URL_HOST));
            $links[] = ['type' => 'website', 'url' => $this->website, 'label' => $host ?: $this->website];
        }

        return $links;
    }

    public static function instagramHandle(string $url): ?string
    {
        if (! preg_match('~instagram\.com/([A-Za-z0-9._]+)~i', $url, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Description as HTML paragraphs: blank lines split paragraphs, single newlines become line breaks.
     */
    public function descriptionHtml(): string
    {
        return collect(preg_split('/\R{2,}/', trim((string) $this->description)))
            ->filter()
            ->map(fn (string $paragraph) => '<p>'.nl2br(e($paragraph)).'</p>')
            ->implode("\n");
    }

    /**
     * Replace the people with the submitted list, keeping the ids (and photos) of people that remain.
     *
     * @param  array<int|string, array{id?: int|string|null, first_name: string, last_name: string, totem?: ?string, title?: ?string, remove_photo?: bool|string|null}>  $rows
     * @param  array<int|string, UploadedFile>  $photos  new photos, keyed like $rows
     */
    public function syncMembers(array $rows, array $photos): void
    {
        $existing = $this->members()->get()->keyBy('id');
        $keep = [];
        $position = 0;

        foreach ($rows as $key => $data) {
            $member = $existing->get((int) ($data['id'] ?? 0)) ?? $this->members()->make();

            $member->fill([
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'totem' => filled($data['totem'] ?? null) ? trim($data['totem']) : null,
                'title' => filled($data['title'] ?? null) ? trim($data['title']) : null,
                'position' => $position++,
            ]);

            if (isset($photos[$key]) || ($data['remove_photo'] ?? false)) {
                $member->deletePhoto();
                $member->photo_path = isset($photos[$key])
                    ? $photos[$key]->store('wie-is-wie', RoleMember::PHOTO_DISK)
                    : null;
            }

            $member->save();
            $keep[] = $member->id;
        }

        $existing->except($keep)->each->delete();
    }
}
