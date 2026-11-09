<?php

declare(strict_types=1);

namespace Brick\Math\Tests;

use Brick\Math\Big_Decimal;
use Brick\Math\Big_Integer;
use Brick\Math\Big_Number;
use Brick\Math\Big_Rational;
use Brick\Math\Exception\Number_Format_Exception;
use Brick\Math\Exception\Rounding_Necessary_Exception;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

/**
 * Unit tests for class Big_Number.
 *
 * Most of the tests are performed in concrete classes.
 * Only static methods that can be called on Big_Number itself may justify tests here.
 */
class Big_Number_Test extends Abstract_Test_Case
{
    #[DataProvider('providerOf')]
    public function testOf(Big_Number|int|string $value, string $expectedClass, string $expectedValue): void
    {
        $result = Big_Number::of($value);

        self::assertSame($expectedClass, $result::class);
        self::assertSame($expectedValue, $result->to_string());
    }

    #[DataProvider('providerOf')]
    public function testOfNullableWithValidInputBehavesLikeOf(mixed $value, string $expectedClass, string $expectedValue): void
    {
        $result = Big_Number::of_nullable($value);

        self::assertNotNull($result);
        self::assertSame($expectedClass, $result::class);
        self::assertSame($expectedValue, $result->to_string());
    }

    public function testOfNullableWithNullInput(): void
    {
        self::assertNull(Big_Number::of_nullable(null));
    }

    public static function providerOf(): array
    {
        return [
            [123, Big_Integer::class, '123'],
            ['1', Big_Integer::class, '1'],
            ['123', Big_Integer::class, '123'],
            ['123.0', Big_Decimal::class, '123.0'],
            ['-0.1', Big_Decimal::class, '-0.1'],
            ['.1', Big_Decimal::class, '0.1'],
            ['-.1', Big_Decimal::class, '-0.1'],
            ['1.', Big_Decimal::class, '1'],
            ['1e2', Big_Decimal::class, '100'],
            ['-1e3', Big_Decimal::class, '-1000'],
            ['1.2e3', Big_Decimal::class, '1200'],
            ['-1.2e3', Big_Decimal::class, '-1200'],
            ['1e-2', Big_Decimal::class, '0.01'],
            ['-1e-3', Big_Decimal::class, '-0.001'],
            ['2/3', Big_Rational::class, '2/3'],
            ['-1/8', Big_Rational::class, '-1/8'],
            [Big_Integer::of(123), Big_Integer::class, '123'],
            [Big_Decimal::of(123), Big_Decimal::class, '123'],
            [Big_Rational::of(123), Big_Rational::class, '123'],
        ];
    }

    public function testOfEmptyStringThrowsException(): void
    {
        $this->expectException(Number_Format_Exception::class);
        $this->expectExceptionMessageExact('The number must not be empty.');

        Big_Number::of('');
    }

    #[DataProvider('providerOfInvalidFormatThrowsException')]
    public function testOfInvalidFormatThrowsException(string $value): void
    {
        $this->expectException(Number_Format_Exception::class);
        $this->expectExceptionMessageExact(sprintf('Value "%s" does not represent a valid number.', $value));

        Big_Number::of($value);
    }

    public static function providerOfInvalidFormatThrowsException(): array
    {
        return [
            ['a'],
            [' 1'],
            ['1 '],
            ['+'],
            ['-'],
            ['+a'],
            ['-a'],
            ['a0'],
            ['0a'],
            ['1.a'],
            ['a.1'],
            ['..1'],
            ['1..'],
            ['.1.'],
            ['.'],
            ['1e'],
            ['.e'],
            ['.e1'],
            ['1e+'],
            ['1e-'],
            ['+e1'],
            ['-e2'],
            ['.e3'],
            ['123/-456'],
            ['1e4/2'],
            ['1.2/3'],
            ['1e2/3'],
            [' 1/2'],
            ['1/2 '],
            ['/'],
        ];
    }

    /**
     * @param list<Big_Number|int|string> $values
     */
    #[DataProvider('providerMin')]
    public function testMin(array $values, string $expectedClass, string $expectedValue): void
    {
        $result = Big_Number::min(...$values);

        self::assertInstanceOf($expectedClass, $result);
        self::assertSame($expectedValue, $result->to_string());
    }

