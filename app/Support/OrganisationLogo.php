<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The organisation's logo on participation certificates: a PNG on the private disk, uploaded
 * by admins (Organisatie → Gegevens). Without an upload, the default from config('gouw.logo').
 */
class OrganisationLogo
{
    public const DISK = 'local';

    public const PATH = 'organisatie/logo.png';

    public static function isUploaded(): bool
    {
        return Storage::disk(self::DISK)->exists(self::PATH);
    }

    public static function store(UploadedFile $file): void
    {
        Storage::disk(self::DISK)->putFileAs(dirname(self::PATH), $file, basename(self::PATH));
    }

    public static function delete(): void
    {
        Storage::disk(self::DISK)->delete(self::PATH);
    }

    /**
     * The uploaded logo, else the default one, as a data URI (dompdf and <img> both read it).
     */
    public static function dataUri(): ?string
    {
        if (self::isUploaded()) {
            $png = Storage::disk(self::DISK)->get(self::PATH);
        } else {
            $default = public_path(config('gouw.logo'));
            $png = is_file($default) ? file_get_contents($default) : null;
        }

        return $png ? 'data:image/png;base64,'.base64_encode($png) : null;
    }
}
