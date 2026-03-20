<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use RuntimeException;
/**
 * Exception thrown when attempting to perform an unsupported operation, such as a square root, on a negative number.
 */
final class Negative_Number_Exception extends RuntimeException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public static function square_root_of_negative_number(): self
    {
        return new self('Cannot calculate the square root of a negative number.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function to_arbitrary_base_of_negative_number(): self
    {
        return new self('Cannot convert a negative number to an arbitrary base.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function unsigned_bytes_of_negative_number(): self
    {
        return new self('Cannot convert a negative number to a byte string in unsigned mode.');
    }
}