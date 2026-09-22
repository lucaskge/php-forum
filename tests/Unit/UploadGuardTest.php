<?php

declare(strict_types=1);

use App\Services\UploadGuard;
use Tests\Assert;
use Tests\Fixtures;

/**
 * The upload rules, exercised against real files on disk.
 *
 * Each case is something an attacker actually tries: a script renamed to .png,
 * a real image with a payload appended, a compound extension, a file lying
 * about its type, and an image that claims a canvas large enough to exhaust
 * memory when decoded.
 */
$policy = [
    'max_bytes' => 524288,
    'allowed_mime' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
    'max_width' => 512,
    'max_height' => 512,
    'max_pixels' => 512 * 512,
];

return [
    'a genuine png is accepted' => static function () use ($policy): void {
        $file = Fixtures::png(32, 32);
        $result = (new UploadGuard())->inspectFile($file, 'avatar.png', $policy);

        Assert::true($result['ok'], (string) ($result['message'] ?? ''));
        Assert::same('image/png', $result['mime'] ?? '');
        Assert::same(32, $result['width'] ?? 0);
    },

    'an ordinary photograph is accepted' => static function () use ($policy): void {
        // Compressed photographic data is effectively random, so short byte
        // sequences turn up in it by chance. A scan for two- or three-byte
        // markers rejects perfectly good images; this is the case that proves
        // it does not happen.
        $file = Fixtures::photoPng(220, 220);
        $result = (new UploadGuard())->inspectFile($file, 'photo.png', $policy);

        Assert::true($result['ok'], 'a real photograph must upload: ' . (string) ($result['message'] ?? ''));
    },

    'ten photographs in a row are all accepted' => static function () use ($policy): void {
        $guard = new UploadGuard();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $result = $guard->inspectFile(Fixtures::photoPng(160, 160), 'photo.png', $policy);

            Assert::true($result['ok'], 'attempt ' . $attempt . ': ' . (string) ($result['message'] ?? ''));
        }
    },

    'a jpeg photograph is accepted' => static function () use ($policy): void {
        $result = (new UploadGuard())->inspectFile(Fixtures::jpeg(), 'photo.jpg', $policy);

        Assert::true($result['ok'], (string) ($result['message'] ?? ''));
        Assert::same('image/jpeg', $result['mime'] ?? '');
    },

    'a jpeg with data appended after its end marker is refused' => static function () use ($policy): void {
        $file = Fixtures::jpeg();
        file_put_contents($file, str_repeat('PAYLOAD', 200), FILE_APPEND);

        $result = (new UploadGuard())->inspectFile($file, 'photo.jpg', $policy);

        Assert::false($result['ok'], 'appended data must be caught structurally');
        Assert::contains('appended', (string) $result['message']);
    },

    'a php script renamed to .png is refused' => static function () use ($policy): void {
        $file = Fixtures::write("<?php system(\$_GET['c']); ?>");
        $result = (new UploadGuard())->inspectFile($file, 'avatar.png', $policy);

        Assert::false($result['ok'], 'a script must never be accepted');
        Assert::contains('not one of the accepted', (string) $result['message']);
    },

    'a real png with a php payload appended is refused' => static function () use ($policy): void {
        // The classic polyglot: valid header, valid image, script after IEND.
        $file = Fixtures::png(16, 16);
        file_put_contents($file, "\n<?php echo shell_exec(\$_GET['c']); ?>", FILE_APPEND);

        $result = (new UploadGuard())->inspectFile($file, 'avatar.png', $policy);

        Assert::false($result['ok'], 'appended code must be caught even behind a valid image');
    },

    'a payload hidden in image metadata is refused' => static function () use ($policy): void {
        $file = Fixtures::pngWithComment('<?php eval($_POST[0]); ?>');
        $result = (new UploadGuard())->inspectFile($file, 'avatar.png', $policy);

        Assert::false($result['ok'], 'a tEXt chunk is still part of the file');
    },

    'a compound extension is refused on the dangerous half' => static function () use ($policy): void {
        $file = Fixtures::png(16, 16);

        foreach (['avatar.php.png', 'avatar.phtml.png', 'avatar.PHP.png', 'shell.phar.png'] as $name) {
            $result = (new UploadGuard())->inspectFile($file, $name, $policy);

            Assert::false($result['ok'], $name . ' must be refused');
        }
    },

    'an svg is refused even though it is an image' => static function () use ($policy): void {
        $file = Fixtures::write('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $result = (new UploadGuard())->inspectFile($file, 'avatar.svg', $policy);

        Assert::false($result['ok'], 'svg carries script and is not on the allow-list');
    },

    'an html file claiming to be a gif is refused' => static function () use ($policy): void {
        $file = Fixtures::write("GIF89a<html><script>alert(1)</script></html>");
        $result = (new UploadGuard())->inspectFile($file, 'avatar.gif', $policy);

        Assert::false($result['ok'], 'a valid signature is not enough on its own');
    },

    'an oversized image is refused' => static function () use ($policy): void {
        $file = Fixtures::png(600, 40);
        $result = (new UploadGuard())->inspectFile($file, 'avatar.png', $policy);

        Assert::false($result['ok']);
        Assert::contains('at most', (string) $result['message']);
    },

    'an empty file is refused' => static function () use ($policy): void {
        $file = Fixtures::write('');
        $result = (new UploadGuard())->inspectFile($file, 'avatar.png', $policy);

        Assert::false($result['ok']);
    },

    'a file above the size limit is refused' => static function () use ($policy): void {
        $file = Fixtures::png(64, 64);
        $small = array_merge($policy, ['max_bytes' => 10]);

        $result = (new UploadGuard())->inspectFile($file, 'avatar.png', $small);

        Assert::false($result['ok']);
        Assert::contains('smaller than', (string) $result['message']);
    },

    'a missing file is refused' => static function () use ($policy): void {
        $result = (new UploadGuard())->inspectFile('/tmp/does-not-exist-' . bin2hex(random_bytes(4)), 'x.png', $policy);

        Assert::false($result['ok']);
    },

    'generated names never carry an executable extension' => static function (): void {
        $guard = new UploadGuard();

        foreach (['php', 'PHP', 'phtml', 'svg', 'html', 'js', '', '../../etc/passwd'] as $requested) {
            $name = $guard->safeFilename(7, $requested);

            Assert::same(1, preg_match('/^7-[0-9a-f]{24}\.[a-z0-9]{2,5}$/', $name), 'unexpected name: ' . $name);
            Assert::notContains('php', $name);
            Assert::notContains('/', $name);
            Assert::notContains('..', $name);
        }
    },

    'generated names are unique' => static function (): void {
        $guard = new UploadGuard();

        Assert::true($guard->safeFilename(1, 'png') !== $guard->safeFilename(1, 'png'));
    },
];
