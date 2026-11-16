<?php

namespace App\Models;

use App\Models\Concerns\HasLogo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What admins change about a district on the website (for now: its logo).
 * Name, colour and outline stay in config/gouw.php, under the same key.
 */
#[Fillable(['key'])]
class District extends Model
{
    use HasLogo;

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * The record for a configured district, created on first use.
     */
    public static function forKey(string $key): self
    {
        abort_unless(array_key_exists($key, config('gouw.districts')), 404);

        return static::firstOrCreate(['key' => $key]);
    }

    public function name(): string
    {
        return config("gouw.districts.{$this->key}.name", $this->key);
    }

    protected function logoDirectory(): string
    {
        return 'logos/districten';
    }
}
