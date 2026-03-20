<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use RuntimeException;
/**
 * Exception thrown when a number cannot be represented at the requested scale without rounding.
 */
final class Rounding_Necessary_Exception extends RuntimeException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public static function decimal_scale_too_small(): self
    {
        return new self('This decimal number cannot be represented at the requested scale without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function rational_scale_too_small(): self
    {
        return new self('This rational number cannot be represented at the requested scale without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function integer_division_not_exact(): self
    {
        return new self('The division has a non-zero remainder and cannot be represented as an integer without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function decimal_division_not_exact(): self
    {
        return new self('The division yields a non-terminating decimal expansion and cannot be represented as a decimal without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function decimal_division_scale_too_small(): self
    {
        return new self('The division result is exact but cannot be represented at the requested scale without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function integer_square_root_not_exact(): self
    {
        return new self('The square root is not exact and cannot be represented as an integer without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function decimal_square_root_not_exact(): self
    {
        return new self('The square root is not exact and cannot be represented as a decimal without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function decimal_square_root_scale_too_small(): self
    {
        return new self('The square root is exact but cannot be represented at the requested scale without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function decimal_not_convertible_to_integer(): self
    {
        return new self('This decimal number cannot be represented as an integer without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function rational_not_convertible_to_integer(): self
    {
        return new self('This rational number cannot be represented as an integer without rounding.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function rational_not_convertible_to_decimal(): self
    {
        return new self('This rational number has a non-terminating decimal expansion and cannot be represented as a decimal without rounding.');
    }
}