<?php

namespace App\Models;

use App\Models\Concerns\HasLogo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A scouting group of the gouw, on the map on /districten. Admins manage them.
 */
#[Fillable(['district', 'number', 'name', 'type', 'akabe', 'akabe_note', 'street', 'postal_code', 'city', 'latitude', 'longitude', 'website'])]
class ScoutGroup extends Model
{
    use HasLogo;

    public const TYPES = [
        'gemengd' => 'Scouts en Gidsen (gemengd)',
        'scouts' => 'Scouts',
        'gidsen' => 'Gidsen',
        'zeescouts' => 'Zeescouts',
    ];

    /**
     * Akabe ("Anders KAn BEst!"), separate from the type. No value: no Akabe.
     */
    public const AKABE = [
        'groep' => 'Akabegroep',
        'tak' => 'Akabetak',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * @return array<string, array{name: string, color: string, boundary: array<mixed>|null}>
     */
    public static function districts(): array
    {
        return config('gouw.districts');
    }

    public function districtName(): string
    {
        return config("gouw.districts.{$this->district}.name", $this->district);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function hasAkabe(): bool
    {
        return isset(self::AKABE[$this->akabe]);
    }

    public function akabeLabel(): ?string
    {
        return self::AKABE[$this->akabe] ?? null;
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Anchor on /districten that opens this group on the map, e.g. "groep-o3101g".
     */
    public function anchor(): string
    {
        return 'groep-'.Str::lower($this->number);
    }

    public function websiteLabel(): ?string
    {
        if (! $this->website) {
            return null;
        }

        $host = (string) parse_url($this->website, PHP_URL_HOST);

        return preg_replace('/^www\./', '', $host) ?: $this->website;
    }

    protected function logoDirectory(): string
    {
        return 'logos/groepen';
    }

    /**
     * Route planner link to the meeting place.
     */
    public function directionsUrl(): string
    {
        $destination = $this->hasLocation()
            ? $this->latitude.','.$this->longitude
            : "{$this->street}, {$this->postal_code} {$this->city}";

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($destination);
    }
}
