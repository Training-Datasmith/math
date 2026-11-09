<?php

declare(strict_types=1);

namespace Brick\Math\Tests;

use Brick\Math\Internal\Calculator;
use Brick\Math\Internal\Calculator_Registry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Calculator implementation detection.
 */
class Calculator_Detect_Test extends TestCase
{
    public function testGetWithNoCalculatorSetDetectsCalculator(): void
    {
        $currentCalculator = Calculator_Registry::get();

        Calculator_Registry::set(null);
        self::assertInstanceOf(Calculator::class, Calculator_Registry::get());

        Calculator_Registry::set($currentCalculator);
    }
}
