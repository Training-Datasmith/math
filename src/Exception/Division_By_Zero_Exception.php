<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use RuntimeException;
/**
 * Exception thrown when a division by zero occurs.
 */
final class Division_By_Zero_Exception extends RuntimeException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public static function division_by_zero(): self
    {
        return new self('Division by zero.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function zero_modulus(): self
    {
        return new self('The modulus must not be zero.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function zero_denominator(): self
    {
        return new self('The denominator of a rational number must not be zero.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function reciprocal_of_zero(): self
    {
        return new self('The reciprocal of zero is undefined.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function zero_to_negative_power(): self
    {
        return new self('Cannot raise zero to a negative power.');
    }
}