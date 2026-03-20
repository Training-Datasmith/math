<?php

declare (strict_types=1);
namespace Brick\Math\Internal;

use Brick\Math\Exception\Integer_Overflow_Exception;
use function is_int;
use const PHP_INT_MIN;
use function sprintf;
/**
 * Helpers for arithmetic operations that throw on native integer overflow.
 *
 * @internal
 */
final class Safe
{
    private function __construct()
    {
    }
    /**
     * @pure
     */
    public static function add(int $a, int $b): int
    {
        $result = $a + $b;
        if (is_int($result)) {
            return $result;
        }
        // @phpstan-ignore deadCode.unreachable
        throw Integer_Overflow_Exception::native_integer_overflow(sprintf('%d + %d', $a, $b));
    }
    /**
     * @pure
     */
    public static function sub(int $a, int $b): int
    {
        $result = $a - $b;
        if (is_int($result)) {
            return $result;
        }
        // @phpstan-ignore deadCode.unreachable
        throw Integer_Overflow_Exception::native_integer_overflow(sprintf('%d - %d', $a, $b));
    }
    /**
     * @pure
     */
    public static function mul(int $a, int $b): int
    {
        $result = $a * $b;
        if (is_int($result)) {
            return $result;
        }
        // @phpstan-ignore deadCode.unreachable
        throw Integer_Overflow_Exception::native_integer_overflow(sprintf('%d * %d', $a, $b));
    }
    /**
     * @pure
     */
    public static function neg(int $value): int
    {
        if ($value === PHP_INT_MIN) {
            throw Integer_Overflow_Exception::native_integer_overflow(sprintf('-(%d)', $value));
        }
        return -$value;
    }
}