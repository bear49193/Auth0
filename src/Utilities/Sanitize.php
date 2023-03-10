<?php

declare(strict_types=1);

namespace Auth0\WordPress\Utilities;

final class Sanitize
{
    public static function alphanumeric(string|null $item, string $allowed = 'A-Za-z0-9 '): string|null
    {
        if ($item === '') {
            return $item;
        }

        if ($item === null) {
            return $item;
        }

        return preg_replace('/[^' . $allowed . ']/', '', $item);
    }

    /**
     * @return mixed[]
     */
    public static function arrayUnique(array $array): array
    {
        if ($array === []) {
            return [];
        }

        return array_values(array_filter(array_unique(array_map('trim', $array))));
    }

    public static function integer(string $string, int $max = 10, int $min = 0): ?int
    {
        $string = trim(\sanitize_text_field($string));

        if ($string === '') {
            return null;
        }

        if (! is_numeric($string)) {
            return null;
        }

        $int = (int) $string;

        if ($int < $min) {
            return 0;
        }

        if ($int > $max) {
            return $max;
        }

        return $int;
    }

    public static function boolean(string $string): ?string
    {
        $string = trim(\sanitize_text_field($string));

        if ($string === '') {
            return null;
        }

        if ($string === 'true') {
            return 'true';
        }

        if ($string === '1') {
            return 'true';
        }

        return 'false';
    }

    public static function string(string $string): ?string
    {
        $string = trim(\sanitize_text_field($string));

        if ($string === '') {
            return null;
        }

        return $string;
    }

    public static function textarea(string $string): ?string
    {
        $string = trim(\sanitize_textarea_field($string));

        if ($string === '') {
            return null;
        }

        return $string;
    }

    public static function cookiePath(string $path): string
    {
        $path = trim(\sanitize_text_field($path));
        $path = trim(str_replace(['../', './'], '', $path));
        $path = trim($path, "/ \t\n\r\0\x0B");

        if (strlen($path) !== 0) {
            return '/' . $path;
        }

        return $path;
    }

    public static function domain(string $path): ?string
    {
        $path = self::string($path);

        if (is_string($path) && $path === '') {
            return null;
        }

        if ($path === null) {
            return null;
        }

        $scheme = parse_url($path, PHP_URL_SCHEME);

        if ($scheme === null) {
            return self::domain('http://' . $path);
        }

        $host = parse_url($path, PHP_URL_HOST);
        if (! is_string($host)) {
            return null;
        }

        if ($host === '') {
            return null;
        }

        $parts = explode('.', $host);

        if (count($parts) < 2) {
            return null;
        }

        $tld = end($parts);

        if (strlen($tld) < 2) {
            return null;
        }

        return $host;
    }
}
