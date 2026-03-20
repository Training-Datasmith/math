<?php

declare (strict_types=1);
namespace Brick\Math\Internal\Calculator;

use function assert;
use Brick\Math\Internal\Calculator;
use function in_array;
use function intdiv;
use function is_int;
use function ltrim;
use Override;
use const PHP_INT_SIZE;
use function str_pad;
use const STR_PAD_LEFT;
use function str_repeat;
use function strcmp;
use function strlen;
use function substr;
/**
 * Calculator implementation using only native PHP code.
 *
 * @internal
 */
final readonly class Native_Calculator extends Calculator
{
    /**
     * The max number of digits the platform can natively add, subtract, multiply or divide without overflow.
     * For multiplication, this represents the max sum of the lengths of both operands.
     *
     * In addition, it is assumed that an extra digit can hold a carry (1) without overflowing.
     * Example: 32-bit: max number 1,999,999,999 (9 digits + carry)
     *          64-bit: max number 1,999,999,999,999,999,999 (18 digits + carry)
     */
    private int $max_digits;
    /**
     * @pure
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->max_digits = match (PHP_INT_SIZE) {
            4 => 9,
            8 => 18,
        };
    }
    #[Override]
    public function add(string $a, string $b): string
    {
        /**
         * @var numeric-string $a
         * @var numeric-string $b
         */
        $result = $a + $b;
        if (is_int($result)) {
            return (string) $result;
        }
        if ($a === '0') {
            return $b;
        }
        if ($b === '0') {
            return $a;
        }
        [$a_neg, $b_neg, $a_dig, $b_dig] = $this->init($a, $b);
        $result = $a_neg === $b_neg ? $this->do_add($a_dig, $b_dig) : $this->do_sub($a_dig, $b_dig);
        if ($a_neg) {
            return $this->neg($result);
        }
        return $result;
    }
    #[Override]
    public function sub(string $a, string $b): string
    {
        return $this->add($a, $this->neg($b));
    }
    #[Override]
    public function mul(string $a, string $b): string
    {
        /**
         * @var numeric-string $a
         * @var numeric-string $b
         */
        $result = $a * $b;
        if (is_int($result)) {
            return (string) $result;
        }
        if ($a === '0' || $b === '0') {
            return '0';
        }
        if ($a === '1') {
            return $b;
        }
        if ($b === '1') {
            return $a;
        }
        if ($a === '-1') {
            return $this->neg($b);
        }
        if ($b === '-1') {
            return $this->neg($a);
        }
        [$a_neg, $b_neg, $a_dig, $b_dig] = $this->init($a, $b);
        $result = $this->do_mul($a_dig, $b_dig);
        if ($a_neg !== $b_neg) {
            return $this->neg($result);
        }
        return $result;
    }
    #[Override]
    public function div_q(string $a, string $b): string
    {
        return $this->div_qr($a, $b)[0];
    }
    #[Override]
    public function div_r(string $a, string $b): string
    {
        return $this->div_qr($a, $b)[1];
    }
    #[Override]
    public function div_qr(string $a, string $b): array
    {
        if ($a === '0') {
            return ['0', '0'];
        }
        if ($a === $b) {
            return ['1', '0'];
        }
        if ($b === '1') {
            return [$a, '0'];
        }
        if ($b === '-1') {
            return [$this->neg($a), '0'];
        }
        /** @var numeric-string $a */
        $na = $a * 1;
        // cast to number
        if (is_int($na)) {
            /** @var numeric-string $b */
            $nb = $b * 1;
            if (is_int($nb)) {
                // the only division that may overflow is PHP_INT_MIN / -1,
                // which cannot happen here as we've already handled a divisor of -1 above.
                $q = intdiv($na, $nb);
                $r = $na % $nb;
                return [(string) $q, (string) $r];
            }
        }
        [$a_neg, $b_neg, $a_dig, $b_dig] = $this->init($a, $b);
        [$q, $r] = $this->do_div($a_dig, $b_dig);
        if ($a_neg !== $b_neg) {
            $q = $this->neg($q);
        }
        if ($a_neg) {
            $r = $this->neg($r);
        }
        return [$q, $r];
    }
    #[Override]
    public function pow(string $a, int $e): string
    {
        if ($e === 0) {
            return '1';
        }
        if ($e === 1) {
            return $a;
        }
        $odd = $e % 2;
        $e -= $odd;
        $aa = $this->mul($a, $a);
        $result = $this->pow($aa, $e / 2);
        if ($odd === 1) {
            return $this->mul($result, $a);
        }
        return $result;
    }
    /**
     * Algorithm from: https://www.geeksforgeeks.org/modular-exponentiation-power-in-modular-arithmetic/.
     */
    #[Override]
    public function mod_pow(string $base, string $exp, string $mod): string
    {
        // normalize to Euclidean representative so modPow() stays consistent with mod()
        $base = $this->mod($base, $mod);
        // special case: the algorithm below fails with power 0 mod 1 (returns 1 instead of 0)
        if ($exp === '0' && $mod === '1') {
            return '0';
        }
        $x = $base;
        $res = '1';
        // numbers are positive, so we can use remainder instead of modulo
        $x = $this->div_r($x, $mod);
        while ($exp !== '0') {
            if (in_array($exp[-1], ['1', '3', '5', '7', '9'])) {
                // odd
                $res = $this->div_r($this->mul($res, $x), $mod);
            }
            $exp = $this->div_q($exp, '2');
            $x = $this->div_r($this->mul($x, $x), $mod);
        }
        return $res;
    }
    /**
     * Adapted from https://cp-algorithms.com/num_methods/roots_newton.html.
     */
    #[Override]
    public function sqrt(string $n): string
    {
        if ($n === '0') {
            return '0';
        }
        // initial approximation
        $x = str_repeat('9', intdiv(strlen($n), 2) ?: 1);
        $decreased = false;
        for (;;) {
            $nx = $this->div_q($this->add($x, $this->div_q($n, $x)), '2');
            if ($x === $nx || $this->cmp($nx, $x) > 0 && $decreased) {
                break;
            }
            $decreased = $this->cmp($nx, $x) < 0;
            $x = $nx;
        }
        return $x;
    }
    /**
     * Performs the addition of two non-signed large integers.
     *
     * @pure
     */
    private function do_add(string $a, string $b): string
    {
        [$a, $b, $length] = $this->pad($a, $b);
        $carry = 0;
        $result = '';
        for ($i = $length - $this->max_digits;; $i -= $this->max_digits) {
            $block_length = $this->max_digits;
            if ($i < 0) {
                $block_length += $i;
                $i = 0;
            }
            /** @var numeric-string $blockA */
            $block_a = substr($a, $i, $block_length);
            /** @var numeric-string $blockB */
            $block_b = substr($b, $i, $block_length);
            $sum = (string) ($block_a + $block_b + $carry);
            $sum_length = strlen($sum);
            if ($sum_length > $block_length) {
                $sum = substr($sum, 1);
                $carry = 1;
            } else {
                if ($sum_length < $block_length) {
                    $sum = str_repeat('0', $block_length - $sum_length) . $sum;
                }
                $carry = 0;
            }
            $result = $sum . $result;
            if ($i === 0) {
                break;
            }
        }
        if ($carry === 1) {
            return '1' . $result;
        }
        return $result;
    }
    /**
     * Performs the subtraction of two non-signed large integers.
     *
     * @pure
     */
    private function do_sub(string $a, string $b): string
    {
        if ($a === $b) {
            return '0';
        }
        // Ensure that we always subtract to a positive result: biggest minus smallest.
        $cmp = $this->do_cmp($a, $b);
        $invert = $cmp === -1;
        if ($invert) {
            $c = $a;
            $a = $b;
            $b = $c;
        }
        [$a, $b, $length] = $this->pad($a, $b);
        $carry = 0;
        $result = '';
        $complement = 10 ** $this->max_digits;
        for ($i = $length - $this->max_digits;; $i -= $this->max_digits) {
            $block_length = $this->max_digits;
            if ($i < 0) {
                $block_length += $i;
                $i = 0;
            }
            /** @var numeric-string $blockA */
            $block_a = substr($a, $i, $block_length);
            /** @var numeric-string $blockB */
            $block_b = substr($b, $i, $block_length);
            $sum = $block_a - $block_b - $carry;
            if ($sum < 0) {
                $sum += $complement;
                $carry = 1;
            } else {
                $carry = 0;
            }
            $sum = (string) $sum;
            $sum_length = strlen($sum);
            if ($sum_length < $block_length) {
                $sum = str_repeat('0', $block_length - $sum_length) . $sum;
            }
            $result = $sum . $result;
            if ($i === 0) {
                break;
            }
        }
        // Carry cannot be 1 when the loop ends, as a > b
        assert($carry === 0);
        $result = ltrim($result, '0');
        if ($invert) {
            return $this->neg($result);
        }
        return $result;
    }
    /**
     * Performs the multiplication of two non-signed large integers.
     *
     * @pure
     */
    private function do_mul(string $a, string $b): string
    {
        $x = strlen($a);
        $y = strlen($b);
        $max_digits = intdiv($this->max_digits, 2);
        $complement = 10 ** $max_digits;
        $result = '0';
        for ($i = $x - $max_digits;; $i -= $max_digits) {
            $block_a_length = $max_digits;
            if ($i < 0) {
                $block_a_length += $i;
                $i = 0;
            }
            $block_a = (int) substr($a, $i, $block_a_length);
            $line = '';
            $carry = 0;
            for ($j = $y - $max_digits;; $j -= $max_digits) {
                $block_b_length = $max_digits;
                if ($j < 0) {
                    $block_b_length += $j;
                    $j = 0;
                }
                $block_b = (int) substr($b, $j, $block_b_length);
                $mul = $block_a * $block_b + $carry;
                $value = $mul % $complement;
                $carry = ($mul - $value) / $complement;
                $value = (string) $value;
                $value = str_pad($value, $max_digits, '0', STR_PAD_LEFT);
                $line = $value . $line;
                if ($j === 0) {
                    break;
                }
            }
            if ($carry !== 0) {
                $line = $carry . $line;
            }
            $line = ltrim($line, '0');
            if ($line !== '') {
                $line .= str_repeat('0', $x - $block_a_length - $i);
                $result = $this->add($result, $line);
            }
            if ($i === 0) {
                break;
            }
        }
        return $result;
    }
    /**
     * Performs the division of two non-signed large integers.
     *
     * @return string[] The quotient and remainder.
     *
     * @pure
     */
    private function do_div(string $a, string $b): array
    {
        $cmp = $this->do_cmp($a, $b);
        if ($cmp === -1) {
            return ['0', $a];
        }
        $x = strlen($a);
        $y = strlen($b);
        // we now know that a >= b && x >= y
        $q = '0';
        // quotient
        $r = $a;
        // remainder
        $z = $y;
        // focus length, always $y or $y+1
        /** @var numeric-string $b */
        $nb = $b * 1;
        // cast to number
        // performance optimization in cases where the remainder will never cause int overflow
        if (is_int(($nb - 1) * 10 + 9)) {
            $r = (int) substr($a, 0, $z - 1);
            for ($i = $z - 1; $i < $x; $i++) {
                $n = $r * 10 + (int) $a[$i];
                /** @var int $nb */
                $q .= intdiv($n, $nb);
                $r = $n % $nb;
            }
            return [ltrim($q, '0') ?: '0', (string) $r];
        }
        for (;;) {
            $focus = substr($a, 0, $z);
            $cmp = $this->do_cmp($focus, $b);
            if ($cmp === -1) {
                if ($z === $x) {
                    // remainder < dividend
                    break;
                }
                $z++;
            }
            $zeros = str_repeat('0', $x - $z);
            $q = $this->add($q, '1' . $zeros);
            $a = $this->sub($a, $b . $zeros);
            $r = $a;
            if ($r === '0') {
                // remainder == 0
                break;
            }
            $x = strlen($a);
            if ($x < $y) {
                // remainder < dividend
                break;
            }
            $z = $y;
        }
        return [$q, $r];
    }
    /**
     * Compares two non-signed large numbers.
     *
     * @return -1|0|1
     *
     * @pure
     */
    private function do_cmp(string $a, string $b): int
    {
        $x = strlen($a);
        $y = strlen($b);
        $cmp = $x <=> $y;
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($a, $b) <=> 0;
        // enforce -1|0|1
    }
    /**
     * Pads the left of one of the given numbers with zeros if necessary to make both numbers the same length.
     *
     * The numbers must only consist of digits, without leading minus sign.
     *
     * @return array{string, string, int}
     *
     * @pure
     */
    private function pad(string $a, string $b): array
    {
        $x = strlen($a);
        $y = strlen($b);
        if ($x > $y) {
            $b = str_repeat('0', $x - $y) . $b;
            return [$a, $b, $x];
        }
        if ($x < $y) {
            $a = str_repeat('0', $y - $x) . $a;
            return [$a, $b, $y];
        }
        return [$a, $b, $x];
    }
}