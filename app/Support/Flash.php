<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One-shot messages surviving a redirect. Types map onto the alert styles in
 * the theme: success | error | warning | info.
 */
final class Flash
{
    private const KEY = '__flash';

    public static function add(string $type, string $message): void
    {
        $messages = Session::get(self::KEY, []);
        $messages[] = ['type' => $type, 'message' => $message];
        Session::put(self::KEY, $messages);
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function drain(): array
    {
        return Session::pull(self::KEY, []) ?: [];
    }

    /**
     * Keeps a rejected form's input so the redirect target can repopulate it.
     *
     * @param array<string,mixed> $input
     * @param array<string,string> $errors
     */
    public static function withInput(array $input, array $errors = []): void
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_token']);

        Session::put('__old', $input);
        Session::put('__errors', $errors);
    }

    /** @return array<string,mixed> */
    public static function oldInput(): array
    {
        return Session::pull('__old', []) ?: [];
    }

    /** @return array<string,string> */
    public static function errors(): array
    {
        return Session::pull('__errors', []) ?: [];
    }
}
