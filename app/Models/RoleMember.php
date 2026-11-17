<?php

namespace App\Models;

use App\Support\PublicFile;
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

    /** @return BelongsTo<Role, $this> */
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
     * The photo's URL without host, or null without a photo (see PublicFile::url):
     * the views then show the placeholder.
     */
    public function photoUrl(): ?string
    {
        return PublicFile::url(self::PHOTO_DISK, $this->photo_path);
    }

    public function deletePhoto(): void
    {
        if ($this->photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete($this->photo_path);
        }
    }
}
