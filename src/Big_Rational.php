<?php

declare (strict_types=1);
namespace Brick\Math;

use Brick\Math\Exception\Division_By_Zero_Exception;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\Math_Exception;
use Brick\Math\Exception\Rounding_Necessary_Exception;
use Brick\Math\Internal\Decimal_Helper;
use Brick\Math\Internal\Safe;
use function is_finite;
use LogicException;
use function max;
use function min;
use Override;
use function strlen;
use function substr;
/**
 * An arbitrarily large rational number.
 *
 * This class is immutable.
 *
 * Fractions are automatically simplified to lowest terms. For example, `2/4` becomes `1/2`.
 * The denominator is always strictly positive; the sign is carried by the numerator.
 */
final readonly class Big_Rational extends Big_Number
{
    /**
     * The numerator.
     */
    private Big_Integer $numerator;
    /**
     * The denominator. Always strictly positive.
     */
    private Big_Integer $denominator;
    /**
     * Protected constructor. Use a factory method to obtain an instance.
     *
     * @param BigInteger $numerator        The numerator.
     * @param BigInteger $denominator      The denominator.
     * @param bool       $checkDenominator Whether to check the denominator for negative and zero.
     *
     * @throws DivisionByZeroException If the denominator is zero.
     *
     * @pure
     */
    protected function __construct(Big_Integer $numerator, Big_Integer $denominator, bool $check_denominator, bool $simplify)
    {
        if ($check_denominator) {
            if ($denominator->is_zero()) {
                throw Division_By_Zero_Exception::zero_denominator();
            }
            if ($denominator->is_negative()) {
                $numerator = $numerator->negated();
                $denominator = $denominator->negated();
            }
        }
        if ($simplify) {
            $gcd = $numerator->gcd($denominator);
            $numerator = $numerator->quotient($gcd);
            $denominator = $denominator->quotient($gcd);
        }
        $this->numerator = $numerator;
        $this->denominator = $denominator;
    }
    /**
     * Creates a BigRational out of a numerator and a denominator.
     *
     * If the denominator is negative, the signs of both the numerator and the denominator
     * will be inverted to ensure that the denominator is always positive.
     *
     * @param BigNumber|int|string $numerator   The numerator. Must be convertible to a BigInteger.
     * @param BigNumber|int|string $denominator The denominator. Must be convertible to a BigInteger.
     *
     * @throws MathException           If an argument is not valid, or is not convertible to a BigInteger.
     * @throws DivisionByZeroException If the denominator is zero.
     *
     * @pure
     */
    public static function of_fraction(Big_Number|int|string $numerator, Big_Number|int|string $denominator): Big_Rational
    {
        $numerator = Big_Integer::of($numerator);
        $denominator = Big_Integer::of($denominator);
        return new Big_Rational($numerator, $denominator, true, true);
    }
    /**
     * Returns a BigRational representing zero.
     *
     * @pure
     */
    public static function zero(): Big_Rational
    {
        /** @var BigRational|null $zero */
        static $zero;
        if ($zero === null) {
            $zero = new Big_Rational(Big_Integer::zero(), Big_Integer::one(), false, false);
        }
        return $zero;
    }
    /**
     * Returns a BigRational representing one.
     *
     * @pure
     */
    public static function one(): Big_Rational
    {
        /** @var BigRational|null $one */
        static $one;
        if ($one === null) {
            $one = new Big_Rational(Big_Integer::one(), Big_Integer::one(), false, false);
        }
        return $one;
    }
    /**
     * Returns a BigRational representing ten.
     *
     * @pure
     */
    public static function ten(): Big_Rational
    {
        /** @var BigRational|null $ten */
        static $ten;
        if ($ten === null) {
            $ten = new Big_Rational(Big_Integer::ten(), Big_Integer::one(), false, false);
        }
        return $ten;
    }
    /**
     * Returns the numerator of this rational number.
     *
     * @pure
     */
    public function get_numerator(): Big_Integer
    {
        return $this->numerator;
    }
    /**
     * Returns the denominator of this rational number.
     *
     * The denominator is always strictly positive.
     *
     * @pure
     */
    public function get_denominator(): Big_Integer
    {
        return $this->denominator;
    }
    /**
     * Returns the integral part of this rational number.
     *
     * Examples:
     *
     * - `7/3` returns `2` (since 7/3 = 2 + 1/3)
     * - `-7/3` returns `-2` (since -7/3 = -2 + (-1/3))
     *
     * The following identity holds: `$r->isEqualTo($r->getFractionalPart()->plus($r->getIntegralPart()))`. Note that in
     * this identity, the operand order is significant: the reversed form throws when the fractional part is non-zero.
     *
     * @pure
     */
    public function get_integral_part(): Big_Integer
    {
        return $this->numerator->quotient($this->denominator);
    }
    /**
     * Returns the fractional part of this rational number.
     *
     * Examples:
     *
     * - `7/3` returns `1/3` (since 7/3 = 2 + 1/3)
     * - `-7/3` returns `-1/3` (since -7/3 = -2 + (-1/3))
     *
     * The following identity holds: `$r->isEqualTo($r->getFractionalPart()->plus($r->getIntegralPart()))`. Note that in
     * this identity, the operand order is significant: the reversed form throws when the fractional part is non-zero.
     *
     * @pure
     */
    public function get_fractional_part(): Big_Rational
    {
        return new Big_Rational($this->numerator->remainder($this->denominator), $this->denominator, false, false);
    }
    /**
     * Returns the sum of this number and the given one.
     *
     * @param BigNumber|int|string $that The number to add.
     *
     * @throws MathException If the number is not valid.
     *
     * @pure
     */
    public function plus(Big_Number|int|string $that): Big_Rational
    {
        $that = Big_Rational::of($that);
        if ($that->is_zero()) {
            return $this;
        }
        if ($this->is_zero()) {
            return $that;
        }
        $numerator = $this->numerator->multiplied_by($that->denominator);
        $numerator = $numerator->plus($that->numerator->multiplied_by($this->denominator));
        $denominator = $this->denominator->multiplied_by($that->denominator);
        return new Big_Rational($numerator, $denominator, false, true);
    }
    /**
     * Returns the difference of this number and the given one.
     *
     * @param BigNumber|int|string $that The number to subtract.
     *
     * @throws MathException If the number is not valid.
     *
     * @pure
     */
    public function minus(Big_Number|int|string $that): Big_Rational
    {
        $that = Big_Rational::of($that);
        if ($that->is_zero()) {
            return $this;
        }
        if ($this->is_zero()) {
            return $that->negated();
        }
        $numerator = $this->numerator->multiplied_by($that->denominator);
        $numerator = $numerator->minus($that->numerator->multiplied_by($this->denominator));
        $denominator = $this->denominator->multiplied_by($that->denominator);
        return new Big_Rational($numerator, $denominator, false, true);
    }
    /**
     * Returns the product of this number and the given one.
     *
     * @param BigNumber|int|string $that The multiplier.
     *
     * @throws MathException If the multiplier is not valid.
     *
     * @pure
     */
    public function multiplied_by(Big_Number|int|string $that): Big_Rational
    {
        $that = Big_Rational::of($that);
        if ($that->is_zero() || $this->is_zero()) {
            return Big_Rational::zero();
        }
        $numerator = $this->numerator->multiplied_by($that->numerator);
        $denominator = $this->denominator->multiplied_by($that->denominator);
        return new Big_Rational($numerator, $denominator, false, true);
    }
    /**
     * Returns the result of the division of this number by the given one.
     *
     * @param BigNumber|int|string $that The divisor.
     *
     * @throws MathException           If the divisor is not valid.
     * @throws DivisionByZeroException If the divisor is zero.
     *
     * @pure
     */
    public function divided_by(Big_Number|int|string $that): Big_Rational
    {
        $that = Big_Rational::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        $numerator = $this->numerator->multiplied_by($that->denominator);
        $denominator = $this->denominator->multiplied_by($that->numerator);
        return new Big_Rational($numerator, $denominator, true, true);
    }
    /**
     * Returns this number exponentiated to the given value.
     *
     * Unlike BigInteger and BigDecimal, BigRational supports negative exponents:
     * the result is the reciprocal raised to the absolute value of the exponent.
     *
     * @throws DivisionByZeroException If the exponent is negative and this number is zero.
     *
     * @pure
     */
    public function power(int $exponent): Big_Rational
    {
        if ($exponent === 0) {
            return Big_Rational::one();
        }
        if ($exponent === 1) {
            return $this;
        }
        if ($exponent < 0) {
            if ($this->is_zero()) {
                throw Division_By_Zero_Exception::zero_to_negative_power();
            }
            return $this->reciprocal()->power(Safe::neg($exponent));
        }
        return new Big_Rational($this->numerator->power($exponent), $this->denominator->power($exponent), false, false);
    }
    /**
     * Returns the reciprocal of this BigRational.
     *
     * The reciprocal has the numerator and denominator swapped.
     *
     * @throws DivisionByZeroException If this number is zero.
     *
     * @pure
     */
    public function reciprocal(): Big_Rational
    {
        if ($this->is_zero()) {
            throw Division_By_Zero_Exception::reciprocal_of_zero();
        }
        return new Big_Rational($this->denominator, $this->numerator, true, false);
    }
    #[Override]
    public function negated(): static
    {
        return new Big_Rational($this->numerator->negated(), $this->denominator, false, false);
    }
    #[Override]
    public function compare_to(Big_Number|int|string $that): int
    {
        $that = Big_Rational::of($that);
        if ($this->denominator->is_equal_to($that->denominator)) {
            return $this->numerator->compare_to($that->numerator);
        }
        return $this->numerator->multiplied_by($that->denominator)->compare_to($that->numerator->multiplied_by($this->denominator));
    }
    #[Override]
    public function get_sign(): int
    {
        return $this->numerator->get_sign();
    }
    #[Override]
    public function to_big_integer(): Big_Integer
    {
        if ($this->denominator->is_equal_to(1)) {
            return $this->numerator;
        }
        throw Rounding_Necessary_Exception::rational_not_convertible_to_integer();
    }
    #[Override]
    public function to_big_decimal(): Big_Decimal
    {
        $scale = Decimal_Helper::compute_scale_from_reduced_fraction_denominator($this->denominator->to_string());
        if ($scale === null) {
            throw Rounding_Necessary_Exception::rational_not_convertible_to_decimal();
        }
        return $this->numerator->to_big_decimal()->divided_by($this->denominator, $scale)->stripped_of_trailing_zeros();
    }
    #[Override]
    public function to_big_rational(): Big_Rational
    {
        return $this;
    }
    #[Override]
    public function to_scale(int $scale, Rounding_Mode $rounding_mode = Rounding_Mode::Unnecessary): Big_Decimal
    {
        if ($scale < 0) {
            // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negative_scale();
        }
        if ($rounding_mode === Rounding_Mode::Unnecessary) {
            $required_scale = Decimal_Helper::compute_scale_from_reduced_fraction_denominator($this->denominator->to_string());
            if ($required_scale === null) {
                throw Rounding_Necessary_Exception::rational_not_convertible_to_decimal();
            }
            if ($required_scale > $scale) {
                throw Rounding_Necessary_Exception::rational_scale_too_small();
            }
        }
        return $this->numerator->to_big_decimal()->divided_by($this->denominator, $scale, $rounding_mode);
    }
    #[Override]
    public function to_int(): int
    {
        return $this->to_big_integer()->to_int();
    }
    #[Override]
    public function to_float(): float
    {
        $numerator_float = $this->numerator->to_float();
        $denominator_float = $this->denominator->to_float();
        if (is_finite($numerator_float) && is_finite($denominator_float)) {
            return $numerator_float / $denominator_float;
        }
        // At least one side overflows to INF; use a decimal approximation instead.
        // We need ~17 significant digits for double precision (we use 20 for some margin). Since $scale controls
        // decimal places (not significant digits), we subtract the estimated order of magnitude so that large results
        // use fewer decimal places and small results use more (to look past leading zeros). Clamped to [0, 350] as
        // doubles range from e-324 to e308 (350 ≈ 324 + 20 significant digits + margin).
        $magnitude = strlen($this->numerator->abs()->to_string()) - strlen($this->denominator->to_string());
        $scale = min(350, max(0, 20 - $magnitude));
        return $this->numerator->to_big_decimal()->divided_by($this->denominator, $scale, Rounding_Mode::HalfEven)->to_float();
    }
    #[Override]
    public function to_string(): string
    {
        $numerator = $this->numerator->to_string();
        $denominator = $this->denominator->to_string();
        if ($denominator === '1') {
            return $numerator;
        }
        return $numerator . '/' . $denominator;
    }
    /**
     * Returns the decimal representation of this rational number, with repeating decimals in parentheses.
     *
     * WARNING: This method is unbounded.
     *          The length of the repeating decimal period can be as large as `denominator - 1`.
     *          For fractions with large denominators, this method can use excessive memory and CPU time.
     *          For example, `1/100019` has a repeating period of 100,018 digits.
     *
     * Examples:
     *
     * - `10/3` returns `3.(3)`
     * - `171/70` returns `2.4(428571)`
     * - `1/2` returns `0.5`
     *
     * @pure
     */
    public function to_repeating_decimal_string(): string
    {
        if ($this->is_zero()) {
            return '0';
        }
        $sign = $this->numerator->is_negative() ? '-' : '';
        $numerator = $this->numerator->abs();
        $denominator = $this->denominator;
        $integral = $numerator->quotient($denominator);
        $remainder = $numerator->remainder($denominator);
        $integral_string = $integral->to_string();
        if ($remainder->is_zero()) {
            return $sign . $integral_string;
        }
        $digits = '';
        $remainder_positions = [];
        $index = 0;
        while (!$remainder->is_zero()) {
            $remainder_string = $remainder->to_string();
            if (isset($remainder_positions[$remainder_string])) {
                $repeat_index = $remainder_positions[$remainder_string];
                $non_repeating = substr($digits, 0, $repeat_index);
                $repeating = substr($digits, $repeat_index);
                return $sign . $integral_string . '.' . $non_repeating . '(' . $repeating . ')';
            }
            $remainder_positions[$remainder_string] = $index;
            $remainder = $remainder->multiplied_by(10);
            $digits .= $remainder->quotient($denominator)->to_string();
            $remainder = $remainder->remainder($denominator);
            $index++;
        }
        return $sign . $integral_string . '.' . $digits;
    }
    /**
     * This method is required for serializing the object and SHOULD NOT be accessed directly.
     *
     * @internal
     *
     * @return array{numerator: BigInteger, denominator: BigInteger}
     */
    public function __serialize(): array
    {
        return ['numerator' => $this->numerator, 'denominator' => $this->denominator];
    }
    /**
     * This method is only here to allow unserializing the object and cannot be accessed directly.
     *
     * @internal
     *
     * @param array{numerator: BigInteger, denominator: BigInteger} $data
     *
     * @throws LogicException
     */
    public function __unserialize(array $data): void
    {
        /** @phpstan-ignore isset.initializedProperty */
        if (isset($this->numerator)) {
            throw new LogicException('__unserialize() is an internal function, it must not be called directly.');
        }
        /** @phpstan-ignore deadCode.unreachable */
        $this->numerator = $data['numerator'];
        $this->denominator = $data['denominator'];
    }
    #[Override]
    protected static function from(Big_Number $number): static
    {
        return $number->to_big_rational();
    }
}