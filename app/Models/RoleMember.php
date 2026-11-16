<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A person filling a function on /wie-is-wie.
 */
#[Fillable(['first_name', 'last_name', 'totem', 'title', 'photo_path', 'position'])]
class RoleMember extends Model
{
    public const PHOTO_DISK = 'public';

    protected static function booted(): void
    {
        static::deleted(fn (RoleMember $member) => $member->deletePhoto());
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function initials(): string
    {
        return Str::upper(Str::substr($this->first_name, 0, 1).Str::substr($this->last_name, 0, 1));
    }

    /**
     * Null when there is no photo, or when the file is missing (e.g. a local copy of
     * the production data without the uploads): the views then show initials.
     */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path || ! Storage::disk(self::PHOTO_DISK)->exists($this->photo_path)) {
            return null;
        }

        return Storage::disk(self::PHOTO_DISK)->url($this->photo_path);
    }

    public function deletePhoto(): void
    {
        if ($this->photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete($this->photo_path);
        }
    }
}
