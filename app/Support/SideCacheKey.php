<?php

namespace App\Support;

class SideCacheKey
{
    private const MAX_VALUE_LENGTH = 72;

    public static function path(array $segments): string
    {
        $parts = ['side_cache'];

        foreach ($segments as $segment) {
            if (is_string($segment)) {
                $trimmed = trim($segment);
                if ($trimmed === 'side_cache' || $trimmed === 'side-cache') {
                    continue;
                }

                if (str_starts_with($trimmed, 'side_cache:') || str_starts_with($trimmed, 'side-cache:')) {
                    $segment = substr($trimmed, strpos($trimmed, ':') + 1);
                }
            }

            $normalized = self::normalizeSegment($segment);
            if ($normalized !== null) {
                $parts[] = $normalized;
            }
        }

        return implode(':', $parts);
    }

    public static function pairs(array $segments, array $pairs): string
    {
        $parts = [self::path($segments)];

        foreach ($pairs as $label => $value) {
            $segment = self::pairSegment($label, $value);
            if ($segment !== null) {
                $parts[] = $segment;
            }
        }

        return implode(':', $parts);
    }

    public static function filters(array $segments, array $filters, array $preferredOrder = []): string
    {
        $ordered = [];
        $remaining = $filters;

        foreach ($preferredOrder as $key) {
            if (array_key_exists($key, $remaining)) {
                $ordered[$key] = $remaining[$key];
                unset($remaining[$key]);
            }
        }

        ksort($remaining);

        return self::pairs($segments, $ordered + $remaining);
    }

    public static function pairSegment(string|int $label, mixed $value): ?string
    {
        if (is_int($label)) {
            return self::normalizeSegment($value);
        }

        $normalizedLabel = self::normalizeLabel($label);
        if ($normalizedLabel === null) {
            return null;
        }

        $normalizedValue = self::normalizeValue($value);
        if ($normalizedValue === null) {
            return null;
        }

        return $normalizedLabel . '-' . $normalizedValue;
    }

    private static function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            if ($value === []) {
                return 'all';
            }

            if (self::isAssoc($value)) {
                $segments = [];
                ksort($value);
                foreach ($value as $key => $item) {
                    $pair = self::pairSegment((string) $key, $item);
                    if ($pair !== null) {
                        $segments[] = $pair;
                    }
                }

                if ($segments === []) {
                    return null;
                }

                return self::compactSegments($segments);
            }

            $items = [];
            foreach ($value as $item) {
                $normalizedItem = self::normalizeValue($item);
                if ($normalizedItem !== null) {
                    $items[] = $normalizedItem;
                }
            }

            $items = array_values(array_unique($items));
            sort($items, SORT_STRING);

            if ($items === []) {
                return 'all';
            }

            return self::compactSegments($items);
        }

        return self::normalizeSegment($value);
    }

    private static function normalizeSegment(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return self::normalizeValue($value);
        }

        $segment = strtolower(trim((string) $value));
        if ($segment === '') {
            return null;
        }

        $segment = str_replace(['\\', '/', '_', ' '], '-', $segment);
        $segment = preg_replace('/[^a-z0-9\-]+/', '-', $segment);
        $segment = preg_replace('/-+/', '-', $segment);
        $segment = trim($segment, '-');

        return $segment === '' ? null : $segment;
    }

    private static function normalizeLabel(string $label): ?string
    {
        return self::normalizeSegment($label);
    }

    private static function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private static function compactSegments(array $segments): string
    {
        $segments = array_values(array_filter($segments));
        $joined = implode('-', $segments);

        if (strlen($joined) <= self::MAX_VALUE_LENGTH) {
            return $joined;
        }

        $kept = [];
        $remaining = count($segments);

        foreach ($segments as $segment) {
            $candidate = implode('-', array_merge($kept, [$segment]));
            $suffix = '-plus-' . max(0, $remaining - 1);

            if ($candidate !== '' && strlen($candidate . $suffix) > self::MAX_VALUE_LENGTH) {
                break;
            }

            $kept[] = $segment;
            $remaining--;
        }

        if ($kept === []) {
            $first = substr($segments[0], 0, max(1, self::MAX_VALUE_LENGTH - 8));
            return $first . '-plus-' . (count($segments) - 1);
        }

        if ($remaining > 0) {
            $kept[] = 'plus-' . $remaining;
        }

        return implode('-', $kept);
    }
}
