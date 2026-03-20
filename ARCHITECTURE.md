# Architecture: math (brick/math)

## Purpose

An arbitrary-precision arithmetic library for PHP. Provides `BigInteger`, `BigDecimal`, and `BigRational` value objects with exact arithmetic — no floating-point rounding errors.

## Directory Structure

```
src/
  Big_Number.php          — Abstract sealed base class for all three number types
  Big_Integer.php         — Arbitrary-precision integer arithmetic
  Big_Decimal.php         — Arbitrary-precision decimal (fixed-point) arithmetic
  Big_Rational.php        — Exact rational (fraction) arithmetic
  Rounding_Mode.php       — Enum of rounding modes (UP, DOWN, CEILING, FLOOR, HALF_UP, etc.)
  Exception/
    Math_Exception.php    — Base exception
    Division_By_Zero_Exception.php
    Integer_Overflow_Exception.php
    Invalid_Argument_Exception.php
    Negative_Number_Exception.php
    No_Inverse_Exception.php
    Number_Format_Exception.php
    Random_Source_Exception.php
    Rounding_Necessary_Exception.php
    Unsupported_Platform_Exception.php
  Internal/
    Calculator.php          — Abstract calculator backend
    Calculator_Registry.php — Detects and registers available backends
    Bc_Math_Calculator.php  — Backend using PHP bcmath extension
    Gmp_Calculator.php      — Backend using PHP GMP extension
    Native_Calculator.php   — Pure PHP fallback (no extensions required)
    Decimal_Helper.php      — Internal string-based decimal helpers
    Safe.php                — Wrappers that throw instead of returning false
```

## Key Design Decisions

- **Value objects**: All three number types are immutable; every operation returns a new instance
- **Readonly abstract class**: `Big_Number` is `abstract readonly`, enforcing immutability at the language level
- **Backend detection**: `Calculator_Registry` auto-selects GMP > bcmath > native at runtime; all three produce identical results
- **Sealed hierarchy**: `@phpstan-sealed` prevents subclassing outside `BigInteger|BigDecimal|BigRational` while keeping the base class in the public API
- **Strict exception hierarchy**: Each error condition has its own typed exception, all extending `Math_Exception`

## Extension Points

- Use `BigNumber::of($value)` as the entry point; it accepts int, string, float, or another `BigNumber`
- Call `BigInteger::random(int $numBits)` for cryptographically random large integers
- Override the calculator backend via `Calculator_Registry::register()` for testing or custom environments

## Dependency Flow

```
BigNumber::of(mixed $value): BigInteger|BigDecimal|BigRational
  └── Internal\Calculator (abstract)
        ├── GmpCalculator       (prefers GMP extension)
        ├── BcMathCalculator    (prefers bcmath extension)
        └── NativeCalculator    (pure PHP, always available)
```
