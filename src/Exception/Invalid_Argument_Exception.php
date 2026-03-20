<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use function sprintf;
/**
 * Exception thrown when an invalid argument is provided.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public static function base_out_of_range(int $base): self
    {
        return new self(sprintf('Base %d is out of range [2, 36].', $base));
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function negative_scale(): self
    {
        return new self('The scale must not be negative.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function negative_bit_index(): self
    {
        return new self('The bit index must not be negative.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function negative_bit_count(): self
    {
        return new self('The bit count must not be negative.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function alphabet_too_short(): self
    {
        return new self('The alphabet must contain at least 2 characters.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function duplicate_chars_in_alphabet(): self
    {
        return new self('The alphabet must not contain duplicate characters.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function min_greater_than_max(): self
    {
        return new self('The minimum value must be less than or equal to the maximum value.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function cannot_convert_float(string $type): self
    {
        return new self(sprintf('Cannot convert %s to a BigDecimal.', $type));
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function negative_exponent(): self
    {
        return new self('The exponent must not be negative.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function negative_modulus(): self
    {
        return new self('The modulus must not be negative.');
    }
}