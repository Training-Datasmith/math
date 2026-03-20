<?php

declare (strict_types=1);
namespace Brick\Math\Internal;

use Brick\Math\Rounding_Mode;
use function ltrim;
use function rtrim;
use function str_pad;
use const STR_PAD_LEFT;
use function str_repeat;
use function strlen;
use function substr;
/**
 * Shared helper for decimal operations.
 *
 * @internal
 */
final class Decimal_Helper
{
    private function __construct()
    {
    }
    /**
     * Computes the scale needed to represent the exact decimal result of a reduced fraction.
     *
     * Returns null if the denominator has prime factors other than 2 or 5.
     *
     * @param string $denominator The denominator of the reduced fraction. Must be strictly positive.
     *
     * @return non-negative-int|null
     *
     * @pure
     */
    public static function compute_scale_from_reduced_fraction_denominator(string $denominator): ?int
    {
        $calculator = Calculator_Registry::get();
        $d = rtrim($denominator, '0');
        /** @var non-negative-int $scale rtrim can only shorten a string */
        $scale = strlen($denominator) - strlen($d);
        foreach ([5, 2] as $prime) {
            for (;;) {
                $last_digit = (int) $d[-1];
                if ($last_digit % $prime !== 0) {
                    break;
                }
                $d = $calculator->div_q($d, (string) $prime);
                $scale++;
            }
        }
        return $d === '1' ? $scale : null;
    }
    /**
     * Scales an unscaled decimal value to the requested scale.
     *
     * Returns null when rounding is necessary and the rounding mode is Unnecessary.
     *
     * @param string       $value        The unscaled value.
     * @param int          $currentScale The current scale.
     * @param int          $targetScale  The target scale.
     * @param RoundingMode $roundingMode The rounding mode.
     *
     * @return string|null The unscaled value at the target scale, or null if RoundingMode::Unnecessary is used and rounding is necessary.
     *
     * @pure
     */
    public static function scale(string $value, int $current_scale, int $target_scale, Rounding_Mode $rounding_mode): ?string
    {
        $scaled = self::try_scale_exactly($value, $current_scale, $target_scale);
        if ($scaled !== null) {
            return $scaled;
        }
        if ($rounding_mode === Rounding_Mode::Unnecessary) {
            return null;
        }
        $divisor = '1' . str_repeat('0', $current_scale - $target_scale);
        return Calculator_Registry::get()->div_round($value, $divisor, $rounding_mode);
    }
    /**
     * Adds leading zeros if necessary to represent the full decimal number.
     *
     * @param string $value The unscaled value.
     * @param int    $scale The current scale.
     *
     * @pure
     */
    public static function pad_unscaled_value(string $value, int $scale): string
    {
        $target_length = $scale + 1;
        $negative = $value[0] === '-';
        $length = strlen($value);
        if ($negative) {
            $length--;
        }
        if ($length >= $target_length) {
            return $value;
        }
        if ($negative) {
            $value = substr($value, 1);
        }
        $value = str_pad($value, $target_length, '0', STR_PAD_LEFT);
        if ($negative) {
            return '-' . $value;
        }
        return $value;
    }
    /**
     * Tries to scale exactly without rounding, returning null when rounding would be required.
     *
     * @param string $value        The unscaled value.
     * @param int    $currentScale The current scale.
     * @param int    $targetScale  The target scale.
     *
     * @return string|null The unscaled value at the target scale, or null if rounding would be required.
     *
     * @pure
     */
    public static function try_scale_exactly(string $value, int $current_scale, int $target_scale): ?string
    {
        if ($value === '0' || $target_scale === $current_scale) {
            return $value;
        }
        if ($target_scale > $current_scale) {
            return $value . str_repeat('0', $target_scale - $current_scale);
        }
        $negative = $value[0] === '-';
        if ($negative) {
            $value = substr($value, 1);
        }
        $value = self::pad_unscaled_value($value, $current_scale);
        $discarded_digits = $current_scale - $target_scale;
        if (substr($value, -$discarded_digits) !== str_repeat('0', $discarded_digits)) {
            return null;
        }
        $value = substr($value, 0, -$discarded_digits);
        $value = ltrim($value, '0');
        if ($value === '') {
            return '0';
        }
        if ($negative) {
            return '-' . $value;
        }
        return $value;
    }
}