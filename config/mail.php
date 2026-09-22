<?php

declare(strict_types=1);

use App\Support\Env;

return [
    // `log` writes messages to storage/logs/mail.log which is what you want in
    // development; `mail` hands the message to PHP's built-in mail() function.
    'transport' => Env::get('MAIL_TRANSPORT', 'log'),
    'from' => Env::get('MAIL_FROM', 'no-reply@localhost'),
    'from_name' => Env::get('MAIL_FROM_NAME', 'Coldwire'),
];
