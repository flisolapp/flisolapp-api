<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StorageCacheHelper
{

    private static array $cache = [];

    public static function get(string $key): ?string
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $disk = Storage::disk('s3');
        $localDisk = Storage::disk('storage_cache');

        // Try downloading from S3
        try {
            if (!$localDisk->exists($key)) {
                // Check S3 first — avoid a get() call on a missing file
                if (!$disk->exists($key)) {
                    return null;
                }

                $contents = $disk->get($key);

                // exists() and get() are not atomic; guard against the race
                // where the file disappears between the two calls, or against
                // Flysystem returning null for an unreadable stream.
                if ($contents === null) {
                    Log::error("S3 get returned null for '{$key}' despite exists() being true");
                    return null;
                }

                $localDisk->put($key, $contents);
            }
        } catch (Throwable $e) {
            Log::error("S3 get failed for '{$key}': " . $e->getMessage());

            // Do not fall through — the file was not cached locally.
            return null;
        }

        // Guard: confirm the local file actually landed before handing back its path.
        // If the put() succeeded but the file is somehow absent, return null rather
        // than a path that will make file_exists() return false in the caller.
        if (!$localDisk->exists($key)) {
            Log::error("S3 cache write appeared to succeed but file is missing locally: '{$key}'");
            return null;
        }

        $path = $localDisk->path($key);
        self::$cache[$key] = $path;

        return $path;
    }

    public static function save(string $key, string $binaryData): void
    {
        if ($binaryData === null) {
            throw new RuntimeException("Cannot save null data for key: {$key}");
        }

        $diskS3 = Storage::disk('s3');
        $diskLocal = Storage::disk('storage_cache');

        $diskLocal->put($key, $binaryData);
        $diskS3->put($key, $binaryData);

        self::$cache[$key] = $diskLocal->path($key);
    }

}
