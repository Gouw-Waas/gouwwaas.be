<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded logo (logo_path on the public disk), removed together with the model.
 */
trait HasLogo
{
    public const LOGO_DISK = 'public';

    public static function bootHasLogo(): void
    {
        static::deleted(fn (self $model) => $model->deleteLogo());
    }

    /**
     * Null without a logo, or when the file is missing (e.g. a local copy of the production data).
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo_path || ! Storage::disk(self::LOGO_DISK)->exists($this->logo_path)) {
            return null;
        }

        return Storage::disk(self::LOGO_DISK)->url($this->logo_path);
    }

    /**
     * Replace the logo with $file, or remove it when $remove is set. Saves nothing else.
     */
    public function updateLogo(?UploadedFile $file, bool $remove = false): void
    {
        if (! $file && ! $remove) {
            return;
        }

        $this->deleteLogo();
        $this->logo_path = $file?->store($this->logoDirectory(), self::LOGO_DISK);
    }

    public function deleteLogo(): void
    {
        if ($this->logo_path) {
            Storage::disk(self::LOGO_DISK)->delete($this->logo_path);
        }
    }

    abstract protected function logoDirectory(): string;
}
