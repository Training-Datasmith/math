<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use RuntimeException;
/**
 * Exception thrown when the current PHP platform does not support a required feature.
 */
final class Unsupported_Platform_Exception extends RuntimeException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public static function unsupported_float_format(): self
    {
        return new self('Unsupported float format: expected IEEE-754 double.');
    }
}