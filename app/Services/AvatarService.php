<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config;
use App\Support\Logger;

/**
 * Avatar uploads.
 *
 * Validation is delegated to UploadGuard, and then the decisive step happens
 * here: the image is decoded and **re-encoded** from scratch. What lands on
 * disk is bytes produced by GD from a decoded pixel buffer, so any payload that
 * survived the earlier checks — appended archives, EXIF comments carrying
 * script, polyglot headers — is simply not part of the output. Metadata is
 * dropped for the same reason, which also stops avatars leaking a member's
 * camera or location data.
 */
final class AvatarService
{
    private UploadGuard $guard;

    public function __construct(?UploadGuard $guard = null)
    {
        $this->guard = $guard ?? new UploadGuard();
    }

    /**
     * @param array<string,mixed> $file A single $_FILES entry.
     * @return array{ok:bool,path?:string,message?:string}
     */
    public function store(array $file, int $userId): array
    {
        if (!$this->available()) {
            Logger::error('Avatar upload attempted without the GD extension');

            return ['ok' => false, 'message' => 'Image uploads are unavailable on this server.'];
        }

        $config = Config::get('uploads.avatars');

        $inspection = $this->guard->inspect($file, [
            'max_bytes' => (int) $config['max_bytes'],
            'allowed_mime' => (array) $config['allowed_mime'],
            // Source limits, not stored limits: anything within them is
            // accepted and then scaled down during re-encoding.
            'max_width' => (int) $config['max_source_width'],
            'max_height' => (int) $config['max_source_height'],
            'max_pixels' => (int) $config['max_pixels'],
        ]);

        if (($inspection['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) $inspection['message']];
        }

        $directory = (string) $config['directory'];

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            Logger::error('Avatar directory is not writable', ['directory' => $directory]);

            return ['ok' => false, 'message' => 'The server cannot store uploads right now.'];
        }

        // Always store PNG: one decoder, one encoder, no format surprises.
        $filename = $this->guard->safeFilename($userId, 'png');
        $destination = $directory . '/' . $filename;

        if (!$this->reencode((string) $inspection['path'], (string) $inspection['mime'], $destination)) {
            return ['ok' => false, 'message' => 'That image could not be processed.'];
        }

        // Uploads are data. Never executable, never group-writable.
        @chmod($destination, 0644);

        // Final proof: what we wrote is a PNG and nothing else.
        $verification = @getimagesize($destination);

        if ($verification === false || ($verification['mime'] ?? '') !== 'image/png') {
            @unlink($destination);
            Logger::security('Avatar rejected after re-encoding', ['user_id' => $userId]);

            return ['ok' => false, 'message' => 'That image could not be processed.'];
        }

        return ['ok' => true, 'path' => (string) $config['url_prefix'] . '/' . $filename];
    }

    /**
     * Decodes the source and writes a fresh PNG. Nothing from the original file
     * is copied across — only the pixels.
     */
    private function reencode(string $source, string $mime, string $destination): bool
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/gif' => @imagecreatefromgif($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };

        if ($image === false) {
            return false;
        }

        $config = Config::get('uploads.avatars');
        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = (int) $config['max_width'];
        $maxHeight = (int) $config['max_height'];

        // Scale down rather than refuse when the source is oversized after all.
        if ($width > $maxWidth || $height > $maxHeight) {
            $scale = min($maxWidth / $width, $maxHeight / $height);
            $targetWidth = max(1, (int) floor($width * $scale));
            $targetHeight = max(1, (int) floor($height * $scale));
        } else {
            $targetWidth = $width;
            $targetHeight = $height;
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            imagedestroy($image);

            return false;
        }

        // Preserve transparency instead of turning it black.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

        if ($transparent !== false) {
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        $copied = imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        if (!$copied) {
            imagedestroy($canvas);

            return false;
        }

        $written = imagepng($canvas, $destination, 6);
        imagedestroy($canvas);

        return $written;
    }

    public function remove(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $config = Config::get('uploads.avatars');
        $prefix = (string) $config['url_prefix'];

        if (!str_starts_with($path, $prefix . '/')) {
            return;
        }

        $filename = basename($path);

        // Only ever delete inside the avatar directory, and only a name of the
        // shape this service generates.
        if (preg_match('/^\d+-[0-9a-f]{24}\.[a-z0-9]{2,5}$/', $filename) !== 1) {
            return;
        }

        $file = (string) $config['directory'] . '/' . $filename;

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Uploads need GD; without it the feature refuses rather than degrades. */
    public function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    /** @return array<int,string> Human-readable list of accepted formats. */
    public function allowedFormats(): array
    {
        return array_values(array_map('strtoupper', (array) Config::get('uploads.avatars.allowed_mime')));
    }

    /** The limit a member will actually hit, PHP's own ceilings included. */
    public function maxKilobytes(): int
    {
        return intdiv(UploadGuard::effectiveMaxBytes((int) Config::get('uploads.avatars.max_bytes', 4194304)), 1024);
    }
}
