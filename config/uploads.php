<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'avatars' => [
        'directory' => BASE_PATH . '/public/uploads/avatars',
        'url_prefix' => '/uploads/avatars',

        'allowed_mime' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ],

        // What a member may send. A photograph straight off a phone is several
        // megabytes and thousands of pixels wide; it is accepted and scaled,
        // because the file is re-encoded on the way in regardless.
        'max_bytes' => (int) Env::get('UPLOAD_MAX_AVATAR_BYTES', '4194304'),
        'max_source_width' => 8000,
        'max_source_height' => 8000,

        // Ceiling on the decoded canvas, checked before anything decodes, so a
        // small file declaring an enormous image cannot exhaust memory.
        'max_pixels' => 40000000,

        // What is stored. Larger images are scaled down to fit inside this.
        'max_width' => 512,
        'max_height' => 512,
    ],
];
