<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use RuntimeException;
/**
 * Exception thrown when attempting to compute a modular inverse that does not exist.
 */
final class No_Inverse_Exception extends RuntimeException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public static function no_modular_inverse(): self
    {
        return new self('This number has no multiplicative inverse modulo the given modulus (they are not coprime).');
    }
}