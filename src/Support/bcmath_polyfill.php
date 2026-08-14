<?php

declare(strict_types=1);

/**
 * Minimal bcmath polyfill for local/dev environments where the ext-bcmath
 * extension isn't installed (e.g. this sandbox). Production Hostinger PHP
 * ships ext-bcmath (required in composer.json), so these definitions are
 * inert there - guarded by function_exists() so they never redeclare the
 * real extension's functions.
 *
 * Internally represents decimals as integers scaled by 10^8 (BTC precision),
 * which is exact for all USD/BTC amounts this app handles and avoids float
 * rounding entirely for add/sub/compare.
 */

if (!function_exists('bcadd')) {
    define('BC_POLYFILL_SCALE', 8);

    function _bc_to_scaled(string $num): int
    {
        $num = trim($num);
        $neg = str_starts_with($num, '-');
        $num = ltrim($num, '+-');
        if (!str_contains($num, '.')) {
            $num .= '.';
        }
        [$int, $frac] = explode('.', $num, 2);
        $int = $int === '' ? '0' : $int;
        $frac = str_pad(substr($frac, 0, BC_POLYFILL_SCALE), BC_POLYFILL_SCALE, '0');
        $scaled = (int) ($int . $frac);
        return $neg ? -$scaled : $scaled;
    }

    function _bc_from_scaled(int $scaled, int $scale): string
    {
        $neg = $scaled < 0;
        $scaled = abs($scaled);
        $str = str_pad((string) $scaled, BC_POLYFILL_SCALE + 1, '0', STR_PAD_LEFT);
        $int = substr($str, 0, -BC_POLYFILL_SCALE);
        $frac = substr($str, -BC_POLYFILL_SCALE);
        $frac = substr($frac, 0, $scale);
        $frac = str_pad($frac, $scale, '0');
        $result = $scale > 0 ? "{$int}.{$frac}" : $int;
        $isZero = (int) $int === 0 && (int) $frac === 0;
        return ($neg && !$isZero) ? "-{$result}" : $result;
    }

    function bcadd(string $a, string $b, int $scale = 2): string
    {
        return _bc_from_scaled(_bc_to_scaled($a) + _bc_to_scaled($b), $scale);
    }

    function bcsub(string $a, string $b, int $scale = 2): string
    {
        return _bc_from_scaled(_bc_to_scaled($a) - _bc_to_scaled($b), $scale);
    }

    function bcmul(string $a, string $b, int $scale = 2): string
    {
        $result = (_bc_to_scaled($a) * _bc_to_scaled($b)) / (10 ** BC_POLYFILL_SCALE);
        return _bc_from_scaled((int) round($result), $scale);
    }

    function bcdiv(string $a, string $b, int $scale = 2): string
    {
        $bi = _bc_to_scaled($b);
        if ($bi === 0) {
            throw new \DivisionByZeroError('Division by zero');
        }
        $result = (_bc_to_scaled($a) * (10 ** BC_POLYFILL_SCALE)) / $bi;
        return _bc_from_scaled((int) round($result), $scale);
    }

    function bccomp(string $a, string $b, int $scale = 2): int
    {
        $factor = 10 ** (BC_POLYFILL_SCALE - min($scale, BC_POLYFILL_SCALE));
        $ai = intdiv(_bc_to_scaled($a), $factor);
        $bi = intdiv(_bc_to_scaled($b), $factor);
        return $ai <=> $bi;
    }
}
