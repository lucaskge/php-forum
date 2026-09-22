<?php

declare(strict_types=1);

use App\Support\Validator;
use Tests\Assert;

return [
    'required rejects empty values' => static function (): void {
        Assert::true(Validator::make(['a' => ''])->required('a')->fails());
        Assert::true(Validator::make(['a' => '   '])->required('a')->fails(), 'whitespace is not a value');
        Assert::true(Validator::make(['a' => 'x'])->required('a')->passes());
    },

    'length bounds are inclusive' => static function (): void {
        Assert::true(Validator::make(['a' => 'abc'])->between('a', 3, 3)->passes());
        Assert::true(Validator::make(['a' => 'ab'])->between('a', 3, 5)->fails());
        Assert::true(Validator::make(['a' => 'abcdef'])->between('a', 3, 5)->fails());
    },

    'usernames follow the board rules' => static function (): void {
        foreach (['grepwire', 'pale_socket', 'a.b-c', 'ab1'] as $valid) {
            Assert::true(Validator::make(['u' => $valid])->username('u')->passes(), $valid . ' should be valid');
        }

        foreach (['ab', '_leading', 'has space', 'wäy', str_repeat('x', 33)] as $invalid) {
            Assert::true(Validator::make(['u' => $invalid])->username('u')->fails(), $invalid . ' should be rejected');
        }
    },

    'passwords need length and a digit' => static function (): void {
        Assert::true(Validator::make(['p' => 'short1'])->password('p')->fails(), 'too short');
        Assert::true(Validator::make(['p' => 'alllettersonly'])->password('p')->fails(), 'no digit');
        Assert::true(Validator::make(['p' => '1234567890123'])->password('p')->fails(), 'no letter');
        Assert::true(Validator::make(['p' => 'coldwire-admin-1'])->password('p')->passes());
    },

    'confirmation must match' => static function (): void {
        $data = ['password' => 'coldwire-1234', 'password_confirmation' => 'coldwire-1234'];
        Assert::true(Validator::make($data)->matches('password_confirmation', 'password')->passes());

        $data['password_confirmation'] = 'different-1234';
        Assert::true(Validator::make($data)->matches('password_confirmation', 'password')->fails());
    },

    'email validation' => static function (): void {
        Assert::true(Validator::make(['e' => 'a@b.co'])->email('e')->passes());
        Assert::true(Validator::make(['e' => 'not-an-email'])->email('e')->fails());
    },

    'in() restricts to an allow-list' => static function (): void {
        Assert::true(Validator::make(['s' => 'spam'])->in('s', ['spam', 'abuse'])->passes());
        Assert::true(Validator::make(['s' => 'other'])->in('s', ['spam', 'abuse'])->fails());
    },

    'slug rule' => static function (): void {
        Assert::true(Validator::make(['s' => 'web-development'])->slug('s')->passes());
        Assert::true(Validator::make(['s' => 'Web Development'])->slug('s')->fails());
        Assert::true(Validator::make(['s' => 'trailing-'])->slug('s')->fails());
    },

    'errors keep the first message per field' => static function (): void {
        $validator = Validator::make(['a' => ''])->required('a')->minLength('a', 5);

        Assert::same(1, count($validator->errors()));
        Assert::contains('required', (string) $validator->firstError());
    },

    'labels appear in messages' => static function (): void {
        $validator = Validator::make(['identifier' => ''])->label('identifier', 'Username or e-mail')->required('identifier');

        Assert::contains('Username or e-mail', (string) $validator->firstError());
    },
];
