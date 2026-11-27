<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The organisation (Gouw Waas): name, address and e-mail, on certificates, in the footer, on
 * the privacy page and as reply-to address. Admins edit it on /organisatie. A single row;
 * without one, the defaults from config('gouw.organisation') are used.
 *
 * @property string $name
 * @property string|null $building
 * @property string $street
 * @property string $city
 * @property string $email
 */
#[Fillable(['name', 'building', 'street', 'city', 'email'])]
class Organisation extends Model
{
    protected $table = 'organisation';

    public static function current(): self
    {
        return static::query()->first()
            ?? new self(array_intersect_key((array) config('gouw.organisation'), array_flip((new self)->getFillable())));
    }

    /**
     * "Gouwhuis Karree, Apostelstraat 5, 9100 Sint-Niklaas" (without the building if there's none).
     */
    public function address(): string
    {
        return implode(', ', array_filter([$this->building, $this->street, $this->city]));
    }
}
