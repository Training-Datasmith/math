<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use function dechex;
use function ord;
use RuntimeException;
use function sprintf;
use function strtoupper;
/**
 * Exception thrown when attempting to create a number from a string with an invalid format.
 */
final class Number_Format_Exception extends RuntimeException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public static function invalid_format(string $value): self
    {
        return new self(sprintf('Value "%s" does not represent a valid number.', $value));
    }
    /**
     * @internal
     *
     * @param string $char The failing character.
     *
     * @pure
     */
    public static function char_not_in_alphabet(string $char): self
    {
        return new self(sprintf('Character %s is not valid in the given alphabet.', self::char_to_string($char)));
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function char_not_valid_in_base(string $char, int $base): self
    {
        return new self(sprintf('Character %s is not valid in base %d.', self::char_to_string($char), $base));
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function empty_number(): self
    {
        return new self('The number must not be empty.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function empty_byte_string(): self
    {
        return new self('The byte string must not be empty.');
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function exponent_too_large(): self
    {
        return new self('The exponent is too large to be represented as an integer.');
    }
    /**
     * @pure
     */
    private static function char_to_string(string $char): string
    {
        $ord = ord($char);
        if ($ord < 32 || $ord > 126) {
            $char = strtoupper(dechex($ord));
            if ($ord < 16) {
                $char = '0' . $char;
            }
            return '0x' . $char;
        }
        return '"' . $char . '"';
    }
}