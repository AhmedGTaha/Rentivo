<?php

declare(strict_types=1);

namespace Rentivo\Validation;

use Rentivo\Support\Currency;
use Rentivo\Support\DateTimeHelper;

/**
 * Small rule-based validator used by every controller that accepts input.
 *
 * Rules are declared as pipe-separated strings, e.g.
 *   'name' => 'required|max:120'
 *   'daily_rate' => 'required|money|min_fils:1'
 *
 * The validator returns sanitised values so controllers never re-read raw
 * request input after validation.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
        $this->run();
    }

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
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

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function value(string $field, mixed $default = null): mixed
    {
        return $this->validated[$field] ?? $default;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = array_filter(explode('|', $ruleString));
            $raw = $this->data[$field] ?? null;

            $value = is_string($raw) ? trim($raw) : $raw;
            $isEmpty = $value === null || $value === '' || $value === [];

            $required = in_array('required', $rules, true);
            $nullable = in_array('nullable', $rules, true);

            if ($isEmpty) {
                if ($required) {
                    $this->addError($field, $this->label($field) . ' is required.');
                    continue;
                }

                $this->validated[$field] = $nullable ? null : $value;
                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['required', 'nullable'], true)) {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                $result = $this->applyRule($field, (string) $name, $parameter, $value);

                if ($result === false) {
                    continue 2;
                }

                if ($result !== true) {
                    $value = $result;
                }
            }

            $this->validated[$field] = $value;
        }
    }

    /**
     * @return true|false|mixed true = passed, false = failed, other = coerced value
     */
    private function applyRule(string $field, string $name, ?string $parameter, mixed $value): mixed
    {
        $label = $this->label($field);

        switch ($name) {
            case 'string':
                return is_string($value) ? true : $this->fail($field, $label . ' must be text.');

            case 'max':
                return mb_strlen((string) $value) <= (int) $parameter
                    ? true
                    : $this->fail($field, $label . ' may not exceed ' . $parameter . ' characters.');

            case 'min':
                return mb_strlen((string) $value) >= (int) $parameter
                    ? true
                    : $this->fail($field, $label . ' must be at least ' . $parameter . ' characters.');

            case 'email':
                $email = filter_var((string) $value, FILTER_VALIDATE_EMAIL);
                return $email !== false
                    ? strtolower((string) $email)
                    : $this->fail($field, $label . ' must be a valid email address.');

            case 'int':
                if (!preg_match('/^-?\d+$/', (string) $value)) {
                    return $this->fail($field, $label . ' must be a whole number.');
                }
                return (int) $value;

            case 'min_value':
                return (int) $value >= (int) $parameter
                    ? true
                    : $this->fail($field, $label . ' must be at least ' . $parameter . '.');

            case 'max_value':
                return (int) $value <= (int) $parameter
                    ? true
                    : $this->fail($field, $label . ' may not be greater than ' . $parameter . '.');

            case 'money':
                $fils = Currency::tryToFils((string) $value);
                return $fils !== null
                    ? $fils
                    : $this->fail($field, $label . ' must be a valid amount such as 25.500.');

            case 'min_fils':
                return (int) $value >= (int) $parameter
                    ? true
                    : $this->fail($field, $label . ' must be greater than zero.');

            case 'in':
                $allowed = explode(',', (string) $parameter);
                return in_array((string) $value, $allowed, true)
                    ? true
                    : $this->fail($field, $label . ' is not a valid selection.');

            case 'boolean':
                return in_array((string) $value, ['1', 'on', 'true', 'yes'], true);

            case 'datetime':
                $parsed = DateTimeHelper::fromInput((string) $value);
                return $parsed !== null
                    ? $parsed
                    : $this->fail($field, $label . ' must be a valid date and time.');

            case 'date':
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value, DateTimeHelper::utc());
                return $parsed !== false
                    ? $parsed
                    : $this->fail($field, $label . ' must be a valid date.');

            case 'phone':
                return preg_match('/^\+?[0-9 ()-]{6,25}$/', (string) $value) === 1
                    ? true
                    : $this->fail($field, $label . ' must be a valid phone number.');

            case 'slug':
                return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value) === 1
                    ? true
                    : $this->fail($field, $label . ' may only contain lowercase letters, numbers and hyphens.');

            case 'hex_color':
                return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) === 1
                    ? strtolower((string) $value)
                    : $this->fail($field, $label . ' must be a hex colour such as #1A1A1A.');

            case 'url':
                return filter_var((string) $value, FILTER_VALIDATE_URL) !== false
                    ? true
                    : $this->fail($field, $label . ' must be a valid URL.');

            default:
                return true;
        }
    }

    private function fail(string $field, string $message): false
    {
        $this->addError($field, $message);

        return false;
    }
}
