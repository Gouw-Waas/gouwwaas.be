<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * URLs for uploaded files on a public disk (activity images, logos, photos).
 */
class PublicFile
{
    /**
     * The file's URL without host ("/storage/…"), or null without a path or when the file is
     * missing (e.g. a local copy of the production data without the uploads).
     *
     * The disk builds its URL from APP_URL; when the site is opened on another host
     * (www vs no www, 127.0.0.1 vs localhost) the browser's CSP (img-src 'self') would block it.
     */
    public static function url(string $disk, ?string $path): ?string
    {
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $url = Storage::disk($disk)->url($path);

        return parse_url($url, PHP_URL_PATH) ?: $url;
    }
}
