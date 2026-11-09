<?php

declare(strict_types=1);

namespace Brick\Math\Tests;

use Brick\Math\Big_Decimal;
use Brick\Math\Big_Integer;
use Brick\Math\Big_Rational;

use function count;

use function ctype_digit;
use function explode;
use function ltrim;

use PHPUnit\Framework\TestCase;

use function preg_quote;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * Base class for math tests.
 */
abstract class Abstract_Test_Case extends TestCase
{
    final protected function expectExceptionMessageExact(string $message): void
    {
        $this->expectExceptionMessageMatches('/^' . preg_quote($message, '/') . '$/');
    }

    /**
     * @param string     $expected The expected value, as a string.
     * @param Big_Integer $actual   The Big_Integer instance to test.
     */
    final protected static function assertBigIntegerEquals(string $expected, Big_Integer $actual): void
    {
        self::assertSame($expected, $actual->to_string());
    }

    /**
     * @param string     $expected The expected string representation.
     * @param Big_Decimal $actual   The Big_Decimal instance to test.
     */
    final protected static function assertBigDecimalEquals(string $expected, Big_Decimal $actual): void
    {
        self::assertSame($expected, $actual->to_string());

        [$unscaledValue, $scale] = self::getUnscaledValueAndScale($expected);

        self::assertSame($unscaledValue, $actual->get_unscaled_value()->to_string());
        self::assertSame($scale, $actual->get_scale());
    }

    /**
     * @param string      $expected The expected string representation.
     * @param Big_Rational $actual   The Big_Rational instance to test.
     */
    final protected static function assertBigRationalEquals(string $expected, Big_Rational $actual): void
    {
        self::assertSame($expected, $actual->to_string());

        [$numerator, $denominator] = self::getNumeratorAndDenominator($expected);

        self::assertSame($numerator, $actual->get_numerator()->to_string());
        self::assertSame($denominator, $actual->get_denominator()->to_string());
    }

    final protected static function isException(string $name): bool
    {
        return str_ends_with($name, 'Exception');
    }

    /**
     * Computes the unscaled value and scale from a properly formatted decimal string.
     *
     * This intentionally uses a different algorithm than `Big_Decimal::of()`, to increase the chances of catching bugs.
     *
     * @return array{string, int}
     */
    private static function getUnscaledValueAndScale(string $decimal): array
    {
        $message = "Invalid decimal number used in assertion: $decimal";

        $parts = explode('.', $decimal);
        $left = $parts[0];

        if ($left[0] === '-') {
            $left = substr($left, 1);
            $sign = '-';
        } else {
            $sign = '';
        }

        self::assertDigitStringWithNoLeadingZeros($left, $message);

        if (count($parts) === 1) {
            if ($sign === '-') {
                self::assertNotSame('0', $left, $message);
            }

            return [$sign . $left, 0];
        }

        self::assertCount(2, $parts, $message);
        $right = $parts[1];

        self::assertTrue(ctype_digit($right), $message);

        $scale = strlen($right);

        $unscaledValue = ltrim($left . $right, '0');

        if ($unscaledValue === '') {
            $unscaledValue = '0';
        }

        return [$sign . $unscaledValue, $scale];
    }

    /**
     * Extracts the numerator and denominator from a properly formatted rational string.
     *
     * This intentionally uses a different algorithm than `Big_Rational::of()`, to increase the chances of catching bugs.
     *
     * @return array{string, string}
     */
    private static function getNumeratorAndDenominator(string $rational): array
    {
        $message = "Invalid rational number used in assertion: $rational";

        $parts = explode('/', $rational);
        $numerator = $parts[0];

        if ($numerator[0] === '-') {
            $numerator = substr($numerator, 1);
            $sign = '-';
        } else {
            $sign = '';
        }

        self::assertDigitStringWithNoLeadingZeros($numerator, $message);

        if (count($parts) === 1) {
            if ($sign === '-') {
                self::assertNotSame('0', $numerator, $message);
            }

            return [$sign . $numerator, '1'];
        }

        self::assertCount(2, $parts, $message);
        $denominator = $parts[1];

        self::assertDigitStringWithNoLeadingZeros($denominator, $message);

        return [$sign . $numerator, $denominator];
    }

    private static function assertDigitStringWithNoLeadingZeros(string $digits, string $message): void
    {
        self::assertTrue(ctype_digit($digits), $message);
        self::assertTrue($digits === '0' || ltrim($digits, '0') === $digits, $message);
    }
}
