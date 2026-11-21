<?php

namespace Tests\Unit;

use App\Support\NumberFormat;
use PHPUnit\Framework\TestCase;

class NumberFormatTest extends TestCase
{
    public function test_quantity_strips_misleading_trailing_decimal_zeros(): void
    {
        $this->assertSame('5', NumberFormat::quantity('5.000'));
        $this->assertSame('3', NumberFormat::quantity('3.000'));
        $this->assertSame('999', NumberFormat::quantity('999.000'));
        $this->assertSame('1.000', NumberFormat::quantity('1000.000'));
        $this->assertSame('0', NumberFormat::quantity('0.000'));
        $this->assertSame('0', NumberFormat::quantity('-0.000'));
        $this->assertSame('0', NumberFormat::quantity(null));
        $this->assertSame('-3', NumberFormat::quantity('-3.000'));
    }

    public function test_quantity_preserves_meaningful_decimals_with_indonesian_comma(): void
    {
        $this->assertSame('2,5', NumberFormat::quantity('2.500'));
        $this->assertSame('12,75', NumberFormat::quantity('12.750'));
        $this->assertSame('0,125', NumberFormat::quantity('0.125'));
    }

    public function test_signed_quantity_adds_plus_for_positive_and_keeps_minus_for_negative(): void
    {
        $this->assertSame('+5', NumberFormat::signedQuantity('5.000'));
        $this->assertSame('-2', NumberFormat::signedQuantity('-2.000'));
        $this->assertSame('+2,5', NumberFormat::signedQuantity('2.500'));
        $this->assertSame('0', NumberFormat::signedQuantity('0.000'));
    }

    public function test_input_formats_without_thousands_separator_or_trailing_zeros(): void
    {
        $this->assertSame('5', NumberFormat::input('5.000'));
        $this->assertSame('999', NumberFormat::input('999.000'));
        $this->assertSame('1000', NumberFormat::input('1000.000'));
        $this->assertSame('2.5', NumberFormat::input('2.500'));
        $this->assertSame('', NumberFormat::input(null));
    }
}
