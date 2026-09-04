<?php

declare(strict_types=1);

namespace Rentivo\Support;

/**
 * One-request flash messages and form repopulation state.
 */
final class Flash
{
    private const KEY = '_flash';
    private const OLD = '_old_input';
    private const ERRORS = '_errors';

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

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    /** @return list<array{type:string,message:string}> */
    public static function pull(): array
    {
        return Session::pull(self::KEY, []) ?: [];
    }

    /** @param array<string,string> $errors */
    public static function withErrors(array $errors): void
    {
        Session::put(self::ERRORS, $errors);
    }

    /** @return array<string,string> */
    public static function pullErrors(): array
    {
        return Session::pull(self::ERRORS, []) ?: [];
    }

    /** @param array<string,mixed> $input */
    public static function withInput(array $input): void
    {
        unset($input['_token'], $input['password']);
        Session::put(self::OLD, $input);
    }

    /** @return array<string,mixed> */
    public static function pullOld(): array
    {
        return Session::pull(self::OLD, []) ?: [];
    }
}
