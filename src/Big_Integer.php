<?php

declare (strict_types=1);
namespace Brick\Math;

use function array_map;
use function assert;
use function bin2hex;
use Brick\Math\Exception\Division_By_Zero_Exception;
use Brick\Math\Exception\Integer_Overflow_Exception;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\Math_Exception;
use Brick\Math\Exception\Negative_Number_Exception;
use Brick\Math\Exception\No_Inverse_Exception;
use Brick\Math\Exception\Number_Format_Exception;
use Brick\Math\Exception\Random_Source_Exception;
use Brick\Math\Exception\Rounding_Necessary_Exception;
use Brick\Math\Internal\Calculator;
use Brick\Math\Internal\Calculator_Registry;
use Brick\Math\Internal\Safe;
use function chr;
use function count_chars;
use const FILTER_VALIDATE_INT;
use function filter_var;
use function hex2bin;
use function in_array;
use function intdiv;
use function is_string;
use LogicException;
use function ltrim;
use function ord;
use Override;
use function preg_match;
use function preg_quote;
use function random_bytes;
use function str_repeat;
use function strlen;
use function strtolower;
use function substr;
use Throwable;
/**
 * An arbitrarily large integer number.
 *
 * This class is immutable.
 */
final readonly class Big_Integer extends Big_Number
{
    /**
     * Protected constructor. Use a factory method to obtain an instance.
     *
     * @param string $value A string of digits, with optional leading minus sign.
     *
     * @pure
     */
    protected function __construct(private string $value)
    {
    }
    /**
     * Creates a number from a string in a given base.
     *
     * The string can optionally be prefixed with the `+` or `-` sign.
     *
     * Bases greater than 36 are not supported by this method, as there is no clear consensus on which of the lowercase
     * or uppercase characters should come first. Instead, this method accepts any base up to 36, and does not
     * differentiate lowercase and uppercase characters, which are considered equal.
     *
     * For bases greater than 36, and/or custom alphabets, use the fromArbitraryBase() method.
     *
     * @param non-empty-string $number The number to convert, in the given base.
     * @param int<2, 36>       $base   The base of the number, between 2 and 36.
     *
     * @throws NumberFormatException    If the number is empty, or contains invalid chars for the given base.
     * @throws InvalidArgumentException If the base is out of range.
     *
     * @pure
     */
    public static function from_base(string $number, int $base): Big_Integer
    {
        if ($base > 36) {
            // @phpstan-ignore smaller.alwaysFalse, greater.alwaysFalse, booleanOr.alwaysFalse
            throw InvalidArgumentException::base_out_of_range($base);
        }
        if ($number === '') {
            // @phpstan-ignore identical.alwaysFalse
            throw Number_Format_Exception::empty_number();
        }
        $original_number = $number;
        if ($number[0] === '-') {
            $sign = '-';
            $number = substr($number, 1);
        } elseif ($number[0] === '+') {
            $sign = '';
            $number = substr($number, 1);
        } else {
            $sign = '';
        }
        if ($number === '') {
            throw Number_Format_Exception::invalid_format($original_number);
        }
        $number = ltrim($number, '0');
        if ($number === '') {
            // The result will be the same in any base, avoid further calculation.
            return Big_Integer::zero();
        }
        if ($number === '1') {
            // The result will be the same in any base, avoid further calculation.
            return new Big_Integer($sign . '1');
        }
        $pattern = '/[^' . substr(Calculator::ALPHABET, 0, $base) . ']/';
        if (preg_match($pattern, strtolower($number), $matches) === 1) {
            throw Number_Format_Exception::char_not_valid_in_base($matches[0], $base);
        }
        if ($base === 10) {
            // The number is usable as is, avoid further calculation.
            return new Big_Integer($sign . $number);
        }
        $result = Calculator_Registry::get()->from_base($number, $base);
        return new Big_Integer($sign . $result);
    }
    /**
     * Parses a string containing an integer in an arbitrary base, using a custom alphabet.
     *
     * This method is byte-oriented: the alphabet is interpreted as a sequence of single-byte characters.
     * Multibyte UTF-8 characters are not supported.
     *
     * Because this method accepts any single-byte character, including dash, it does not handle negative numbers.
     *
     * @param non-empty-string $number   The number to parse.
     * @param non-empty-string $alphabet The alphabet, for example '01' for base 2, or '01234567' for base 8.
     *
     * @throws NumberFormatException    If the given number is empty or contains invalid chars for the given alphabet.
     * @throws InvalidArgumentException If the alphabet does not contain at least 2 chars, or contains duplicates.
     *
     * @pure
     */
    public static function from_arbitrary_base(string $number, string $alphabet): Big_Integer
    {
        $base = strlen($alphabet);
        if ($base < 2) {
            throw InvalidArgumentException::alphabet_too_short();
        }
        if (strlen(count_chars($alphabet, 3)) !== $base) {
            throw InvalidArgumentException::duplicate_chars_in_alphabet();
        }
        if ($number === '') {
            // @phpstan-ignore identical.alwaysFalse
            throw Number_Format_Exception::empty_number();
        }
        $pattern = '/[^' . preg_quote($alphabet, '/') . ']/';
        if (preg_match($pattern, $number, $matches) === 1) {
            throw Number_Format_Exception::char_not_in_alphabet($matches[0]);
        }
        $number = Calculator_Registry::get()->from_arbitrary_base($number, $alphabet, $base);
        return new Big_Integer($number);
    }
    /**
     * Translates a string of bytes containing the binary representation of a BigInteger into a BigInteger.
     *
     * The input string is assumed to be in big-endian byte-order: the most significant byte is in the zeroth element.
     *
     * If `$signed` is true, the input is assumed to be in two's-complement representation, and the leading bit is
     * interpreted as a sign bit. If `$signed` is false, the input is interpreted as an unsigned number, and the
     * resulting BigInteger will always be positive or zero.
     *
     * This method can be used to retrieve a number exported by `toBytes()`, as long as the `$signed` flags match.
     *
     * @param non-empty-string $value  The byte string.
     * @param bool             $signed Whether to interpret as a signed number in two's-complement representation with a leading
     *                                 sign bit.
     *
     * @throws NumberFormatException If the string is empty.
     *
     * @pure
     */
    public static function from_bytes(string $value, bool $signed = true): Big_Integer
    {
        if ($value === '') {
            // @phpstan-ignore identical.alwaysFalse
            throw Number_Format_Exception::empty_byte_string();
        }
        $twos_complement = false;
        if ($signed) {
            $x = ord($value[0]);
            if ($twos_complement = $x >= 0x80) {
                $value = ~$value;
            }
        }
        $number = self::from_base(bin2hex($value), 16);
        if ($twos_complement) {
            return $number->plus(1)->negated();
        }
        return $number;
    }
    /**
     * Generates a pseudo-random number in the range 0 to 2^bitCount - 1.
     *
     * Using the default random bytes generator, this method is suitable for cryptographic use.
     *
     * @param non-negative-int             $bitCount             The number of bits.
     * @param (callable(int): string)|null $randomBytesGenerator A function that accepts a number of bytes, and returns
     *                                                           a string of random bytes of the given length. Defaults
     *                                                           to the `random_bytes()` function.
     *
     * @throws InvalidArgumentException If $bitCount is negative.
     * @throws RandomSourceException    If random byte generation fails.
     */
    public static function random_bits(int $bit_count, ?callable $random_bytes_generator = null): Big_Integer
    {
        if ($bit_count < 0) {
            // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negative_bit_count();
        }
        if ($bit_count === 0) {
            return Big_Integer::zero();
        }
        /** @var int<1, max> $byteLength */
        $byte_length = intdiv($bit_count - 1, 8) + 1;
        $extra_bits = $byte_length * 8 - $bit_count;
        $bitmask = chr(0xff >> $extra_bits);
        $random_bytes = self::random_bytes($byte_length, $random_bytes_generator);
        $random_bytes[0] = $random_bytes[0] & $bitmask;
        return self::from_bytes($random_bytes, false);
    }
    /**
     * Generates a pseudo-random number between `$min` and `$max`, inclusive.
     *
     * Using the default random bytes generator, this method is suitable for cryptographic use.
     *
     * @param BigNumber|int|string         $min                  The lower bound. Must be convertible to a BigInteger.
     * @param BigNumber|int|string         $max                  The upper bound. Must be convertible to a BigInteger.
     * @param (callable(int): string)|null $randomBytesGenerator A function that accepts a number of bytes, and returns
     *                                                           a string of random bytes of the given length. Defaults
     *                                                           to the `random_bytes()` function.
     *
     * @throws MathException            If one of the parameters cannot be converted to a BigInteger.
     * @throws InvalidArgumentException If `$min` is greater than `$max`.
     * @throws RandomSourceException    If random byte generation fails.
     */
    public static function random_range(Big_Number|int|string $min, Big_Number|int|string $max, ?callable $random_bytes_generator = null): Big_Integer
    {
        $min = Big_Integer::of($min);
        $max = Big_Integer::of($max);
        if ($min->is_greater_than($max)) {
            throw InvalidArgumentException::min_greater_than_max();
        }
        if ($min->is_equal_to($max)) {
            return $min;
        }
        $diff = $max->minus($min);
        $bit_length = $diff->get_bit_length();
        // try until the number is in range (50% to 100% chance of success)
        do {
            $random_number = self::random_bits($bit_length, $random_bytes_generator);
        } while ($random_number->is_greater_than($diff));
        return $random_number->plus($min);
    }
    /**
     * Returns a BigInteger representing zero.
     *
     * @pure
     */
    public static function zero(): Big_Integer
    {
        /** @var BigInteger|null $zero */
        static $zero;
        if ($zero === null) {
            $zero = new Big_Integer('0');
        }
        return $zero;
    }
    /**
     * Returns a BigInteger representing one.
     *
     * @pure
     */
    public static function one(): Big_Integer
    {
        /** @var BigInteger|null $one */
        static $one;
        if ($one === null) {
            $one = new Big_Integer('1');
        }
        return $one;
    }
    /**
     * Returns a BigInteger representing ten.
     *
     * @pure
     */
    public static function ten(): Big_Integer
    {
        /** @var BigInteger|null $ten */
        static $ten;
        if ($ten === null) {
            $ten = new Big_Integer('10');
        }
        return $ten;
    }
    /**
     * Returns the greatest common divisor of the given numbers.
     *
     * The GCD is always positive, unless all numbers are zero, in which case it is zero.
     *
     * @param BigNumber|int|string $a    The first number. Must be convertible to a BigInteger.
     * @param BigNumber|int|string ...$n The additional numbers. Each number must be convertible to a BigInteger.
     *
     * @throws MathException If one of the parameters cannot be converted to a BigInteger.
     *
     * @pure
     */
    public static function gcd_all(Big_Number|int|string $a, Big_Number|int|string ...$n): Big_Integer
    {
        $result = Big_Integer::of($a)->abs();
        $n = array_map(Big_Integer::of(...), $n);
        // @phpstan-ignore possiblyImpure.functionCall
        foreach ($n as $next) {
            $result = $result->gcd($next);
            if ($result->is_equal_to(1)) {
                return $result;
            }
        }
        return $result;
    }
    /**
     * Returns the least common multiple of the given numbers.
     *
     * The LCM is always positive, unless one of the numbers is zero, in which case it is zero.
     *
     * @param BigNumber|int|string $a    The first number. Must be convertible to a BigInteger.
     * @param BigNumber|int|string ...$n The additional numbers. Each number must be convertible to a BigInteger.
     *
     * @throws MathException If one of the parameters cannot be converted to a BigInteger.
     *
     * @pure
     */
    public static function lcm_all(Big_Number|int|string $a, Big_Number|int|string ...$n): Big_Integer
    {
        $result = Big_Integer::of($a)->abs();
        $n = array_map(Big_Integer::of(...), $n);
        // @phpstan-ignore possiblyImpure.functionCall
        foreach ($n as $next) {
            $result = $result->lcm($next);
            if ($result->is_zero()) {
                return $result;
            }
        }
        return $result;
    }
    /**
     * Returns the sum of this number and the given one.
     *
     * @param BigNumber|int|string $that The number to add. Must be convertible to a BigInteger.
     *
     * @throws MathException If the number is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function plus(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        if ($that->is_zero()) {
            return $this;
        }
        if ($this->is_zero()) {
            return $that;
        }
        $value = Calculator_Registry::get()->add($this->value, $that->value);
        return new Big_Integer($value);
    }
    /**
     * Returns the difference of this number and the given one.
     *
     * @param BigNumber|int|string $that The number to subtract. Must be convertible to a BigInteger.
     *
     * @throws MathException If the number is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function minus(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        if ($that->is_zero()) {
            return $this;
        }
        if ($this->is_zero()) {
            return $that->negated();
        }
        $value = Calculator_Registry::get()->sub($this->value, $that->value);
        return new Big_Integer($value);
    }
    /**
     * Returns the product of this number and the given one.
     *
     * @param BigNumber|int|string $that The multiplier. Must be convertible to a BigInteger.
     *
     * @throws MathException If the multiplier is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function multiplied_by(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        if ($that->is_one()) {
            return $this;
        }
        if ($this->is_one()) {
            return $that;
        }
        $value = Calculator_Registry::get()->mul($this->value, $that->value);
        return new Big_Integer($value);
    }
    /**
     * Returns the result of the division of this number by the given one.
     *
     * @param BigNumber|int|string $that         The divisor. Must be convertible to a BigInteger.
     * @param RoundingMode         $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws MathException              If the divisor is not valid, or is not convertible to a BigInteger.
     * @throws DivisionByZeroException    If the divisor is zero.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used and the remainder is not zero.
     *
     * @pure
     */
    public function divided_by(Big_Number|int|string $that, Rounding_Mode $rounding_mode = Rounding_Mode::Unnecessary): Big_Integer
    {
        $that = Big_Integer::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        if ($that->is_one()) {
            return $this;
        }
        if ($that->is_minus_one()) {
            return $this->negated();
        }
        $result = Calculator_Registry::get()->div_round($this->value, $that->value, $rounding_mode);
        if ($result === null) {
            throw Rounding_Necessary_Exception::integer_division_not_exact();
        }
        return new Big_Integer($result);
    }
    /**
     * Returns this number exponentiated to the given value.
     *
     * @param non-negative-int $exponent
     *
     * @throws InvalidArgumentException If the exponent is negative.
     *
     * @pure
     */
    public function power(int $exponent): Big_Integer
    {
        if ($exponent === 0) {
            return Big_Integer::one();
        }
        if ($exponent === 1) {
            return $this;
        }
        if ($exponent < 0) {
            // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negative_exponent();
        }
        return new Big_Integer(Calculator_Registry::get()->pow($this->value, $exponent));
    }
    /**
     * Returns the quotient of the division of this number by the given one.
     *
     * Examples:
     *
     * - `7` quotient `3` returns `2`
     * - `7` quotient `-3` returns `-2`
     * - `-7` quotient `3` returns `-2`
     * - `-7` quotient `-3` returns `2`
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigInteger.
     *
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigInteger.
     * @throws DivisionByZeroException If the divisor is zero.
     *
     * @pure
     */
    public function quotient(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        if ($that->is_one()) {
            return $this;
        }
        if ($that->is_minus_one()) {
            return $this->negated();
        }
        $quotient = Calculator_Registry::get()->div_q($this->value, $that->value);
        return new Big_Integer($quotient);
    }
    /**
     * Returns the remainder of the division of this number by the given one.
     *
     * The remainder, when non-zero, has the same sign as the dividend.
     *
     * Examples:
     *
     * - `7` remainder `3` returns `1`
     * - `7` remainder `-3` returns `1`
     * - `-7` remainder `3` returns `-1`
     * - `-7` remainder `-3` returns `-1`
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigInteger.
     *
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigInteger.
     * @throws DivisionByZeroException If the divisor is zero.
     *
     * @pure
     */
    public function remainder(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        if ($that->is_one() || $that->is_minus_one()) {
            return Big_Integer::zero();
        }
        $remainder = Calculator_Registry::get()->div_r($this->value, $that->value);
        return new Big_Integer($remainder);
    }
    /**
     * Returns the quotient and remainder of the division of this number by the given one.
     *
     * Examples:
     *
     * - `7` quotientAndRemainder `3` returns [`2`, `1`]
     * - `7` quotientAndRemainder `-3` returns [`-2`, `1`]
     * - `-7` quotientAndRemainder `3` returns [`-2`, `-1`]
     * - `-7` quotientAndRemainder `-3` returns [`2`, `-1`]
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigInteger.
     *
     * @return array{BigInteger, BigInteger} An array containing the quotient and the remainder.
     *
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigInteger.
     * @throws DivisionByZeroException If the divisor is zero.
     *
     * @pure
     */
    public function quotient_and_remainder(Big_Number|int|string $that): array
    {
        $that = Big_Integer::of($that);
        if ($that->is_zero()) {
            throw Division_By_Zero_Exception::division_by_zero();
        }
        if ($that->is_one()) {
            return [$this, Big_Integer::zero()];
        }
        if ($that->is_minus_one()) {
            return [$this->negated(), Big_Integer::zero()];
        }
        [$quotient, $remainder] = Calculator_Registry::get()->div_qr($this->value, $that->value);
        return [new Big_Integer($quotient), new Big_Integer($remainder)];
    }
    /**
     * Returns this number modulo the given one.
     *
     * The result is always non-negative, and is the unique value `r` such that `0 <= r < m`
     * and `this - r` is a multiple of `m`.
     *
     * This is also known as Euclidean modulo. Unlike `remainder()`, which can return negative values
     * when the dividend is negative, `mod()` always returns a non-negative result.
     *
     * Examples:
     *
     * - `7` mod `3` returns `1`
     * - `-7` mod `3` returns `2`
     *
     * @param BigNumber|int|string $modulus The modulus. Must be convertible to a BigInteger.
     *
     * @throws MathException            If the modulus is not valid, or is not convertible to a BigInteger.
     * @throws InvalidArgumentException If the modulus is negative.
     * @throws DivisionByZeroException  If the modulus is zero.
     *
     * @pure
     */
    public function mod(Big_Number|int|string $modulus): Big_Integer
    {
        $modulus = Big_Integer::of($modulus);
        if ($modulus->is_zero()) {
            throw Division_By_Zero_Exception::zero_modulus();
        }
        if ($modulus->is_negative()) {
            throw InvalidArgumentException::negative_modulus();
        }
        $value = Calculator_Registry::get()->mod($this->value, $modulus->value);
        return new Big_Integer($value);
    }
    /**
     * Returns the modular multiplicative inverse of this BigInteger modulo $modulus.
     *
     * @param BigNumber|int|string $modulus The modulus. Must be convertible to a BigInteger.
     *
     * @throws MathException            If the modulus is not valid, or is not convertible to a BigInteger.
     * @throws InvalidArgumentException If the modulus is negative.
     * @throws DivisionByZeroException  If the modulus is zero.
     * @throws NoInverseException       If this BigInteger has no multiplicative inverse mod m (that is, this BigInteger
     *                                  is not relatively prime to m).
     *
     * @pure
     */
    public function mod_inverse(Big_Number|int|string $modulus): Big_Integer
    {
        $modulus = Big_Integer::of($modulus);
        if ($modulus->is_zero()) {
            throw Division_By_Zero_Exception::zero_modulus();
        }
        if ($modulus->is_negative()) {
            throw InvalidArgumentException::negative_modulus();
        }
        if ($modulus->is_one()) {
            return Big_Integer::zero();
        }
        $value = Calculator_Registry::get()->mod_inverse($this->value, $modulus->value);
        if ($value === null) {
            throw No_Inverse_Exception::no_modular_inverse();
        }
        return new Big_Integer($value);
    }
    /**
     * Returns this number raised into power with modulo.
     *
     * This operation requires a non-negative exponent and a strictly positive modulus.
     *
     * @param BigNumber|int|string $exponent The exponent. Must be convertible to a BigInteger.
     * @param BigNumber|int|string $modulus  The modulus. Must be convertible to a BigInteger.
     *
     * @throws MathException            If the exponent or modulus is not valid, or is not convertible to a BigInteger.
     * @throws InvalidArgumentException If the exponent or modulus is negative.
     * @throws DivisionByZeroException  If the modulus is zero.
     *
     * @pure
     */
    public function mod_pow(Big_Number|int|string $exponent, Big_Number|int|string $modulus): Big_Integer
    {
        $exponent = Big_Integer::of($exponent);
        $modulus = Big_Integer::of($modulus);
        if ($modulus->is_zero()) {
            throw Division_By_Zero_Exception::zero_modulus();
        }
        if ($modulus->is_negative()) {
            throw InvalidArgumentException::negative_modulus();
        }
        if ($exponent->is_negative()) {
            throw InvalidArgumentException::negative_exponent();
        }
        $result = Calculator_Registry::get()->mod_pow($this->value, $exponent->value, $modulus->value);
        return new Big_Integer($result);
    }
    /**
     * Returns the greatest common divisor of this number and the given one.
     *
     * The GCD is always positive, unless both operands are zero, in which case it is zero.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function gcd(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        if ($that->is_zero()) {
            return $this->abs();
        }
        if ($this->is_zero()) {
            return $that->abs();
        }
        $value = Calculator_Registry::get()->gcd($this->value, $that->value);
        return new Big_Integer($value);
    }
    /**
     * Returns the least common multiple of this number and the given one.
     *
     * The LCM is always positive, unless at least one operand is zero, in which case it is zero.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function lcm(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        if ($this->is_zero() || $that->is_zero()) {
            return Big_Integer::zero();
        }
        $value = Calculator_Registry::get()->lcm($this->value, $that->value);
        return new Big_Integer($value);
    }
    /**
     * Returns the integer square root of this number, rounded according to the given rounding mode.
     *
     * @param RoundingMode $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws NegativeNumberException    If this number is negative.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used, and the number is not a perfect square.
     *
     * @pure
     */
    public function sqrt(Rounding_Mode $rounding_mode = Rounding_Mode::Unnecessary): Big_Integer
    {
        if ($this->is_negative()) {
            throw Negative_Number_Exception::square_root_of_negative_number();
        }
        $calculator = Calculator_Registry::get();
        $sqrt = $calculator->sqrt($this->value);
        // For Down and Floor (equivalent for non-negative numbers), return floor sqrt
        if ($rounding_mode === Rounding_Mode::Down || $rounding_mode === Rounding_Mode::Floor) {
            return new Big_Integer($sqrt);
        }
        // Check if the sqrt is exact
        $s2 = $calculator->mul($sqrt, $sqrt);
        $remainder = $calculator->sub($this->value, $s2);
        if ($remainder === '0') {
            // sqrt is exact
            return new Big_Integer($sqrt);
        }
        // sqrt is not exact
        if ($rounding_mode === Rounding_Mode::Unnecessary) {
            throw Rounding_Necessary_Exception::integer_square_root_not_exact();
        }
        // For Up and Ceiling (equivalent for non-negative numbers), round up
        if ($rounding_mode === Rounding_Mode::Up || $rounding_mode === Rounding_Mode::Ceiling) {
            return new Big_Integer($calculator->add($sqrt, '1'));
        }
        // For Half* modes, compare our number to the midpoint of the interval [s², (s+1)²[.
        // The midpoint is s² + s + 0.5. Comparing n >= s² + s + 0.5 with remainder = n − s²
        // is equivalent to comparing 2*remainder >= 2*s + 1.
        $two_remainder = $calculator->mul($remainder, '2');
        $threshold = $calculator->add($calculator->mul($sqrt, '2'), '1');
        $cmp = $calculator->cmp($two_remainder, $threshold);
        // We're supposed to increment (round up) when:
        //   - HalfUp, HalfCeiling => $cmp >= 0
        //   - HalfDown, HalfFloor => $cmp > 0
        //   - HalfEven => $cmp > 0 || ($cmp === 0 && $sqrt % 2 === 1)
        // But 2*remainder is always even and 2*s + 1 is always odd, so $cmp is never zero.
        // Therefore, all Half* modes simplify to:
        if ($cmp > 0) {
            $sqrt = $calculator->add($sqrt, '1');
        }
        return new Big_Integer($sqrt);
    }
    #[Override]
    public function negated(): static
    {
        return new Big_Integer(Calculator_Registry::get()->neg($this->value));
    }
    /**
     * Returns the integer bitwise-and combined with another integer.
     *
     * This method returns a negative BigInteger if and only if both operands are negative.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function and(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        return new Big_Integer(Calculator_Registry::get()->and($this->value, $that->value));
    }
    /**
     * Returns the integer bitwise-or combined with another integer.
     *
     * This method returns a negative BigInteger if and only if either of the operands is negative.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function or(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        return new Big_Integer(Calculator_Registry::get()->or($this->value, $that->value));
    }
    /**
     * Returns the integer bitwise-xor combined with another integer.
     *
     * This method returns a negative BigInteger if and only if exactly one of the operands is negative.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function xor(Big_Number|int|string $that): Big_Integer
    {
        $that = Big_Integer::of($that);
        return new Big_Integer(Calculator_Registry::get()->xor($this->value, $that->value));
    }
    /**
     * Returns the bitwise-not of this BigInteger.
     *
     * @pure
     */
    public function not(): Big_Integer
    {
        return $this->negated()->minus(1);
    }
    /**
     * Returns the integer left shifted by a given number of bits.
     *
     * If $bits is negative, the integer is shifted right by the absolute value instead.
     *
     * @pure
     */
    public function shifted_left(int $bits): Big_Integer
    {
        if ($bits === 0) {
            return $this;
        }
        if ($bits < 0) {
            return $this->shifted_right(Safe::neg($bits));
        }
        return $this->multiplied_by(Big_Integer::of(2)->power($bits));
    }
    /**
     * Returns the integer right shifted by a given number of bits.
     *
     * If $bits is negative, the integer is shifted left by the absolute value instead.
     *
     * @pure
     */
    public function shifted_right(int $bits): Big_Integer
    {
        if ($bits === 0) {
            return $this;
        }
        if ($bits < 0) {
            return $this->shifted_left(Safe::neg($bits));
        }
        $operand = Big_Integer::of(2)->power($bits);
        if ($this->is_positive_or_zero()) {
            return $this->quotient($operand);
        }
        return $this->divided_by($operand, Rounding_Mode::Up);
    }
    /**
     * Returns the number of bits in the minimal two's-complement representation of this BigInteger, excluding a sign bit.
     *
     * For positive BigIntegers, this is equivalent to the number of bits in the ordinary binary representation.
     * Computes (ceil(log2(this < 0 ? -this : this+1))).
     *
     * @return non-negative-int
     *
     * @pure
     */
    public function get_bit_length(): int
    {
        if ($this->is_zero()) {
            return 0;
        }
        if ($this->is_negative()) {
            return $this->abs()->minus(1)->get_bit_length();
        }
        return strlen($this->to_base(2));
    }
    /**
     * Returns the index of the rightmost (lowest-order) one bit in this BigInteger.
     *
     * Returns null if this BigInteger is zero.
     *
     * @return non-negative-int|null
     *
     * @pure
     */
    public function get_lowest_set_bit(): ?int
    {
        $n = $this;
        $bit_length = $this->get_bit_length();
        for ($i = 0; $i <= $bit_length; $i++) {
            if ($n->is_odd()) {
                return $i;
            }
            $n = $n->shifted_right(1);
        }
        return null;
    }
    /**
     * Returns true if and only if the designated bit is set.
     *
     * Computes ((this & (1<<bitIndex)) != 0).
     *
     * @param non-negative-int $bitIndex The bit to test, 0-based.
     *
     * @throws InvalidArgumentException If the bit to test is negative.
     *
     * @pure
     */
    public function is_bit_set(int $bit_index): bool
    {
        if ($bit_index < 0) {
            // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negative_bit_index();
        }
        return $this->shifted_right($bit_index)->is_odd();
    }
    /**
     * Returns whether this number is even.
     *
     * @pure
     */
    public function is_even(): bool
    {
        return in_array($this->value[-1], ['0', '2', '4', '6', '8'], true);
    }
    /**
     * Returns whether this number is odd.
     *
     * @pure
     */
    public function is_odd(): bool
    {
        return in_array($this->value[-1], ['1', '3', '5', '7', '9'], true);
    }
    #[Override]
    public function compare_to(Big_Number|int|string $that): int
    {
        $that = Big_Number::of($that);
        if ($that instanceof Big_Integer) {
            return Calculator_Registry::get()->cmp($this->value, $that->value);
        }
        return -$that->compare_to($this);
    }
    #[Override]
    public function get_sign(): int
    {
        return $this->value === '0' ? 0 : ($this->value[0] === '-' ? -1 : 1);
    }
    #[Override]
    public function to_big_integer(): Big_Integer
    {
        return $this;
    }
    #[Override]
    public function to_big_decimal(): Big_Decimal
    {
        return self::new_big_decimal($this->value);
    }
    #[Override]
    public function to_big_rational(): Big_Rational
    {
        return self::new_big_rational($this, Big_Integer::one(), false, false);
    }
    #[Override]
    public function to_scale(int $scale, Rounding_Mode $rounding_mode = Rounding_Mode::Unnecessary): Big_Decimal
    {
        return $this->to_big_decimal()->to_scale($scale, $rounding_mode);
    }
    #[Override]
    public function to_int(): int
    {
        $int_value = filter_var($this->value, FILTER_VALIDATE_INT);
        if ($int_value === false) {
            throw Integer_Overflow_Exception::integer_out_of_range($this);
        }
        return $int_value;
    }
    #[Override]
    public function to_float(): float
    {
        return (float) $this->value;
    }
    /**
     * Returns a string representation of this number in the given base.
     *
     * The output will always be lowercase for bases greater than 10.
     *
     * @param int<2, 36> $base
     *
     * @throws InvalidArgumentException If the base is out of range.
     *
     * @pure
     */
    public function to_base(int $base): string
    {
        if ($base === 10) {
            return $this->value;
        }
        if ($base < 2 || $base > 36) {
            // @phpstan-ignore smaller.alwaysFalse, greater.alwaysFalse, booleanOr.alwaysFalse
            throw InvalidArgumentException::base_out_of_range($base);
        }
        return Calculator_Registry::get()->to_base($this->value, $base);
    }
    /**
     * Returns a string representation of this number in an arbitrary base with a custom alphabet.
     *
     * This method is byte-oriented: the alphabet is interpreted as a sequence of single-byte characters.
     * Multibyte UTF-8 characters are not supported.
     *
     * Because this method accepts any single-byte character, including dash, it does not handle negative numbers;
     * a NegativeNumberException will be thrown when attempting to call this method on a negative number.
     *
     * @param non-empty-string $alphabet The alphabet, for example '01' for base 2, or '01234567' for base 8.
     *
     * @throws InvalidArgumentException If the alphabet does not contain at least 2 chars, or contains duplicates.
     * @throws NegativeNumberException  If this number is negative.
     *
     * @pure
     */
    public function to_arbitrary_base(string $alphabet): string
    {
        $base = strlen($alphabet);
        if ($base < 2) {
            throw InvalidArgumentException::alphabet_too_short();
        }
        if (strlen(count_chars($alphabet, 3)) !== $base) {
            throw InvalidArgumentException::duplicate_chars_in_alphabet();
        }
        if ($this->is_negative()) {
            throw Negative_Number_Exception::to_arbitrary_base_of_negative_number();
        }
        return Calculator_Registry::get()->to_arbitrary_base($this->value, $alphabet, $base);
    }
    /**
     * Returns a string of bytes containing the binary representation of this BigInteger.
     *
     * The string is in big-endian byte-order: the most significant byte is in the zeroth element.
     *
     * If `$signed` is true, the output will be in two's-complement representation, and a sign bit will be prepended to
     * the output. If `$signed` is false, no sign bit will be prepended, and this method will throw an exception if the
     * number is negative.
     *
     * The string will contain the minimum number of bytes required to represent this BigInteger, including a sign bit
     * if `$signed` is true.
     *
     * This representation is compatible with the `fromBytes()` factory method, as long as the `$signed` flags match.
     *
     * @param bool $signed Whether to output a signed number in two's-complement representation with a leading sign bit.
     *
     * @throws NegativeNumberException If $signed is false, and the number is negative.
     *
     * @pure
     */
    public function to_bytes(bool $signed = true): string
    {
        if (!$signed && $this->is_negative()) {
            throw Negative_Number_Exception::unsigned_bytes_of_negative_number();
        }
        $hex = $this->abs()->to_base(16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $base_hex_length = strlen($hex);
        if ($signed) {
            if ($this->is_negative()) {
                $bin = hex2bin($hex);
                assert($bin !== false);
                /** @var non-empty-string $hex */
                $hex = bin2hex(~$bin);
                $hex = self::from_base($hex, 16)->plus(1)->to_base(16);
                $hex_length = strlen($hex);
                if ($hex_length < $base_hex_length) {
                    $hex = str_repeat('0', $base_hex_length - $hex_length) . $hex;
                }
                if ($hex[0] < '8') {
                    $hex = 'FF' . $hex;
                }
            } else if ($hex[0] >= '8') {
                $hex = '00' . $hex;
            }
        }
        $result = hex2bin($hex);
        assert($result !== false);
        return $result;
    }
    /**
     * @return numeric-string
     */
    #[Override]
    public function to_string(): string
    {
        return $this->value;
    }
    /**
     * This method is required for serializing the object and SHOULD NOT be accessed directly.
     *
     * @internal
     *
     * @return array{value: string}
     */
    public function __serialize(): array
    {
        return ['value' => $this->value];
    }
    /**
     * This method is only here to allow unserializing the object and cannot be accessed directly.
     *
     * @internal
     *
     * @param array{value: string} $data
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
    }
    #[Override]
    protected static function from(Big_Number $number): static
    {
        return $number->to_big_integer();
    }
    /**
     * Returns random bytes from the provided generator or from random_bytes().
     *
     * @param int                          $byteLength           The number of requested bytes.
     * @param (callable(int): string)|null $randomBytesGenerator The random bytes generator, or null to use random_bytes().
     *
     * @throws RandomSourceException If random byte generation fails.
     */
    private static function random_bytes(int $byte_length, ?callable $random_bytes_generator): string
    {
        if ($random_bytes_generator === null) {
            $random_bytes_generator = random_bytes(...);
        }
        try {
            $random_bytes = $random_bytes_generator($byte_length);
        } catch (Throwable $e) {
            throw Random_Source_Exception::random_source_failure($e);
        }
        /** @phpstan-ignore function.alreadyNarrowedType (Defensive runtime check for user-provided callbacks) */
        if (!is_string($random_bytes)) {
            throw Random_Source_Exception::invalid_random_bytes_type($random_bytes);
        }
        if (strlen($random_bytes) !== $byte_length) {
            throw Random_Source_Exception::invalid_random_bytes_length($byte_length, strlen($random_bytes));
        }
        return $random_bytes;
    }
    /**
     * @pure
     */
    private function is_one(): bool
    {
        return $this->value === '1';
    }
    /**
     * @pure
     */
    private function is_minus_one(): bool
    {
        return $this->value === '-1';
    }
}