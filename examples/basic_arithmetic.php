<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Brick\Math\BigInteger;
use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

// --- Example 1: Arbitrary-precision integers ---
$a = BigInteger::of('9999999999999999999999999999');
$b = BigInteger::of('1111111111111111111111111111');

echo "Sum:     " . $a->plus($b) . "\n";          // 11111111111111111111111111110
echo "Product: " . $a->multipliedBy($b) . "\n";  // exact, no overflow
echo "Power:   " . BigInteger::of(2)->power(100) . "\n\n";

// --- Example 2: Exact decimal arithmetic (no floating-point errors) ---
$price = BigDecimal::of('19.99');
$tax_rate = BigDecimal::of('0.085');
$tax = $price->multipliedBy($tax_rate)->toScale(2, RoundingMode::HALF_UP);

echo "Price: {$price}\n";
echo "Tax:   {$tax}\n";
echo "Total: " . $price->plus($tax) . "\n\n";

// Compare: native float loses precision
$native = 0.1 + 0.2;
echo "Native 0.1 + 0.2 = {$native}\n"; // 0.30000000000000002
$exact = BigDecimal::of('0.1')->plus(BigDecimal::of('0.2'));
echo "Exact  0.1 + 0.2 = {$exact}\n\n"; // 0.3

// --- Example 3: Rational numbers (exact fractions) ---
$one_third = BigRational::of('1/3');
$two_thirds = BigRational::of('2/3');

echo "1/3 + 2/3 = " . $one_third->plus($two_thirds) . "\n"; // 1
echo "1/3 * 2/3 = " . $one_third->multipliedBy($two_thirds) . "\n\n"; // 2/9

// --- Example 4: Rounding modes ---
$value = BigDecimal::of('2.5555');
echo "HALF_UP:   " . $value->toScale(2, RoundingMode::HALF_UP) . "\n";   // 2.56
echo "HALF_DOWN: " . $value->toScale(2, RoundingMode::HALF_DOWN) . "\n"; // 2.56
echo "FLOOR:     " . $value->toScale(2, RoundingMode::FLOOR) . "\n";     // 2.55
echo "CEILING:   " . $value->toScale(2, RoundingMode::CEILING) . "\n";   // 2.56
