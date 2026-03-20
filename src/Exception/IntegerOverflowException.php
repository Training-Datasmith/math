<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use Brick\Math\Big_Integer;
use const PHP_INT_MAX;
use const PHP_INT_MIN;
use RuntimeException;
use function sprintf;
/**
 * Exception thrown when a native integer overflow occurs.
 */
final class Integer_Overflow_Exception extends RuntimeException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public static function integer_out_of_range(Big_Integer $value): self
    {
        $message = '%s is out of range [%d, %d] and cannot be represented as an integer.';
        return new self(sprintf($message, $value->to_string(), PHP_INT_MIN, PHP_INT_MAX));
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function native_integer_overflow(string $expression): self
    {
        return new self(sprintf('Cannot compute %s because the result is outside the native integer range [%d, %d].', $expression, PHP_INT_MIN, PHP_INT_MAX));
    }
}