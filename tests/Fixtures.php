<?php

declare(strict_types=1);

namespace Tests;

/**
 * Throwaway files for the upload tests. Everything lands in the system temp
 * directory and is removed when the run ends.
 */
final class Fixtures
{
    /** @var array<int,string> */
    private static array $files = [];

    public static function write(string $contents, string $extension = 'bin'): string
    {
        $path = sys_get_temp_dir() . '/coldwire-test-' . bin2hex(random_bytes(6)) . '.' . $extension;
        file_put_contents($path, $contents);
        self::$files[] = $path;

        return $path;
    }

    /** A real, decodable PNG of the requested size. */
    public static function png(int $width, int $height): string
    {
        return self::write(self::pngBytes($width, $height), 'png');
    }

    /** A real PNG carrying a tEXt chunk, the usual metadata hiding place. */
    public static function pngWithComment(string $comment): string
    {
        $bytes = self::pngBytes(16, 16);
        $chunk = self::chunk('tEXt', "Comment\x00" . $comment);

        // Insert before IEND so the file stays a structurally valid PNG.
        $position = strrpos($bytes, self::chunk('IEND', ''));
        $bytes = $position === false
            ? $bytes . $chunk
            : substr($bytes, 0, $position) . $chunk . substr($bytes, $position);

        return self::write($bytes, 'png');
    }

    /**
     * A PNG whose pixels are noise, so the compressed data is effectively
     * random — the same shape as a real photograph, and the case that a
     * short-marker byte scan rejects by chance.
     */
    public static function photoPng(int $width = 220, int $height = 220): string
    {
        $raw = '';

        for ($y = 0; $y < $height; $y++) {
            $row = '';

            for ($x = 0; $x < $width; $x++) {
                $row .= pack('CCC', random_int(0, 255), random_int(0, 255), random_int(0, 255));
            }

            $raw .= "\x00" . $row;
        }

        $bytes = "\x89PNG\r\n\x1a\n"
            . self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . self::chunk('IDAT', (string) gzcompress($raw, 6))
            . self::chunk('IEND', '');

        return self::write($bytes, 'png');
    }

    /** A minimal but structurally valid JPEG. */
    public static function jpeg(): string
    {
        $image = imagecreatetruecolor(40, 40);
        imagefilledrectangle($image, 0, 0, 40, 40, (int) imagecolorallocate($image, 30, 60, 90));

        ob_start();
        imagejpeg($image, null, 80);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return self::write($bytes, 'jpg');
    }

    private static function pngBytes(int $width, int $height): string
    {
        $raw = '';

        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00" . str_repeat("\x28\x3c\x50", $width);
        }

        return "\x89PNG\r\n\x1a\n"
            . self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . self::chunk('IDAT', (string) gzcompress($raw))
            . self::chunk('IEND', '');
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    public static function cleanup(): void
    {
        foreach (self::$files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        self::$files = [];
    }
}
