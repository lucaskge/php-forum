<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small rule-based validator. All validation in the application is server-side
 * — the front end ships no JavaScript, so nothing is ever trusted from it.
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $data;

    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,string> */
    private array $labels = [];

    /** @param array<string,mixed> $data */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** @param array<string,mixed> $data */
    public static function make(array $data): self
    {
        return new self($data);
    }

    public function label(string $field, string $label): self
    {
        $this->labels[$field] = $label;

        return $this;
    }

    private function name(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function value(string $field): string
    {
        $value = $this->data[$field] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function fail(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function required(string $field): self
    {
        if ($this->value($field) === '') {
            $this->fail($field, $this->name($field) . ' is required.');
        }

        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        $value = $this->value($field);

        if ($value !== '' && mb_strlen($value, 'UTF-8') < $min) {
            $this->fail($field, sprintf('%s must be at least %d characters.', $this->name($field), $min));
        }

        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        $value = $this->value($field);

        if (mb_strlen($value, 'UTF-8') > $max) {
            $this->fail($field, sprintf('%s may not exceed %d characters.', $this->name($field), $max));
        }

        return $this;
    }

    public function between(string $field, int $min, int $max): self
    {
        return $this->minLength($field, $min)->maxLength($field, $max);
    }

    public function email(string $field): self
    {
        $value = $this->value($field);

        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail($field, 'Enter a valid e-mail address.');
        }

        return $this;
    }

    public function username(string $field): self
    {
        $value = $this->value($field);

        if ($value !== '' && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{2,31}$/', $value) !== 1) {
            $this->fail($field, 'Usernames are 3–32 characters: letters, digits, dot, dash or underscore, starting with a letter or digit.');
        }

        return $this;
    }

    public function matches(string $field, string $otherField): self
    {
        if ($this->value($field) !== $this->value($otherField)) {
            $this->fail($field, $this->name($field) . ' does not match.');
        }

        return $this;
    }

    public function password(string $field): self
    {
        $value = $this->value($field);
        $min = (int) Config::get('security.password.min_length', 10);

        if ($value === '') {
            return $this;
        }

        if (mb_strlen($value, 'UTF-8') < $min) {
            return $this->fail($field, sprintf('Passwords must be at least %d characters long.', $min));
        }

        if (preg_match('/[a-zA-Z]/', $value) !== 1 || preg_match('/[0-9]/', $value) !== 1) {
            $this->fail($field, 'Passwords must contain at least one letter and one digit.');
        }

        return $this;
    }

    /** @param array<int,string|int> $allowed */
    public function in(string $field, array $allowed): self
    {
        $value = $this->value($field);

        if ($value !== '' && !in_array($value, array_map('strval', $allowed), true)) {
            $this->fail($field, 'The selected ' . strtolower($this->name($field)) . ' is not valid.');
        }

        return $this;
    }

    public function integer(string $field): self
    {
        $value = $this->value($field);

        if ($value !== '' && preg_match('/^-?\d+$/', $value) !== 1) {
            $this->fail($field, $this->name($field) . ' must be a whole number.');
        }

        return $this;
    }

    public function date(string $field): self
    {
        $value = $this->value($field);

        if ($value !== '' && Dates::parse($value) === null) {
            $this->fail($field, $this->name($field) . ' must be a valid date (YYYY-MM-DD).');
        }

        return $this;
    }

    public function slug(string $field): self
    {
        $value = $this->value($field);

        if ($value !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            $this->fail($field, $this->name($field) . ' may only contain lowercase letters, digits and dashes.');
        }

        return $this;
    }

    public function when(bool $condition, callable $callback): self
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $error) {
            return $error;
        }

        return null;
    }
}
