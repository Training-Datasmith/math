<?php

declare (strict_types=1);
namespace Brick\Math\Internal;

use Brick\Math\Rounding_Mode;
use function chr;
use function ltrim;
use function ord;
use function str_repeat;
use function strlen;
use function strpos;
use function strrev;
use function strtolower;
use function substr;
/**
 * Performs basic operations on arbitrary size integers.
 *
 * Unless otherwise specified, all parameters must be validated as non-empty strings of digits,
 * without leading zero, and with an optional leading minus sign if the number is not zero.
 *
 * Any other parameter format will lead to undefined behaviour.
 * All methods must return strings respecting this format, unless specified otherwise.
 *
 * @internal
 */
abstract readonly class Calculator
{
    /**
     * The alphabet for converting from and to base 2 to 36, lowercase.
     */
    public const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';
    /**
     * Returns the absolute value of a number.
     *
     * @pure
     */
    final public function abs(string $n): string
    {
        return $n[0] === '-' ? substr($n, 1) : $n;
    }
    /**
     * Negates a number.
     *
     * @pure
     */
    final public function neg(string $n): string
    {
        if ($n === '0') {
            return '0';
        }
        if ($n[0] === '-') {
            return substr($n, 1);
        }
        return '-' . $n;
    }
    /**
     * Compares two numbers.
     *
     * Returns -1 if the first number is less than, 0 if equal to, 1 if greater than the second number.
     *
     * @return -1|0|1
     *
     * @pure
     */
    final public function cmp(string $a, string $b): int
    {
        [$a_neg, $b_neg, $a_dig, $b_dig] = $this->init($a, $b);
        if ($a_neg && !$b_neg) {
            return -1;
        }
        if ($b_neg && !$a_neg) {
            return 1;
        }
        $a_len = strlen($a_dig);
        $b_len = strlen($b_dig);
        if ($a_len < $b_len) {
            $result = -1;
        } elseif ($a_len > $b_len) {
            $result = 1;
        } else {
            $result = $a_dig <=> $b_dig;
        }
        return $a_neg ? -$result : $result;
    }
    /**
     * Adds two numbers.
     *
     * @pure
     */
    abstract public function add(string $a, string $b): string;
    /**
     * Subtracts two numbers.
     *
     * @pure
     */
    abstract public function sub(string $a, string $b): string;
    /**
     * Multiplies two numbers.
     *
     * @pure
     */
    abstract public function mul(string $a, string $b): string;
    /**
     * Returns the quotient of the division of two numbers.
     *
     * @param string $a The dividend.
     * @param string $b The divisor, must not be zero.
     *
     * @return string The quotient.
     *
     * @pure
     */
    abstract public function div_q(string $a, string $b): string;
    /**
     * Returns the remainder of the division of two numbers.
     *
     * @param string $a The dividend.
     * @param string $b The divisor, must not be zero.
     *
     * @return string The remainder.
     *
     * @pure
     */
    abstract public function div_r(string $a, string $b): string;
    /**
     * Returns the quotient and remainder of the division of two numbers.
     *
     * @param string $a The dividend.
     * @param string $b The divisor, must not be zero.
     *
     * @return array{string, string} An array containing the quotient and remainder.
     *
     * @pure
     */
    abstract public function div_qr(string $a, string $b): array;
    /**
     * Exponentiates a number.
     *
     * @param string $a The base number.
     * @param int    $e The exponent, validated as a non-negative integer.
     *
     * @return string The power.
     *
     * @pure
     */
    abstract public function pow(string $a, int $e): string;
    /**
     * @param string $b The modulus; must not be zero.
     *
     * @pure
     */
    public function mod(string $a, string $b): string
    {
        return $this->div_r($this->add($this->div_r($a, $b), $b), $b);
    }
    /**
     * Returns the modular multiplicative inverse of $x modulo $m.
     *
     * If $x has no multiplicative inverse mod m, this method must return null.
     *
     * This method can be overridden by the concrete implementation if the underlying library has built-in support.
     *
     * @param string $m The modulus; must not be negative or zero.
     *
     * @pure
     */
    public function mod_inverse(string $x, string $m): ?string
    {
        if ($m === '1') {
            return '0';
        }
        $mod_val = $x;
        if ($x[0] === '-' || $this->cmp($this->abs($x), $m) >= 0) {
            $mod_val = $this->mod($x, $m);
        }
        [$g, $x] = $this->gcd_extended($mod_val, $m);
        if ($g !== '1') {
            return null;
        }
        return $this->mod($this->add($this->mod($x, $m), $m), $m);
    }
    /**
     * Raises a number into power with modulo.
     *
     * @param string $base The base number.
     * @param string $exp  The exponent; must be positive or zero.
     * @param string $mod  The modulus; must be strictly positive.
     *
     * @pure
     */
    abstract public function mod_pow(string $base, string $exp, string $mod): string;
    /**
     * Returns the greatest common divisor of the two numbers.
     *
     * This method can be overridden by the concrete implementation if the underlying library
     * has built-in support for GCD calculations.
     *
     * @return string The GCD, always positive, or zero if both arguments are zero.
     *
     * @pure
     */
    public function gcd(string $a, string $b): string
    {
        if ($a === '0') {
            return $this->abs($b);
        }
        if ($b === '0') {
            return $this->abs($a);
        }
        return $this->gcd($b, $this->div_r($a, $b));
    }
    /**
     * Returns the least common multiple of the two numbers.
     *
     * This method can be overridden by the concrete implementation if the underlying library
     * has built-in support for LCM calculations.
     *
     * @return string The LCM, always positive, or zero if at least one argument is zero.
     *
     * @pure
     */
    public function lcm(string $a, string $b): string
    {
        if ($a === '0' || $b === '0') {
            return '0';
        }
        return $this->div_q($this->abs($this->mul($a, $b)), $this->gcd($a, $b));
    }
    /**
     * Returns the square root of the given number, rounded down.
     *
     * The result is the largest x such that x² ≤ n.
     * The input MUST NOT be negative.
     *
     * @pure
     */
    abstract public function sqrt(string $n): string;
    /**
     * Converts a number from an arbitrary base.
     *
     * This method can be overridden by the concrete implementation if the underlying library
     * has built-in support for base conversion.
     *
     * @param string $number The number, positive or zero, non-empty, case-insensitively validated for the given base.
     * @param int    $base   The base of the number, validated from 2 to 36.
     *
     * @return string The converted number, following the Calculator conventions.
     *
     * @pure
     */
    public function from_base(string $number, int $base): string
    {
        return $this->from_arbitrary_base(strtolower($number), self::ALPHABET, $base);
    }
    /**
     * Converts a number to an arbitrary base.
     *
     * This method can be overridden by the concrete implementation if the underlying library
     * has built-in support for base conversion.
     *
     * @param string $number The number to convert, following the Calculator conventions.
     * @param int    $base   The base to convert to, validated from 2 to 36.
     *
     * @return string The converted number, lowercase.
     *
     * @pure
     */
    public function to_base(string $number, int $base): string
    {
        $negative = $number[0] === '-';
        if ($negative) {
            $number = substr($number, 1);
        }
        $number = $this->to_arbitrary_base($number, self::ALPHABET, $base);
        if ($negative) {
            return '-' . $number;
        }
        return $number;
    }
    /**
     * Converts a non-negative number in an arbitrary base using a custom alphabet, to base 10.
     *
     * @param string $number   The number to convert, validated as a non-empty string,
     *                         containing only chars in the given alphabet/base.
     * @param string $alphabet The alphabet that contains every digit, validated as 2 chars minimum.
     * @param int    $base     The base of the number, validated from 2 to alphabet length.
     *
     * @return string The number in base 10, following the Calculator conventions.
     *
     * @pure
     */
    final public function from_arbitrary_base(string $number, string $alphabet, int $base): string
    {
        // remove leading "zeros"
        $number = ltrim($number, $alphabet[0]);
        if ($number === '') {
            return '0';
        }
        // optimize for "one"
        if ($number === $alphabet[1]) {
            return '1';
        }
        $result = '0';
        $power = '1';
        $base = (string) $base;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $index = strpos($alphabet, $number[$i]);
            if ($index !== 0) {
                $result = $this->add($result, $index === 1 ? $power : $this->mul($power, (string) $index));
            }
            if ($i !== 0) {
                $power = $this->mul($power, $base);
            }
        }
        return $result;
    }
    /**
     * Converts a non-negative number to an arbitrary base using a custom alphabet.
     *
     * @param string $number   The number to convert, positive or zero, following the Calculator conventions.
     * @param string $alphabet The alphabet that contains every digit, validated as 2 chars minimum.
     * @param int    $base     The base to convert to, validated from 2 to alphabet length.
     *
     * @return string The converted number in the given alphabet.
     *
     * @pure
     */
    final public function to_arbitrary_base(string $number, string $alphabet, int $base): string
    {
        if ($number === '0') {
            return $alphabet[0];
        }
        $base = (string) $base;
        $result = '';
        while ($number !== '0') {
            [$number, $remainder] = $this->div_qr($number, $base);
            $remainder = (int) $remainder;
            $result .= $alphabet[$remainder];
        }
        return strrev($result);
    }
    /**
     * Performs a rounded division.
     *
     * When the remainder of the division is not zero, rounding is performed according to the rounding mode provided,
     * unless RoundingMode::Unnecessary is used, in which case the method returns null.
     *
     * @param string       $a            The dividend.
     * @param string       $b            The divisor, must not be zero.
     * @param RoundingMode $roundingMode The rounding mode.
     *
     * @pure
     */
    final public function div_round(string $a, string $b, Rounding_Mode $rounding_mode): ?string
    {
        [$quotient, $remainder] = $this->div_qr($a, $b);
        $has_discarded_fraction = $remainder !== '0';
        $is_positive_or_zero = ($a[0] === '-') === ($b[0] === '-');
        $discarded_fraction_sign = function () use ($remainder, $b): int {
            $r = $this->abs($this->mul($remainder, '2'));
            $b = $this->abs($b);
            return $this->cmp($r, $b);
        };
        $increment = false;
        switch ($rounding_mode) {
            case Rounding_Mode::Unnecessary:
                if ($has_discarded_fraction) {
                    return null;
                }
                break;
            case Rounding_Mode::Up:
                $increment = $has_discarded_fraction;
                break;
            case Rounding_Mode::Down:
                break;
            case Rounding_Mode::Ceiling:
                $increment = $has_discarded_fraction && $is_positive_or_zero;
                break;
            case Rounding_Mode::Floor:
                $increment = $has_discarded_fraction && !$is_positive_or_zero;
                break;
            case Rounding_Mode::HalfUp:
                $increment = $discarded_fraction_sign() >= 0;
                break;
            case Rounding_Mode::HalfDown:
                $increment = $discarded_fraction_sign() > 0;
                break;
            case Rounding_Mode::HalfCeiling:
                $increment = $is_positive_or_zero ? $discarded_fraction_sign() >= 0 : $discarded_fraction_sign() > 0;
                break;
            case Rounding_Mode::HalfFloor:
                $increment = $is_positive_or_zero ? $discarded_fraction_sign() > 0 : $discarded_fraction_sign() >= 0;
                break;
            case Rounding_Mode::HalfEven:
                $last_digit = (int) $quotient[-1];
                $last_digit_is_even = $last_digit % 2 === 0;
                $increment = $last_digit_is_even ? $discarded_fraction_sign() > 0 : $discarded_fraction_sign() >= 0;
                break;
        }
        if ($increment) {
            return $this->add($quotient, $is_positive_or_zero ? '1' : '-1');
        }
        return $quotient;
    }
    /**
     * Calculates bitwise AND of two numbers.
     *
     * This method can be overridden by the concrete implementation if the underlying library
     * has built-in support for bitwise operations.
     *
     * @pure
     */
    public function and(string $a, string $b): string
    {
        return $this->bitwise('and', $a, $b);
    }
    /**
     * Calculates bitwise OR of two numbers.
     *
     * This method can be overridden by the concrete implementation if the underlying library
     * has built-in support for bitwise operations.
     *
     * @pure
     */
    public function or(string $a, string $b): string
    {
        return $this->bitwise('or', $a, $b);
    }
    /**
     * Calculates bitwise XOR of two numbers.
     *
     * This method can be overridden by the concrete implementation if the underlying library
     * has built-in support for bitwise operations.
     *
     * @pure
     */
    public function xor(string $a, string $b): string
    {
        return $this->bitwise('xor', $a, $b);
    }
    /**
     * Extracts the sign & digits of the operands.
     *
     * @return array{bool, bool, string, string} Whether $a and $b are negative, followed by their digits.
     *
     * @pure
     */
    final protected function init(string $a, string $b): array
    {
        return [$a_neg = $a[0] === '-', $b_neg = $b[0] === '-', $a_neg ? substr($a, 1) : $a, $b_neg ? substr($b, 1) : $b];
    }
    /**
     * @return array{string, string, string} GCD, X, Y
     *
     * @pure
     */
    private function gcd_extended(string $a, string $b): array
    {
        if ($a === '0') {
            return [$b, '0', '1'];
        }
        [$gcd, $x1, $y1] = $this->gcd_extended($this->mod($b, $a), $a);
        $x = $this->sub($y1, $this->mul($this->div_q($b, $a), $x1));
        $y = $x1;
        return [$gcd, $x, $y];
    }
    /**
     * Performs a bitwise operation on a decimal number.
     *
     * @param 'and'|'or'|'xor' $operator The operator to use.
     * @param string           $a        The left operand.
     * @param string           $b        The right operand.
     *
     * @pure
     */
    private function bitwise(string $operator, string $a, string $b): string
    {
        [$a_neg, $b_neg, $a_dig, $b_dig] = $this->init($a, $b);
        $a_bin = $this->to_binary($a_dig);
        $b_bin = $this->to_binary($b_dig);
        $a_len = strlen($a_bin);
        $b_len = strlen($b_bin);
        if ($a_len > $b_len) {
            $b_bin = str_repeat("\x00", $a_len - $b_len) . $b_bin;
        } elseif ($b_len > $a_len) {
            $a_bin = str_repeat("\x00", $b_len - $a_len) . $a_bin;
        }
        if ($a_neg) {
            $a_bin = $this->twos_complement($a_bin);
        }
        if ($b_neg) {
            $b_bin = $this->twos_complement($b_bin);
        }
        $value = match ($operator) {
            'and' => $a_bin & $b_bin,
            'or' => $a_bin | $b_bin,
            'xor' => $a_bin ^ $b_bin,
        };
        $negative = match ($operator) {
            'and' => $a_neg and $b_neg,
            'or' => $a_neg or $b_neg,
            'xor' => $a_neg xor $b_neg,
        };
        if ($negative) {
            $value = $this->twos_complement($value);
        }
        $result = $this->to_decimal($value);
        return $negative ? $this->neg($result) : $result;
    }
    /**
     * @param string $number A positive, binary number.
     *
     * @pure
     */
    private function twos_complement(string $number): string
    {
        $xor = str_repeat("\xff", strlen($number));
        $number ^= $xor;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $byte = ord($number[$i]);
            if (++$byte !== 256) {
                $number[$i] = chr($byte);
                break;
            }
            $number[$i] = "\x00";
            if ($i === 0) {
                $number = "\x01" . $number;
            }
        }
        return $number;
    }
    /**
     * Converts a decimal number to a binary string.
     *
     * @param string $number The number to convert, positive or zero, only digits.
     *
     * @pure
     */
    private function to_binary(string $number): string
    {
        $result = '';
        while ($number !== '0') {
            [$number, $remainder] = $this->div_qr($number, '256');
            $result .= chr((int) $remainder);
        }
        return strrev($result);
    }
    /**
     * Returns the positive decimal representation of a binary number.
     *
     * @param string $bytes The bytes representing the number.
     *
     * @pure
     */
    private function to_decimal(string $bytes): string
    {
        $result = '0';
        $power = '1';
        for ($i = strlen($bytes) - 1; $i >= 0; $i--) {
            $index = ord($bytes[$i]);
            if ($index !== 0) {
                $result = $this->add($result, $index === 1 ? $power : $this->mul($power, (string) $index));
            }
            if ($i !== 0) {
                $power = $this->mul($power, '256');
            }
        }
        return $result;
    }
}