<?php

declare (strict_types=1);
namespace Brick\Math\Exception;

use function get_debug_type;
use RuntimeException;
use function sprintf;
use Throwable;
/**
 * Exception thrown when random byte generation fails.
 */
final class Random_Source_Exception extends RuntimeException implements Math_Exception
{
    /**
     * @internal
     *
     * @pure
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function random_source_failure(Throwable $previous): self
    {
        return new self('Random byte generation failed.', $previous);
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function invalid_random_bytes_type(mixed $value): self
    {
        return new self(sprintf('The random bytes generator must return a string, got %s.', get_debug_type($value)));
    }
    /**
     * @internal
     *
     * @pure
     */
    public static function invalid_random_bytes_length(int $expected_length, int $actual_length): self
    {
        return new self(sprintf('The random bytes generator returned %d byte(s), expected %d.', $actual_length, $expected_length));
    }
}