    public static function providerMin(): array
    {
        return [
            [['1', '1.0', '1/1'], Big_Integer::class, '1'],
            [['1.0', '1', '1/1'], Big_Decimal::class, '1.0'],
            [['1/1', '1.0', '1'], Big_Rational::class, '1'],
            [[-3, '-4.0', '-4/1'], Big_Decimal::class, '-4.0'],
            [[-3, '-4/1', '-4.0'], Big_Rational::class, '-4'],
            [['2/3', '0.67', '0.6666666666666666666666666667'], Big_Rational::class, '2/3'],
        ];
    }

    /**
     * @param list<Big_Number|int|string> $values
     */
    #[DataProvider('providerMax')]
    public function testMax(array $values, string $expectedClass, string $expectedValue): void
    {
        $result = Big_Number::max(...$values);

        self::assertInstanceOf($expectedClass, $result);
        self::assertSame($expectedValue, $result->to_string());
    }

    public static function providerMax(): array
    {
        return [
            [['1', '1.0', '1/1'], Big_Integer::class, '1'],
            [['1.0', '1', '1/1'], Big_Decimal::class, '1.0'],
            [['1/1', '1.0', '1'], Big_Rational::class, '1'],
            [[-3, '-3.0', '-3/1'], Big_Integer::class, '-3'],
            [['1/2', '0.5', '0.50'], Big_Rational::class, '1/2'],
        ];
    }

    /**
     * @param class-string<Big_Number>    $callingClass  The Big_Number class to call sum() on.
     * @param list<Big_Number|int|string> $values        The values to add.
     * @param string                     $expectedClass The expected class name.
     * @param string                     $expectedSum   The expected sum.
     */
    #[DataProvider('providerSum')]
    public function testSum(string $callingClass, array $values, string $expectedClass, string $expectedSum): void
    {
        $sum = $callingClass::sum(...$values);

        self::assertInstanceOf($expectedClass, $sum);
        self::assertSame($expectedSum, $sum->to_string());
    }

    public static function providerSum(): array
    {
        return [
            [Big_Number::class, [-1], Big_Integer::class, '-1'],
            [Big_Number::class, [-1, '99'], Big_Integer::class, '98'],
            [Big_Integer::class, [-1, '99'], Big_Integer::class, '98'],
            [Big_Decimal::class, [-1, '99'], Big_Decimal::class, '98'],
            [Big_Rational::class, [-1, '99'], Big_Rational::class, '98'],
            [Big_Number::class, [-1, '99', '-0.7'], Big_Decimal::class, '97.3'],
            [Big_Decimal::class, [-1, '99', '-0.7'], Big_Decimal::class, '97.3'],
            [Big_Rational::class, [-1, '99', '-0.7'], Big_Rational::class, '973/10'],
            [Big_Number::class, [-1, '99', '-0.7', '3/2'], Big_Rational::class, '494/5'],
            [Big_Number::class, [-1, '3/2'], Big_Rational::class, '1/2'],
            [Big_Number::class, ['-0.5'], Big_Decimal::class, '-0.5'],
            [Big_Number::class, ['-0.5', 1], Big_Decimal::class, '0.5'],
            [Big_Number::class, ['-0.5', 1, '0.7'], Big_Decimal::class, '1.2'],
            [Big_Number::class, ['-0.5', 1, '0.7', '47/7'], Big_Rational::class, '277/35'],
            [Big_Number::class, ['-1/9'], Big_Rational::class, '-1/9'],
            [Big_Number::class, ['-1/9', 123], Big_Rational::class, '1106/9'],
            [Big_Number::class, ['-1/9', 123, '8349.3771'], Big_Rational::class, '762503939/90000'],
            [Big_Number::class, ['-1/9', '8349.3771', 123], Big_Rational::class, '762503939/90000'],
        ];
    }

    /**
     * @param class-string<Big_Number>    $callingClass The Big_Number class to call sum() on.
     * @param list<Big_Number|int|string> $values       The values to add.
     */
    #[DataProvider('providerSumThrowsRoundingNecessaryException')]
    public function testSumThrowsRoundingNecessaryException(string $callingClass, array $values, string $expectedExceptionMessage): void
    {
        $this->expectException(Rounding_Necessary_Exception::class);
        $this->expectExceptionMessageExact($expectedExceptionMessage);

        $callingClass::sum(...$values);
    }

    public static function providerSumThrowsRoundingNecessaryException(): array
    {
        return [
            [Big_Integer::class, [1, '1.5'], 'This decimal number cannot be represented as an integer without rounding.'],
            [Big_Integer::class, [1, '1/2'], 'This rational number cannot be represented as an integer without rounding.'],
            [Big_Decimal::class, ['1.5', '1/3'], 'This rational number has a non-terminating decimal expansion and cannot be represented as a decimal without rounding.'],
        ];
    }
}
