<?php

declare (strict_types=1);
namespace Brick\Math;

use function assert;
use Brick\Math\Exception\Division_By_Zero_Exception;
use Brick\Math\Exception\Integer_Overflow_Exception;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\Math_Exception;
use Brick\Math\Exception\Number_Format_Exception;
use Brick\Math\Exception\Rounding_Necessary_Exception;
use Brick\Math\Internal\Safe;
use const FILTER_VALIDATE_INT;
use function filter_var;
use function is_int;
use function is_null;
use JsonSerializable;
use function ltrim;
use Override;
use function preg_match;
use const PREG_UNMATCHED_AS_NULL;
use function str_contains;
use function str_repeat;
use Stringable;
use function strlen;
use function substr;
/**
 * Base class for arbitrary-precision numbers.
 *
 * This class is sealed: it is part of the public API but should not be subclassed in userland.
 * Protected methods may change in any version.
 *
 * @phpstan-sealed BigInteger|BigDecimal|BigRational
 */
abstract readonly class Big_Number implements JsonSerializable, Stringable
{
    /**
     * The regular expression used to parse integer or decimal numbers.
     */
    private const PARSE_REGEXP_NUMERICAL = '/^' . '(?<sign>[\-\+])?' . '(?<integral>[0-9]+)?' . '(?<point>\.)?' . '(?<fractional>[0-9]+)?' . '(?:[eE](?<exponent>[\-\+]?[0-9]+))?' . '$/';
    /**
     * The regular expression used to parse rational numbers.
     */
    private const PARSE_REGEXP_RATIONAL = '/^' . '(?<sign>[\-\+])?' . '(?<numerator>[0-9]+)' . '\/' . '(?<denominator>[0-9]+)' . '$/';
    /**
     * Creates a BigNumber of the given value.
     *
     * When of() is called on BigNumber, the concrete return type is dependent on the given value, with the following
     * rules:
     *
     * - BigNumber instances are returned as is
     * - integer numbers are returned as BigInteger
     * - strings containing a `/` character are returned as BigRational
     * - strings containing a `.` character or using an exponential notation are returned as BigDecimal
     * - strings containing only digits with an optional leading `+` or `-` sign are returned as BigInteger
     *
     * When of() is called on BigInteger, BigDecimal, or BigRational, the resulting number is converted to an instance
     * of the subclass when possible; otherwise a RoundingNecessaryException exception is thrown.
     *
     * @throws NumberFormatException      If the format of the number is not valid.
     * @throws DivisionByZeroException    If the value represents a rational number with a denominator of zero.
     * @throws RoundingNecessaryException If the value cannot be converted to an instance of the subclass without rounding.
     *
     * @pure
     */
    final public static function of(Big_Number|int|string $value): static
    {
        $value = self::_of($value);
        if (static::class === Big_Number::class) {
            assert($value instanceof static);
            return $value;
        }
        return static::from($value);
    }
    /**
     * Creates a BigNumber of the given value, or returns null if the input is null.
     *
     * Behaves like of() for non-null values.
     *
     * @see BigNumber::of()
     *
     * @throws NumberFormatException      If the format of the number is not valid.
     * @throws DivisionByZeroException    If the value represents a rational number with a denominator of zero.
     * @throws RoundingNecessaryException If the value cannot be converted to an instance of the subclass without rounding.
     *
     * @pure
     */
    final public static function of_nullable(Big_Number|int|string|null $value): ?static
    {
        if (is_null($value)) {
            return null;
        }
        return static::of($value);
    }
    /**
     * Returns the minimum of the given values.
     *
     * If several values are equal and minimal, the first one is returned.
     * This can affect the concrete return type when calling this method on BigNumber.
     *
     * @param BigNumber|int|string $a    The first number. Must be convertible to an instance of the class this method
     *                                   is called on.
     * @param BigNumber|int|string ...$n The additional numbers. Each number must be convertible to an instance of the
     *                                   class this method is called on.
     *
     * @throws MathException If a number is not valid, or is not convertible to an instance of the class this method is
     *                       called on.
     *
     * @pure
     */
    final public static function min(Big_Number|int|string $a, Big_Number|int|string ...$n): static
    {
        $min = static::of($a);
        foreach ($n as $value) {
            $value = static::of($value);
            if ($value->is_less_than($min)) {
                $min = $value;
            }
        }
        return $min;
    }
    /**
     * Returns the maximum of the given values.
     *
     * If several values are equal and maximal, the first one is returned.
     * This can affect the concrete return type when calling this method on BigNumber.
     *
     * @param BigNumber|int|string $a    The first number. Must be convertible to an instance of the class this method
     *                                   is called on.
     * @param BigNumber|int|string ...$n The additional numbers. Each number must be convertible to an instance of the
     *                                   class this method is called on.
     *
     * @throws MathException If a number is not valid, or is not convertible to an instance of the class this method is
     *                       called on.
     *
     * @pure
     */
    final public static function max(Big_Number|int|string $a, Big_Number|int|string ...$n): static
    {
        $max = static::of($a);
        foreach ($n as $value) {
            $value = static::of($value);
            if ($value->is_greater_than($max)) {
                $max = $value;
            }
        }
        return $max;
    }
    /**
     * Returns the sum of the given values.
     *
     * When called on BigNumber, sum() accepts any supported type and returns a result whose type is the widest among
     * the given values (BigInteger < BigDecimal < BigRational).
     *
     * When called on BigInteger, BigDecimal, or BigRational, sum() requires that all values can be converted to that
     * specific subclass, and returns a result of the same type.
     *
     * @param BigNumber|int|string $a    The first number. Must be convertible to an instance of the class this method
     *                                   is called on.
     * @param BigNumber|int|string ...$n The additional numbers. Each number must be convertible to an instance of the
     *                                   class this method is called on.
     *
     * @throws MathException If a number is not valid, or is not convertible to an instance of the class this method is
     *                       called on.
     *
     * @pure
     */
    final public static function sum(Big_Number|int|string $a, Big_Number|int|string ...$n): static
    {
        $sum = static::of($a);
        foreach ($n as $value) {
            $sum = self::add($sum, static::of($value));
        }
        assert($sum instanceof static);
        return $sum;
    }
    /**
     * Checks if this number is equal to the given one.
     *
     * @throws MathException If the given number is not valid.
     *
     * @pure
     */
    final public function is_equal_to(Big_Number|int|string $that): bool
    {
        return $this->compare_to($that) === 0;
    }
    /**
     * Checks if this number is strictly less than the given one.
     *
     * @throws MathException If the given number is not valid.
     *
     * @pure
     */
    final public function is_less_than(Big_Number|int|string $that): bool
    {
        return $this->compare_to($that) < 0;
    }
    /**
     * Checks if this number is less than or equal to the given one.
     *
     * @throws MathException If the given number is not valid.
     *
     * @pure
     */
    final public function is_less_than_or_equal_to(Big_Number|int|string $that): bool
    {
        return $this->compare_to($that) <= 0;
    }
    /**
     * Checks if this number is strictly greater than the given one.
     *
     * @throws MathException If the given number is not valid.
     *
     * @pure
     */
    final public function is_greater_than(Big_Number|int|string $that): bool
    {
        return $this->compare_to($that) > 0;
    }
    /**
     * Checks if this number is greater than or equal to the given one.
     *
     * @throws MathException If the given number is not valid.
     *
     * @pure
     */
    final public function is_greater_than_or_equal_to(Big_Number|int|string $that): bool
    {
        return $this->compare_to($that) >= 0;
    }
    /**
     * Checks if this number equals zero.
     *
     * @pure
     */
    final public function is_zero(): bool
    {
        return $this->get_sign() === 0;
    }
    /**
     * Checks if this number is strictly negative.
     *
     * @pure
     */
    final public function is_negative(): bool
    {
        return $this->get_sign() < 0;
    }
    /**
     * Checks if this number is negative or zero.
     *
     * @pure
     */
    final public function is_negative_or_zero(): bool
    {
        return $this->get_sign() <= 0;
    }
    /**
     * Checks if this number is strictly positive.
     *
     * @pure
     */
    final public function is_positive(): bool
    {
        return $this->get_sign() > 0;
    }
    /**
     * Checks if this number is positive or zero.
     *
     * @pure
     */
    final public function is_positive_or_zero(): bool
    {
        return $this->get_sign() >= 0;
    }
    /**
     * Returns the absolute value of this number.
     *
     * @pure
     */
    final public function abs(): static
    {
        return $this->is_negative() ? $this->negated() : $this;
    }
    /**
     * Returns the negated value of this number.
     *
     * @pure
     */
    abstract public function negated(): static;
    /**
     * Returns the sign of this number.
     *
     * Returns -1 if the number is negative, 0 if zero, 1 if positive.
     *
     * @return -1|0|1
     *
     * @pure
     */
    abstract public function get_sign(): int;
    /**
     * Compares this number to the given one.
     *
     * Returns -1 if `$this` is lower than, 0 if equal to, 1 if greater than `$that`.
     *
     * @return -1|0|1
     *
     * @throws MathException If the number is not valid.
     *
     * @pure
     */
    abstract public function compare_to(Big_Number|int|string $that): int;
    /**
     * Limits (clamps) this number between the given minimum and maximum values.
     *
     * If the number is lower than $min, returns $min.
     * If the number is greater than $max, returns $max.
     * Otherwise, returns this number unchanged.
     *
     * @param BigNumber|int|string $min The minimum. Must be convertible to an instance of the class this method is called on.
     * @param BigNumber|int|string $max The maximum. Must be convertible to an instance of the class this method is called on.
     *
     * @throws MathException            If min/max are not convertible to an instance of the class this method is called on.
     * @throws InvalidArgumentException If min is greater than max.
     *
     * @pure
     */
    final public function clamp(Big_Number|int|string $min, Big_Number|int|string $max): static
    {
        $min = static::of($min);
        $max = static::of($max);
        if ($min->is_greater_than($max)) {
            throw InvalidArgumentException::min_greater_than_max();
        }
        if ($this->is_less_than($min)) {
            return $min;
        }
        if ($this->is_greater_than($max)) {
            return $max;
        }
        return $this;
    }
    /**
     * Converts this number to a BigInteger.
     *
     * @throws RoundingNecessaryException If this number cannot be converted to a BigInteger without rounding.
     *
     * @pure
     */
    abstract public function to_big_integer(): Big_Integer;
    /**
     * Converts this number to a BigDecimal.
     *
     * @throws RoundingNecessaryException If this number cannot be converted to a BigDecimal without rounding.
     *
     * @pure
     */
    abstract public function to_big_decimal(): Big_Decimal;
    /**
     * Converts this number to a BigRational.
     *
     * @pure
     */
    abstract public function to_big_rational(): Big_Rational;
    /**
     * Converts this number to a BigDecimal with the given scale, using rounding if necessary.
     *
     * @param non-negative-int $scale        The scale of the resulting `BigDecimal`. Must be non-negative.
     * @param RoundingMode     $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws InvalidArgumentException   If the scale is negative.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used, and this number cannot be converted to
     *                                    the given scale without rounding.
     *
     * @pure
     */
    abstract public function to_scale(int $scale, Rounding_Mode $rounding_mode = Rounding_Mode::Unnecessary): Big_Decimal;
    /**
     * Returns the exact value of this number as a native integer.
     *
     * If this number cannot be converted to a native integer without losing precision, an exception is thrown.
     * Note that the acceptable range for an integer depends on the platform and differs for 32-bit and 64-bit.
     *
     * @throws RoundingNecessaryException If this number cannot be converted to an integer without rounding.
     * @throws IntegerOverflowException   If this number is too large to fit in a native integer.
     *
     * @pure
     */
    abstract public function to_int(): int;
    /**
     * Returns an approximation of this number as a floating-point value.
     *
     * Note that this method can discard information as the precision of a floating-point value
     * is inherently limited.
     *
     * If the number is greater than the largest representable floating point number, positive infinity is returned.
     * If the number is less than the smallest representable floating point number, negative infinity is returned.
     * This method never returns NaN.
     *
     * @pure
     */
    abstract public function to_float(): float;
    /**
     * Returns a string representation of this number.
     *
     * The output of this method can be parsed by the `of()` factory method; this will yield an object equal to this
     * one, but possibly of a different type if instantiated through `BigNumber::of()`.
     *
     * @return non-empty-string
     *
     * @pure
     */
    abstract public function to_string(): string;
    /**
     * @return non-empty-string
     */
    #[Override]
    final public function jsonSerialize(): string
    {
        return $this->to_string();
    }
    /**
     * @return non-empty-string
     *
     * @pure
     */
    #[Override]
    final public function __toString(): string
    {
        return $this->to_string();
    }
    /**
     * Overridden by subclasses to convert a BigNumber to an instance of the subclass.
     *
     * @throws RoundingNecessaryException If the value cannot be converted.
     *
     * @pure
     */
    abstract protected static function from(Big_Number $number): static;
    /**
     * Proxy method to access BigInteger's protected constructor from sibling classes.
     *
     * @internal
     *
     * @pure
     */
    final protected function new_big_integer(string $value): Big_Integer
    {
        return new Big_Integer($value);
    }
    /**
     * Proxy method to access BigDecimal's protected constructor from sibling classes.
     *
     * @internal
     *
     * @param non-negative-int $scale
     *
     * @pure
     */
    final protected function new_big_decimal(string $value, int $scale = 0): Big_Decimal
    {
        return new Big_Decimal($value, $scale);
    }
    /**
     * Proxy method to access BigRational's protected constructor from sibling classes.
     *
     * @internal
     *
     * @pure
     */
    final protected function new_big_rational(Big_Integer $numerator, Big_Integer $denominator, bool $check_denominator, bool $simplify): Big_Rational
    {
        return new Big_Rational($numerator, $denominator, $check_denominator, $simplify);
    }
    /**
     * @throws NumberFormatException   If the format of the number is not valid.
     * @throws DivisionByZeroException If the value represents a rational number with a denominator of zero.
     *
     * @pure
     */
    private static function _of(Big_Number|int|string $value): Big_Number
    {
        if ($value instanceof Big_Number) {
            return $value;
        }
        if (is_int($value)) {
            return new Big_Integer((string) $value);
        }
        if ($value === '') {
            throw Number_Format_Exception::empty_number();
        }
        if (str_contains($value, '/')) {
            // Rational number
            if (preg_match(self::PARSE_REGEXP_RATIONAL, $value, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
                throw Number_Format_Exception::invalid_format($value);
            }
            $sign = $matches['sign'];
            $numerator = $matches['numerator'];
            $denominator = $matches['denominator'];
            $numerator = self::clean_up($sign, $numerator);
            $denominator = self::clean_up(null, $denominator);
            if ($denominator === '0') {
                throw Division_By_Zero_Exception::zero_denominator();
            }
            return new Big_Rational(new Big_Integer($numerator), new Big_Integer($denominator), false, true);
        }
        // Integer or decimal number
        if (preg_match(self::PARSE_REGEXP_NUMERICAL, $value, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            throw Number_Format_Exception::invalid_format($value);
        }
        $sign = $matches['sign'];
        $point = $matches['point'];
        $integral = $matches['integral'];
        $fractional = $matches['fractional'];
        $exponent = $matches['exponent'];
        if ($integral === null && $fractional === null) {
            throw Number_Format_Exception::invalid_format($value);
        }
        if ($integral === null) {
            $integral = '0';
        }
        if ($point !== null || $exponent !== null) {
            $fractional ??= '';
            if ($exponent !== null) {
                if ($exponent[0] === '-') {
                    $exponent = ltrim(substr($exponent, 1), '0') ?: '0';
                    $exponent = filter_var($exponent, FILTER_VALIDATE_INT);
                    if ($exponent !== false) {
                        $exponent = -$exponent;
                    }
                } else {
                    if ($exponent[0] === '+') {
                        $exponent = substr($exponent, 1);
                    }
                    $exponent = ltrim($exponent, '0') ?: '0';
                    $exponent = filter_var($exponent, FILTER_VALIDATE_INT);
                }
            } else {
                $exponent = 0;
            }
            if ($exponent === false) {
                throw Number_Format_Exception::exponent_too_large();
            }
            $unscaled_value = self::clean_up($sign, $integral . $fractional);
            $scale = Safe::sub(strlen($fractional), $exponent);
            if ($scale < 0) {
                if ($unscaled_value !== '0') {
                    $unscaled_value .= str_repeat('0', Safe::neg($scale));
                }
                $scale = 0;
            }
            return new Big_Decimal($unscaled_value, $scale);
        }
        $integral = self::clean_up($sign, $integral);
        return new Big_Integer($integral);
    }
    /**
     * Removes optional leading zeros and applies sign.
     *
     * @param '+'|'-'|null     $sign   The sign, optional. Null is allowed for convenience and treated as '+'.
     * @param non-empty-string $number The number, validated as a string of digits.
     *
     * @pure
     */
    private static function clean_up(string|null $sign, string $number): string
    {
        $number = ltrim($number, '0');
        if ($number === '') {
            return '0';
        }
        return $sign === '-' ? '-' . $number : $number;
    }
    /**
     * Adds two BigNumber instances in the correct order to avoid a RoundingNecessaryException.
     *
     * @pure
     */
    private static function add(Big_Number $a, Big_Number $b): Big_Number
    {
        if ($a instanceof Big_Rational) {
            return $a->plus($b);
        }
        if ($b instanceof Big_Rational) {
            return $b->plus($a);
        }
        if ($a instanceof Big_Decimal) {
            return $a->plus($b);
        }
        if ($b instanceof Big_Decimal) {
            return $b->plus($a);
        }
        return $a->plus($b);
    }
}