<?php

declare(strict_types=1);

use Brick\Math\Internal\Calculator;
use Brick\Math\Internal\Calculator_Registry;

require __DIR__ . '/vendor/autoload.php';

function getCalculatorImplementation(): Calculator
{
    switch ($calculator = getenv('CALCULATOR')) {
        case 'GMP':
            $calculator = new Calculator\Gmp_Calculator();

            break;

        case 'BCMath':
            $calculator = new Calculator\Bc_Math_Calculator();

            break;

        case 'Native':
            $calculator = new Calculator\Native_Calculator();

            break;

        default:
            if ($calculator === false) {
                echo 'CALCULATOR environment variable not set!' . PHP_EOL;
            } else {
                echo 'Unknown calculator: ' . $calculator . PHP_EOL;
            }

            echo 'Example usage: CALCULATOR={calculator} vendor/bin/phpunit' . PHP_EOL;
            echo 'Available calculators: GMP, BCMath, Native' . PHP_EOL;
            exit(1);
    }

    echo 'Using ', get_class($calculator), PHP_EOL;

    return $calculator;
}

Calculator_Registry::set(getCalculatorImplementation());

$scale = getenv('BCMATH_DEFAULT_SCALE');

if ($scale !== false) {
    echo "Using bcscale($scale)", PHP_EOL;
    bcscale((int) $scale);
}
