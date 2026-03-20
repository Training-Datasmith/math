<?php

declare (strict_types=1);
namespace Brick\Math;

use function assert;
use Brick\Math\Exception\Division_By_Zero_Exception;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\Math_Exception;
use Brick\Math\Exception\Negative_Number_Exception;
use Brick\Math\Exception\Rounding_Necessary_Exception;
use Brick\Math\Exception\Unsupported_Platform_Exception;
use Brick\Math\Internal\Calculator_Registry;
use Brick\Math\Internal\Decimal_Helper;
use Brick\Math\Internal\Safe;
use function chr;
use function in_array;
use function ini_set;
use function intdiv;
use function is_infinite;
use function is_nan;
use function json_encode;
use LogicException;
use function max;
use Override;
use function pack;
use const PHP_INT_SIZE;
use function rtrim;
use function str_repeat;
use function strlen;
use function substr;
use function unpack;
/**
 * An arbitrarily large decimal number.
 *
 * This class is immutable.
 *
 * The scale of the number is the number of digits after the decimal point. It is always positive or zero.
 */
final readonly class Big_Decimal extends Big_Number
{
    /**
     * Protected constructor. Use a factory method to obtain an instance.
     *
     * @param string           $value The unscaled value, validated.
     * @param non-negative-int $scale The scale, validated.
     *
     * @pure
     */
    protected function __construct(
        private string $value,
        /**
         * The scale (number of digits after the decimal point) of this decimal number.
         *
         * This must be zero or more.
         */
        private int $scale = 0
    )
    {
    }
    /**
     * Creates a BigDecimal from an unscaled value and a scale.
     *
     * Example: `(12345, 3)` will result in the BigDecimal `12.345`.
     *
     * A negative scale is normalized to zero by appending zeros to the unscaled value.
     *
     * Example: `(12345, -3)` will result in the BigDecimal `12345000`.
     *
     * @param BigNumber|int|string $value The unscaled value. Must be convertible to a BigInteger.
     * @param int                  $scale The scale of the number. If negative, the scale will be set to zero
     *                                    and the unscaled value will be adjusted accordingly.
     *
     * @throws MathException If the value is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public static function of_unscaled_value(Big_Number|int|string $value, int $scale = 0): Big_Decimal
    {
        $value = Big_Integer::of($value)->to_string();
        if ($scale < 0) {
            if ($value !== '0') {
                $value .= str_repeat('0', Safe::neg($scale));
            }
            $scale = 0;
        }
        return new Big_Decimal($value, $scale);
    }
    /**
     * Returns a BigDecimal representing zero, with a scale of zero.
     *
     * @pure
     */
    public static function zero(): Big_Decimal
    {
        /** @var BigDecimal|null $zero */
        static $zero;
        if ($zero === null) {
            $zero = new Big_Decimal('0');
        }
        return $zero;
    }
    /**
     * Returns a BigDecimal representing one, with a scale of zero.
     *
     * @pure
     */
    public static function one(): Big_Decimal
    {
        /** @var BigDecimal|null $one */
        static $one;
        if ($one === null) {
            $one = new Big_Decimal('1');
        }
        return $one;
    }
    /**
     * Returns a BigDecimal representing ten, with a scale of zero.
     *
     * @pure
     */
    public static function ten(): Big_Decimal
    {
        /** @var BigDecimal|null $ten */
        static $ten;
        if ($ten === null) {
            $ten = new Big_Decimal('10');
        }
        return $ten;
    }
    /**
     * Creates a BigDecimal from the exact IEEE-754 value of a float.
     *
     * Examples:
     *   - `fromFloatExact(0.1)` returns a BigDecimal with value '0.1000000000000000055511151231257827021181583404541015625'
     *   - `fromFloatExact(0.3)` returns a BigDecimal with value '0.299999999999999988897769753748434595763683319091796875'
     *   - `fromFloatExact(0.5)` returns a BigDecimal with value '0.5'
     *   - `fromFloatExact(1.0)` returns a BigDecimal with value '1'
     *
     * Note that BigDecimal has no concept of negative zero, so `-0.0` and `0.0` both convert to zero.
     *
     * @throws InvalidArgumentException     If the value is NaN or infinite.
     * @throws UnsupportedPlatformException If the platform uses a non-IEEE-754 double format.
     *
     * @pure
     */
    public static function from_float_exact(float $value): Big_Decimal
    {
        if (is_nan($value)) {
            throw InvalidArgumentException::cannot_convert_float('NaN');
        }
        if (is_infinite($value)) {
            throw InvalidArgumentException::cannot_convert_float($value > 0 ? 'INF' : '-INF');
        }
        if (pack('E', 1.0) !== "?\xf0\x00\x00\x00\x00\x00\x00") {
            throw Unsupported_Platform_Exception::unsupported_float_format();
        }
        if (PHP_INT_SIZE >= 8) {
            // 64-bit: extract the IEEE-754 bit pattern as a 64-bit integer.
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('J', pack('E', $value));
            $bits = $unpacked[1];
            // Bits: [sign(1)|exp(11)|mantissa(52)]
            $sign_bit = $bits >> 63 & 1;
            $exp_bits = $bits >> 52 & 0x7ff;
            $mantissa = $bits & 0xfffffffffffff;
            // Zero (covers both 0.0 and -0.0).
            if ($exp_bits === 0 && $mantissa === 0) {
                return Big_Decimal::zero();
            }
            if ($exp_bits === 0) {
                $significand = Big_Integer::of($mantissa);
            } else {
                $significand = Big_Integer::of(0x10000000000000 | $mantissa);
            }
        } else {
            // 32-bit: extract the IEEE-754 bit pattern as 8 bytes.
            $packed = pack('E', $value);
            // Get the first 16 bits as an integer.
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('n', $packed);
            $high16 = $unpacked[1];
            // Bits: [sign(1)|exp(11)|mantissa(4)] in header (bytes 0-1) + 48 bits of mantissa in bytes 2-7
            $sign_bit = $high16 >> 15 & 1;
            $exp_bits = $high16 >> 4 & 0x7ff;
            $mantissa_bytes = chr($high16 & 0xf) . substr($packed, 2);
            // Zero (covers both 0.0 and -0.0).
            if ($exp_bits === 0 && $mantissa_bytes === "\x00\x00\x00\x00\x00\x00\x00") {
                return Big_Decimal::zero();
            }
            $mantissa = Big_Integer::from_bytes($mantissa_bytes, false);
            if ($exp_bits === 0) {
                $significand = $mantissa;
            } else {
                $significand = $mantissa->plus(Big_Integer::of(1)->shifted_left(52));
            }
        }
        if ($exp_bits === 0) {
            // Subnormal: no implicit leading 1-bit; effective exponent = -1074.
            $base_exp = -1074;
        } else {
            // Normal: biased exp - 1023 (bias) - 52 (mantissa shift)
            $base_exp = $exp_bits - 1075;
        }
        if ($base_exp >= 0) {
            // Result is an integer: significand × 2^baseExp.
            $unscaled = $significand->multiplied_by(Big_Integer::of(2)->power($base_exp));
            $scale = 0;
        } else {
            // Fraction: significand × 5^|baseExp| / 10^|baseExp|.
            // Multiplying by 5^n eliminates the 2-based denominator while keeping scale = n.
            $abs_exp = -$base_exp;
            $unscaled = $significand->multiplied_by(Big_Integer::of(5)->power($abs_exp));
            $scale = $abs_exp;
        }
        if ($sign_bit === 1) {
            $unscaled = $unscaled->negated();
        }
        return Big_Decimal::of_unscaled_value($unscaled, $scale)->stripped_of_trailing_zeros();
    }
    /**
     * Creates a BigDecimal from the shortest decimal representation of a float that round-trips back to the same value.
     *
     * The result is the shortest BigDecimal that passes `BigDecimal::fromFloatShortest($f)->toFloat() === $f`.
     *
     * Examples:
     *   - `fromFloatShortest(0.3)` returns a BigDecimal with value '0.3'
     *   - `fromFloatShortest(0.1 * 3.0)` returns a BigDecimal with value '0.30000000000000004' (`0.1 * 3.0 !== 0.3`)
     *   - `fromFloatShortest(1.0 / 3.0)` returns a BigDecimal with value '0.3333333333333333'
     *
     * Note that BigDecimal has no concept of negative zero, so `-0.0` and `0.0` both convert to zero.
     *
     * @throws InvalidArgumentException If the value is NaN or infinite.
     */
    public static function from_float_shortest(float $value): Big_Decimal
    {
        if (is_nan($value)) {
            throw InvalidArgumentException::cannot_convert_float('NaN');
        }
        if (is_infinite($value)) {
            throw InvalidArgumentException::cannot_convert_float($value > 0 ? 'INF' : '-INF');
        }
        // json_encode() uses serialize_precision; precision -1 uses the shortest round-trip algorithm
        $previous_precision = ini_set('serialize_precision', '-1');
        try {
            $str = json_encode($value);
        } finally {
            if ($previous_precision !== false) {
                ini_set('serialize_precision', $previous_precision);
            }
        }
        assert($str !== false);
        return Big_Decimal::of($str)->stripped_of_trailing_zeros();
    }
    /**
     * Returns the sum of this number and the given one.
     *
     * The result has a scale of `max($this->scale, $that->scale)`.
     *
     * @param BigNumber|int|string $that The number to add. Must be convertible to a BigDecimal.
     *
     * @throws MathException If the number is not valid, or is not convertible to a BigDecimal.
     *
     * @pure
     */
    public function plus(Big_Number|int|string $that): Big_Decimal
    {
        $that = Big_Decimal::of($that);
        if ($that->is_zero() && $that->scale <= $this->scale) {
            return $this;
        }
        if ($this->is_zero() && $this->scale <= $that->scale) {
            return $that;
        }
        [$a, $b] = $this->scale_values($this, $that);
        $value = Calculator_Registry::get()->add($a, $b);
        $scale = max($this->scale, $that->scale);
        return new Big_Decimal($value, $scale);
    }
    /**
     * Returns the difference of this number and the given one.
     *
     * The result has a scale of `max($this->scale, $that->scale)`.
     *
     * @param BigNumber|int|string $that The number to subtract. Must be convertible to a BigDecimal.
     *
     * @throws MathException If the number is not valid, or is not convertible to a BigDecimal.
     *
     * @pure
     */
    public function minus(Big_Number|int|string $that): Big_Decimal
    {
        $that = Big_Decimal::of($that);
        if ($that->is_zero() && $that->scale <= $this->scale) {
            return $this;
        }
        if ($this->is_zero() && $this->scale <= $that->scale) {
            return $that->negated();
        }
        [$a, $b] = $this->scale_values($this, $that);
        $value = Calculator_Registry::get()->sub($a, $b);
        $scale = max($this->scale, $that->scale);
        return new Big_Decimal($value, $scale);
    }
    /**
     * Returns the product of this number and the given one.
     *
     * The result has a scale of `$this->scale + $that->scale`.
     *
     * @param BigNumber|int|string $that The multiplier. Must be convertible to a BigDecimal.
     *
     * @throws MathException If the multiplier is not valid, or is not convertible to a BigDecimal.
     *
     * @pure
     */
    public function multiplied_by(Big_Number|int|string $that): Big_Decimal
    {
        $that = Big_Decimal::of($that);
        if ($that->is_one_scale_zero()) {
            return $this;
        }
        if ($this->is_one_scale_zero()) {
            return $that;
        }
        /** @var non-negative-int $scale */
        $scale = Safe::add($this->scale, $that->scale);
        if ($this->is_zero() || $that->is_zero()) {
            return new Big_Decimal('0', $scale);
        }
        $value = Calculator_Registry::get()->mul($this->value, $that->value);
        return new Big_Decimal($value, $scale);
    }
    /**
     * Returns the result of the division of this number by the given one, at the given scale.
     *
     * @param BigNumber|int|string $that         The divisor. Must be convertible to a BigDecimal.
     * @param non-negative-int     $scale        The desired scale. Must be non-negative.
     * @param RoundingMode         $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws MathException              If the divisor is not valid, or is not convertible to a BigDecimal.
     * @throws InvalidArgumentException   If the scale is negative.
     * @throws DivisionByZeroException    If the divisor is zero.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used and the result cannot be represented
     *                                    exactly at the given scale.
     *
     * @pure
     */
    public function divided_by(Big_Number|int|string $that, int $scale, Rounding_Mode $rounding_mode = Rounding_Mode::Unnecessary): Big_Decimal
    {
        if ($scale < 0) {
            // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negative_scale();
        }
        $that = Big_Decimal::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        if ($that->is_one_scale_zero() && $scale === $this->scale) {
            return $this;
        }
        $p = $this->value_with_min_scale(Safe::add($that->scale, $scale));
        $q = $that->value_with_min_scale(Safe::sub($this->scale, $scale));
        $calculator = Calculator_Registry::get();
        $result = $calculator->div_round($p, $q, $rounding_mode);
        if ($result === null) {
            [$a, $b] = $this->scale_values($this->abs(), $that->abs());
            $denominator = $calculator->div_q($b, $calculator->gcd($a, $b));
            $required_scale = Decimal_Helper::compute_scale_from_reduced_fraction_denominator($denominator);
            if ($required_scale === null) {
                throw Rounding_Necessary_Exception::decimal_division_not_exact();
            }
            throw Rounding_Necessary_Exception::decimal_division_scale_too_small();
        }
        return new Big_Decimal($result, $scale);
    }
    /**
     * Returns the exact result of the division of this number by the given one.
     *
     * The scale of the result is automatically calculated to fit all the fraction digits.
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigDecimal.
     *
     * @throws MathException              If the divisor is not valid, or is not convertible to a BigDecimal.
     * @throws DivisionByZeroException    If the divisor is zero.
     * @throws RoundingNecessaryException If the result yields an infinite number of digits.
     *
     * @pure
     */
    public function divided_by_exact(Big_Number|int|string $that): Big_Decimal
    {
        $that = Big_Decimal::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        [$a, $b] = $this->scale_values($this->abs(), $that->abs());
        $calculator = Calculator_Registry::get();
        $denominator = $calculator->div_q($b, $calculator->gcd($a, $b));
        $scale = Decimal_Helper::compute_scale_from_reduced_fraction_denominator($denominator);
        if ($scale === null) {
            throw Rounding_Necessary_Exception::decimal_division_not_exact();
        }
        return $this->divided_by($that, $scale)->stripped_of_trailing_zeros();
    }
    /**
     * Returns this number exponentiated to the given value.
     *
     * The result has a scale of `$this->scale * $exponent`.
     *
     * @param non-negative-int $exponent
     *
     * @throws InvalidArgumentException If the exponent is negative.
     *
     * @pure
     */
    public function power(int $exponent): Big_Decimal
    {
        if ($exponent === 0) {
            return Big_Decimal::one();
        }
        if ($exponent === 1) {
            return $this;
        }
        if ($exponent < 0) {
            // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negative_exponent();
        }
        /** @var non-negative-int $scale */
        $scale = Safe::mul($this->scale, $exponent);
        return new Big_Decimal(Calculator_Registry::get()->pow($this->value, $exponent), $scale);
    }
    /**
     * Returns the quotient of the division of this number by the given one.
     *
     * The quotient has a scale of `0`.
     *
     * Examples:
     *
     * - `7.5` quotient `3` returns `2`
     * - `7.5` quotient `-3` returns `-2`
     * - `-7.5` quotient `3` returns `-2`
     * - `-7.5` quotient `-3` returns `2`
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigDecimal.
     *
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigDecimal.
     * @throws DivisionByZeroException If the divisor is zero.
     *
     * @pure
     */
    public function quotient(Big_Number|int|string $that): Big_Decimal
    {
        $that = Big_Decimal::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        $p = $this->value_with_min_scale($that->scale);
        $q = $that->value_with_min_scale($this->scale);
        $quotient = Calculator_Registry::get()->div_q($p, $q);
        return new Big_Decimal($quotient, 0);
    }
    /**
     * Returns the remainder of the division of this number by the given one.
     *
     * The remainder has a scale of `max($this->scale, $that->scale)`.
     * The remainder, when non-zero, has the same sign as the dividend.
     *
     * Examples:
     *
     * - `7.5` remainder `3` returns `1.5`
     * - `7.5` remainder `-3` returns `1.5`
     * - `-7.5` remainder `3` returns `-1.5`
     * - `-7.5` remainder `-3` returns `-1.5`
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigDecimal.
     *
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigDecimal.
     * @throws DivisionByZeroException If the divisor is zero.
     *
     * @pure
     */
    public function remainder(Big_Number|int|string $that): Big_Decimal
    {
        $that = Big_Decimal::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        $p = $this->value_with_min_scale($that->scale);
        $q = $that->value_with_min_scale($this->scale);
        $remainder = Calculator_Registry::get()->div_r($p, $q);
        $scale = max($this->scale, $that->scale);
        return new Big_Decimal($remainder, $scale);
    }
    /**
     * Returns the quotient and remainder of the division of this number by the given one.
     *
     * The quotient has a scale of `0`, and the remainder has a scale of `max($this->scale, $that->scale)`.
     *
     * Examples:
     *
     * - `7.5` quotientAndRemainder `3` returns [`2`, `1.5`]
     * - `7.5` quotientAndRemainder `-3` returns [`-2`, `1.5`]
     * - `-7.5` quotientAndRemainder `3` returns [`-2`, `-1.5`]
     * - `-7.5` quotientAndRemainder `-3` returns [`2`, `-1.5`]
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigDecimal.
     *
     * @return array{BigDecimal, BigDecimal} An array containing the quotient and the remainder.
     *
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigDecimal.
     * @throws DivisionByZeroException If the divisor is zero.
     *
     * @pure
     */
    public function quotient_and_remainder(Big_Number|int|string $that): array
    {
        $that = Big_Decimal::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        $p = $this->value_with_min_scale($that->scale);
        $q = $that->value_with_min_scale($this->scale);
        [$quotient, $remainder] = Calculator_Registry::get()->div_qr($p, $q);
        $scale = max($this->scale, $that->scale);
        $quotient = new Big_Decimal($quotient, 0);
        $remainder = new Big_Decimal($remainder, $scale);
        return [$quotient, $remainder];
    }
    /**
     * Returns the square root of this number, rounded to the given scale according to the given rounding mode.
     *
     * @param non-negative-int $scale        The target scale. Must be non-negative.
     * @param RoundingMode     $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws InvalidArgumentException   If the scale is negative.
     * @throws NegativeNumberException    If this number is negative.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used and the result cannot be represented
     *                                    exactly at the given scale.
     *
     * @pure
     */
    public function sqrt(int $scale, Rounding_Mode $rounding_mode = Rounding_Mode::Unnecessary): Big_Decimal
    {
        if ($scale < 0) {
            // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negative_scale();
        }
        if ($this->is_zero()) {
            return new Big_Decimal('0', $scale);
        }
        if ($this->is_negative()) {
            throw Negative_Number_Exception::square_root_of_negative_number();
        }
        $value = $this->value;
        $input_scale = $this->scale;
        if ($input_scale % 2 !== 0) {
            $value .= '0';
            $input_scale = Safe::add($input_scale, 1);
        }
        $calculator = Calculator_Registry::get();
        // Keep one extra digit for rounding.
        $intermediate_scale = Safe::add(max($scale, intdiv($input_scale, 2)), 1);
        $value .= str_repeat('0', Safe::sub(Safe::mul(2, $intermediate_scale), $input_scale));
        $sqrt = $calculator->sqrt($value);
        $is_exact = $calculator->mul($sqrt, $sqrt) === $value;
        if (!$is_exact) {
            if ($rounding_mode === Rounding_Mode::Unnecessary) {
                throw Rounding_Necessary_Exception::decimal_square_root_not_exact();
            }
            // Non-perfect-square sqrt is irrational, so the true value is strictly above this sqrt floor.
            // Add one at the intermediate scale to guarantee Up/Ceiling round up at the target scale.
            if (in_array($rounding_mode, [Rounding_Mode::Up, Rounding_Mode::Ceiling], true)) {
                $sqrt = $calculator->add($sqrt, '1');
            } elseif (in_array($rounding_mode, [Rounding_Mode::HalfDown, Rounding_Mode::HalfEven, Rounding_Mode::HalfFloor], true)) {
                $rounding_mode = Rounding_Mode::HalfUp;
            }
        }
        $scaled = Decimal_Helper::scale($sqrt, $intermediate_scale, $scale, $rounding_mode);
        if ($scaled === null) {
            throw Rounding_Necessary_Exception::decimal_square_root_scale_too_small();
        }
        return new Big_Decimal($scaled, $scale);
    }
    /**
     * Returns a copy of this BigDecimal with the decimal point moved to the left by the given number of places.
     *
     * If $places is negative, the decimal point is moved to the right by the absolute value instead.
     *
     * @pure
     */
    public function with_point_moved_left(int $places): Big_Decimal
    {
        if ($places === 0) {
            return $this;
        }
        if ($places < 0) {
            return $this->with_point_moved_right(Safe::neg($places));
        }
        /** @var non-negative-int $scale */
        $scale = Safe::add($this->scale, $places);
        return new Big_Decimal($this->value, $scale);
    }
    /**
     * Returns a copy of this BigDecimal with the decimal point moved to the right by the given number of places.
     *
     * If $places is negative, the decimal point is moved to the left by the absolute value instead.
     *
     * @pure
     */
    public function with_point_moved_right(int $places): Big_Decimal
    {
        if ($places === 0) {
            return $this;
        }
        if ($places < 0) {
            return $this->with_point_moved_left(Safe::neg($places));
        }
        $value = $this->value;
        $scale = Safe::sub($this->scale, $places);
        if ($scale < 0) {
            if ($value !== '0') {
                $value .= str_repeat('0', Safe::neg($scale));
            }
            $scale = 0;
        }
        return new Big_Decimal($value, $scale);
    }
    /**
     * Returns a copy of this BigDecimal with any trailing zeros removed from the fractional part.
     *
     * Examples:
     *
     * - `1.200` returns `1.2`
     * - `1.000` returns `1`
     * - `100` returns `100`
     *
     * @pure
     */
    public function stripped_of_trailing_zeros(): Big_Decimal
    {
        if ($this->scale === 0) {
            return $this;
        }
        $trimmed_value = rtrim($this->value, '0');
        if ($trimmed_value === '') {
            return Big_Decimal::zero();
        }
        $trimmable_zeros = strlen($this->value) - strlen($trimmed_value);
        if ($trimmable_zeros === 0) {
            return $this;
        }
        if ($trimmable_zeros > $this->scale) {
            $trimmable_zeros = $this->scale;
        }
        $value = substr($this->value, 0, -$trimmable_zeros);
        /** @var non-negative-int $scale */
        $scale = $this->scale - $trimmable_zeros;
        return new Big_Decimal($value, $scale);
    }
    #[Override]
    public function negated(): static
    {
        return new Big_Decimal(Calculator_Registry::get()->neg($this->value), $this->scale);
    }
    #[Override]
    public function compare_to(Big_Number|int|string $that): int
    {
        $that = Big_Number::of($that);
        if ($that instanceof Big_Integer) {
            $that = $that->to_big_decimal();
        }
        if ($that instanceof Big_Decimal) {
            [$a, $b] = $this->scale_values($this, $that);
            return Calculator_Registry::get()->cmp($a, $b);
        }
        return -$that->compare_to($this);
    }
    #[Override]
    public function get_sign(): int
    {
        return $this->value === '0' ? 0 : ($this->value[0] === '-' ? -1 : 1);
    }
    /**
     * Returns the unscaled value of this decimal number.
     *
     * For example, the unscaled value of `123.456` is `123456`.
     *
     * @pure
     */
    public function get_unscaled_value(): Big_Integer
    {
        return self::new_big_integer($this->value);
    }
    /**
     * Returns the scale of this decimal number.
     *
     * The scale is the number of digits after the decimal point. For example, the scale of `123.456` is `3`.
     *
     * @return non-negative-int
     *
     * @pure
     */
    public function get_scale(): int
    {
        return $this->scale;
    }
    /**
     * Returns the number of significant digits in the number.
     *
     * This is the number of digits in the unscaled value of the number.
     * The sign has no impact on the result.
     *
     * Examples:
     *   0 => 1
     *   0.0 => 1
     *   123 => 3
     *   123.456 => 6
     *   0.00123 => 3
     *   0.0012300 => 5
     *
     * @return positive-int
     *
     * @pure
     */
    public function get_precision(): int
    {
        $length = strlen($this->value);
        return $this->value[0] === '-' ? $length - 1 : $length;
    }
    /**
     * Returns the integral part of this decimal number.
     *
     * Examples:
     *
     * - `123.456` returns `123`
     * - `-123.456` returns `-123`
     * - `0.123` returns `0`
     * - `-0.123` returns `0`
     *
     * The following identity holds: `$d->isEqualTo($d->getFractionalPart()->plus($d->getIntegralPart()))`. Note that in
     * this identity, the operand order is significant: the reversed form throws when the fractional part is non-zero.
     *
     * @pure
     */
    public function get_integral_part(): Big_Integer
    {
        if ($this->scale === 0) {
            return self::new_big_integer($this->value);
        }
        $value = Decimal_Helper::pad_unscaled_value($this->value, $this->scale);
        $integer_part = substr($value, 0, -$this->scale);
        if ($integer_part === '-0') {
            $integer_part = '0';
        }
        return self::new_big_integer($integer_part);
    }
    /**
     * Returns the fractional part of this decimal number.
     *
     * Examples:
     *
     * - `123.456` returns `0.456`
     * - `-123.456` returns `-0.456`
     * - `123` returns `0`
     * - `-123` returns `0`
     * - `123.000` returns `0.000`
     *
     * The result always has the same scale as `$this`.
     *
     * The following identity holds: `$d->isEqualTo($d->getFractionalPart()->plus($d->getIntegralPart()))`. Note that in
     * this identity, the operand order is significant: the reversed form throws when the fractional part is non-zero.
     *
     * @pure
     */
    public function get_fractional_part(): Big_Decimal
    {
        if ($this->scale === 0) {
            return Big_Decimal::zero();
        }
        return $this->minus($this->get_integral_part());
    }
    #[Override]
    public function to_big_integer(): Big_Integer
    {
        $value = Decimal_Helper::try_scale_exactly($this->value, $this->scale, 0);
        if ($value !== null) {
            return self::new_big_integer($value);
        }
        throw Rounding_Necessary_Exception::decimal_not_convertible_to_integer();
    }
    #[Override]
    public function to_big_decimal(): Big_Decimal
    {
        return $this;
    }
    #[Override]
    public function to_big_rational(): Big_Rational
    {
        $numerator = self::new_big_integer($this->value);
        $denominator = self::new_big_integer('1' . str_repeat('0', $this->scale));
        return self::new_big_rational($numerator, $denominator, false, true);
    }
    #[Override]
    public function to_scale(int $scale, Rounding_Mode $rounding_mode = Rounding_Mode::Unnecessary): Big_Decimal
    {
        if ($scale < 0) {
            // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negative_scale();
        }
        if ($scale === $this->scale) {
            return $this;
        }
        $value = Decimal_Helper::scale($this->value, $this->scale, $scale, $rounding_mode);
        if ($value === null) {
            throw Rounding_Necessary_Exception::decimal_scale_too_small();
        }
        return new Big_Decimal($value, $scale);
    }
    #[Override]
    public function to_int(): int
    {
        return $this->to_big_integer()->to_int();
    }
    #[Override]
    public function to_float(): float
    {
        return (float) $this->to_string();
    }
    /**
     * @return numeric-string
     */
    #[Override]
    public function to_string(): string
    {
        if ($this->scale === 0) {
            return $this->value;
        }
        $value = Decimal_Helper::pad_unscaled_value($this->value, $this->scale);
        /** @phpstan-ignore return.type */
        return substr($value, 0, -$this->scale) . '.' . substr($value, -$this->scale);
    }
    /**
     * This method is required for serializing the object and SHOULD NOT be accessed directly.
     *
     * @internal
     *
     * @return array{value: string, scale: non-negative-int}
     */
    public function __serialize(): array
    {
        return ['value' => $this->value, 'scale' => $this->scale];
    }
    /**
     * This method is only here to allow unserializing the object and cannot be accessed directly.
     *
     * @internal
     *
     * @param array{value: string, scale: non-negative-int} $data
     *
     * @throws LogicException
     */
    public function __unserialize(array $data): void
    {
        /** @phpstan-ignore isset.initializedProperty */
        if (isset($this->value)) {
            throw new LogicException('__unserialize() is an internal function, it must not be called directly.');
        }
        /** @phpstan-ignore deadCode.unreachable */
        $this->value = $data['value'];
        $this->scale = $data['scale'];
    }
    #[Override]
    protected static function from(Big_Number $number): static
    {
        return $number->to_big_decimal();
    }
    /**
     * Puts the internal values of the given decimal numbers on the same scale.
     *
     * @return array{string, string} The scaled integer values of $x and $y.
     *
     * @pure
     */
    private function scale_values(Big_Decimal $x, Big_Decimal $y): array
    {
        $a = $x->value;
        $b = $y->value;
        if ($b !== '0' && $x->scale > $y->scale) {
            $b .= str_repeat('0', $x->scale - $y->scale);
        } elseif ($a !== '0' && $x->scale < $y->scale) {
            $a .= str_repeat('0', $y->scale - $x->scale);
        }
        return [$a, $b];
    }
    /**
     * @pure
     */
    private function value_with_min_scale(int $scale): string
    {
        $value = $this->value;
        if ($this->value !== '0' && $scale > $this->scale) {
            $value .= str_repeat('0', $scale - $this->scale);
        }
        return $value;
    }
    /**
     * @pure
     */
    private function is_one_scale_zero(): bool
    {
        return $this->value === '1' && $this->scale === 0;
    }
}