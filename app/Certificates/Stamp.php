<?php

namespace App\Certificates;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The gouw stamp printed on participation certificates: one PNG on the private disk,
 * uploaded by admins (Beheer → Attest).
 */
class Stamp
{
    public const DISK = 'local';

    public const PATH = 'attesten/stempel.png';

    public static function exists(): bool
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

    public static function dataUri(): ?string
    {
        if (! self::exists()) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) Storage::disk(self::DISK)->get(self::PATH));
    }
}
