<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Brick\Math\Big_Integer;
use Brick\Math\Big_Decimal;
use Brick\Math\Big_Rational;
use Brick\Math\Rounding_Mode;

// --- Example 1: Arbitrary-precision integers ---
$a = Big_Integer::of('9999999999999999999999999999');
$b = Big_Integer::of('1111111111111111111111111111');

echo "Sum:     " . $a->plus($b) . "\n";          // 11111111111111111111111111110
echo "Product: " . $a->multiplied_by($b) . "\n";  // exact, no overflow
echo "Power:   " . Big_Integer::of(2)->power(100) . "\n\n";

// --- Example 2: Exact decimal arithmetic (no floating-point errors) ---
$price = Big_Decimal::of('19.99');
$tax_rate = Big_Decimal::of('0.085');
$tax = $price->multiplied_by($tax_rate)->to_scale(2, Rounding_Mode::HALF_UP);

echo "Price: {$price}\n";
echo "Tax:   {$tax}\n";
echo "Total: " . $price->plus($tax) . "\n\n";

// Compare: native float loses precision
$native = 0.1 + 0.2;
echo "Native 0.1 + 0.2 = {$native}\n"; // 0.30000000000000002
$exact = Big_Decimal::of('0.1')->plus(Big_Decimal::of('0.2'));
echo "Exact  0.1 + 0.2 = {$exact}\n\n"; // 0.3

// --- Example 3: Rational numbers (exact fractions) ---
$one_third = Big_Rational::of('1/3');
$two_thirds = Big_Rational::of('2/3');

echo "1/3 + 2/3 = " . $one_third->plus($two_thirds) . "\n"; // 1
echo "1/3 * 2/3 = " . $one_third->multiplied_by($two_thirds) . "\n\n"; // 2/9

// --- Example 4: Rounding modes ---
$value = Big_Decimal::of('2.5555');
echo "HALF_UP:   " . $value->to_scale(2, Rounding_Mode::HALF_UP) . "\n";   // 2.56
echo "HALF_DOWN: " . $value->to_scale(2, Rounding_Mode::HALF_DOWN) . "\n"; // 2.56
echo "FLOOR:     " . $value->to_scale(2, Rounding_Mode::FLOOR) . "\n";     // 2.55
echo "CEILING:   " . $value->to_scale(2, Rounding_Mode::CEILING) . "\n";   // 2.56
