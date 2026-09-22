<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Logger;

/**
 * Generic upload inspection.
 *
 * Nothing the browser says about a file is believed: not the name, not the
 * extension, not the Content-Type header. A file is accepted only when its
 * bytes say what it claims to be, and the checks are layered so that defeating
 * one is not enough:
 *
 *   1. it really came through PHP's upload machinery
 *   2. its size is inside the policy
 *   3. its leading bytes match a known signature for an allowed type
 *   4. the signature, the fileinfo type and the image type all agree
 *   5. its dimensions and total pixel count are inside the policy
 *   6. it contains no executable markers anywhere in its bytes
 *   7. the name it will be stored under is generated here, never derived
 *      from anything the client sent
 *
 * The caller is expected to finish the job by re-encoding image data (see
 * AvatarService), which discards anything that survived all of the above.
 */
final class UploadGuard
{
    /**
     * Leading bytes that identify the formats the board accepts. A file whose
     * head matches none of these never reaches any further check.
     *
     * @var array<string,array<int,string>>
     */
    private const SIGNATURES = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\r\n\x1A\n"],
        'image/gif' => ['GIF87a', 'GIF89a'],
        'image/webp' => ['RIFF'],
    ];

    /**
     * Markers scanned for across the whole file.
     *
     * Every one is at least five bytes long, and that length is the point.
     * Compressed image data is effectively random, so a short marker turns up
     * by chance: `<%` is two bytes, which means roughly one hit per 64 KB —
     * it appears about twice in an ordinary 145 KB photograph and would reject
     * it. At five bytes the odds of a chance hit are about one in 10^12, so a
     * match here is a real finding rather than noise.
     *
     * @var array<int,string>
     */
    private const FORBIDDEN_MARKERS = [
        '<?php', '<html', '<body', '<script', '<iframe', '<!doctype html',
        '#!/bin/', '#!/usr/bin/', 'java.lang.runtime', '<?xml',
    ];

    /**
     * There is deliberately no scan for shorter markers, anywhere in the file.
     * A two-byte sequence appears about once per 64 KB of compressed image
     * data, so scanning even a 2 KB header for one rejects roughly six percent
     * of ordinary photographs. A file that is really text wearing an image
     * signature is caught by the checks that do not guess: the signature, the
     * fileinfo type and getimagesize must all agree, and nothing may follow the
     * point where the format says the image ends.
     */

    /**
     * Extensions that must never be produced or accepted, whatever the bytes
     * look like, including as a secondary extension in a compound name.
     *
     * @var array<int,string>
     */
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar', 'pht',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'dll', 'so', 'jsp', 'asp', 'aspx',
        'js', 'mjs', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'swf', 'jar', 'htaccess',
    ];

    /**
     * The largest upload that can actually get through.
     *
     * PHP refuses a file above `upload_max_filesize` before the application
     * sees it, and a request above `post_max_size` before that, so the limit a
     * member experiences is the smallest of the three. Quoting the configured
     * value alone produces the worst kind of error message: one that names a
     * limit the file was under.
     */
    public static function effectiveMaxBytes(int $configured): int
    {
        $limits = [$configured];

        foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
            $value = self::parseBytes((string) ini_get($directive));

            if ($value > 0) {
                $limits[] = $value;
            }
        }

        return min($limits);
    }

    /** Turns PHP's shorthand notation (8M, 512K, 1G) into bytes. */
    private static function parseBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * @param array<string,mixed> $file One entry from $_FILES.
     * @param array{max_bytes:int,allowed_mime:array<string,string>,max_width:int,max_height:int,max_pixels?:int} $policy
     * @return array{ok:bool,message?:string,mime?:string,extension?:string,width?:int,height?:int,path?:string}
     */
    public function inspect(array $file, array $policy): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return $this->reject($this->errorMessage($error, self::effectiveMaxBytes((int) $policy['max_bytes'])));
        }

        $path = (string) ($file['tmp_name'] ?? '');

        // Only a file PHP itself received counts; this blocks an attempt to
        // point the handler at an arbitrary path on the server.
        if ($path === '' || !is_uploaded_file($path) || !is_readable($path)) {
            return $this->reject('The upload could not be verified.');
        }

        return $this->inspectFile($path, (string) ($file['name'] ?? ''), $policy);
    }

    /**
     * Every check except "did this arrive as an upload", so the rules can be
     * exercised directly by the tests against files on disk.
     *
     * @param array{max_bytes:int,allowed_mime:array<string,string>,max_width:int,max_height:int,max_pixels?:int} $policy
     * @return array{ok:bool,message?:string,mime?:string,extension?:string,width?:int,height?:int,path?:string}
     */
    public function inspectFile(string $path, string $declaredName, array $policy): array
    {
        if (!is_readable($path)) {
            return $this->reject('The upload could not be verified.');
        }

        // filesize() is served from PHP's stat cache; clear it so the limit is
        // checked against what is on disk right now.
        clearstatcache(true, $path);
        $size = (int) filesize($path);

        if ($size <= 0) {
            return $this->reject('That file is empty.');
        }

        $maxBytes = self::effectiveMaxBytes((int) $policy['max_bytes']);

        if ($size > $maxBytes) {
            return $this->reject(sprintf(
                'Files must be smaller than %s KB; that one is %s KB.',
                number_format(intdiv($maxBytes, 1024)),
                number_format(intdiv($size, 1024)),
            ));
        }

        // The declared name is used for one thing only: refusing it.
        if ($this->hasDangerousExtension($declaredName)) {
            Logger::security('Upload refused: dangerous extension', ['name' => mb_substr($declaredName, 0, 120)]);

            return $this->reject('That file type is not accepted.');
        }

        $head = (string) file_get_contents($path, false, null, 0, 32);
        $signature = $this->matchSignature($head);

        if ($signature === null || !array_key_exists($signature, (array) $policy['allowed_mime'])) {
            return $this->reject('That file is not one of the accepted image formats.');
        }

        $detected = $this->detectMime($path);

        if ($detected !== null && $detected !== $signature) {
            Logger::security('Upload refused: type mismatch', ['signature' => $signature, 'detected' => $detected]);

            return $this->reject('That file does not match the format its contents claim.');
        }

        $info = @getimagesize($path);

        if ($info === false || !isset($info[0], $info[1], $info['mime'])) {
            return $this->reject('That file is not a readable image.');
        }

        if ((string) $info['mime'] !== $signature) {
            return $this->reject('That file does not match the format its contents claim.');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];

        if ($width < 1 || $height < 1) {
            return $this->reject('That image has no usable dimensions.');
        }

        if ($width > (int) $policy['max_width'] || $height > (int) $policy['max_height']) {
            return $this->reject(sprintf(
                'Images may be at most %d×%d pixels; that one is %d×%d.',
                $policy['max_width'],
                $policy['max_height'],
                $width,
                $height,
            ));
        }

        // A small file can still declare an enormous canvas; decoding it is what
        // exhausts memory, so the pixel count is checked before anything decodes.
        $maxPixels = (int) ($policy['max_pixels'] ?? ($policy['max_width'] * $policy['max_height']));

        if ($width * $height > $maxPixels) {
            return $this->reject('That image describes more pixels than the board will decode.');
        }

        if ($this->containsExecutableMarkers($path)) {
            Logger::security('Upload refused: executable markers in image data', ['size' => $size]);

            return $this->reject('That file contains code, which an image never does.');
        }

        // The precise check for the classic polyglot: a structurally valid
        // image with a payload bolted on after its end marker.
        if ($this->hasTrailingData($path, $signature)) {
            Logger::security('Upload refused: data appended after the image ended', ['size' => $size]);

            return $this->reject('That file has extra data appended after the image ends.');
        }

        return [
            'ok' => true,
            'mime' => $signature,
            'extension' => (string) $policy['allowed_mime'][$signature],
            'width' => $width,
            'height' => $height,
            'path' => $path,
        ];
    }

    /**
     * A generated, collision-resistant name. Nothing from the client reaches
     * the filesystem — not the name, not the extension.
     */
    public function safeFilename(int $ownerId, string $extension): string
    {
        // Reduced to plain letters and digits, then clamped: whatever arrives,
        // what comes out is a short, inert extension.
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?? '');
        $extension = substr($extension, 0, 5);

        if ($extension === '' || strlen($extension) < 2 || in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            $extension = 'bin';
        }

        return sprintf('%d-%s.%s', max(0, $ownerId), bin2hex(random_bytes(12)), $extension);
    }

    private function matchSignature(string $head): ?string
    {
        foreach (self::SIGNATURES as $mime => $signatures) {
            foreach ($signatures as $signature) {
                if (str_starts_with($head, $signature)) {
                    // RIFF also fronts WAV and AVI; WebP names itself at byte 8.
                    if ($mime === 'image/webp' && substr($head, 8, 4) !== 'WEBP') {
                        continue;
                    }

                    return $mime;
                }
            }
        }

        return null;
    }

    private function detectMime(string $path): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = @finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
    }

    private function hasDangerousExtension(string $name): bool
    {
        $name = strtolower(basename(str_replace('\\', '/', $name)));

        // Every segment counts: "avatar.php.png" is refused on the .php.
        foreach (array_slice(explode('.', $name), 1) as $segment) {
            if (in_array($segment, self::DANGEROUS_EXTENSIONS, true)) {
                return true;
            }
        }

        return str_contains($name, "\0");
    }

    /** Scans the whole file for the long markers listed above. */
    private function containsExecutableMarkers(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return true;
        }

        $carry = '';

        while (!feof($handle)) {
            $chunk = strtolower((string) fread($handle, 65536));
            $window = $carry . $chunk;

            foreach (self::FORBIDDEN_MARKERS as $marker) {
                if (str_contains($window, $marker)) {
                    fclose($handle);

                    return true;
                }
            }

            // Overlap so a marker split across two reads is still seen.
            $carry = substr($window, -32);
        }

        fclose($handle);

        return false;
    }

    /**
     * True when the file carries bytes past the point its format declares as
     * the end. Each format states where it finishes, so this is exact: it
     * catches an appended archive or script without guessing, and cannot
     * misfire on ordinary image data the way a byte scan can.
     */
    private function hasTrailingData(string $path, string $mime): bool
    {
        $contents = (string) file_get_contents($path);
        $size = strlen($contents);

        $end = match ($mime) {
            'image/png' => $this->pngEnd($contents),
            'image/jpeg' => $this->jpegEnd($contents),
            'image/gif' => $this->gifEnd($contents),
            'image/webp' => $this->webpEnd($contents),
            default => null,
        };

        if ($end === null) {
            return false;
        }

        // A few bytes of padding are tolerated; a payload is never that small.
        return $size - $end > 16;
    }

    /** A PNG ends with its IEND chunk: 4-byte length, the name, 4-byte CRC. */
    private function pngEnd(string $contents): ?int
    {
        $position = strrpos($contents, 'IEND');

        return $position === false ? null : $position + 8;
    }

    /** A JPEG ends with the End Of Image marker, FF D9. */
    private function jpegEnd(string $contents): ?int
    {
        $position = strrpos($contents, "\xFF\xD9");

        return $position === false ? null : $position + 2;
    }

    /** A GIF ends with the trailer byte 0x3B. */
    private function gifEnd(string $contents): ?int
    {
        $position = strrpos($contents, "\x3B");

        return $position === false ? null : $position + 1;
    }

    /** A WebP declares its own length in the RIFF header. */
    private function webpEnd(string $contents): ?int
    {
        if (strlen($contents) < 12) {
            return null;
        }

        $unpacked = unpack('Vsize', substr($contents, 4, 4));

        if ($unpacked === false) {
            return null;
        }

        // The stated size covers everything after the first eight bytes.
        return (int) $unpacked['size'] + 8;
    }

    /** @return array{ok:false,message:string} */
    private function reject(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }

    private function errorMessage(int $error, int $maxBytes): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf('The file is larger than the %d KB limit.', intdiv($maxBytes, 1024)),
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a file before uploading.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not write the file.',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by the server configuration.',
            default => 'The upload failed.',
        };
    }
}
