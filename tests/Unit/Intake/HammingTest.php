<?php

namespace Tests\Unit\Intake;

use Modules\Intake\Support\Hamming;
use PHPUnit\Framework\TestCase;

class HammingTest extends TestCase
{
    public function test_identical_is_zero(): void
    {
        $this->assertSame(0, Hamming::distance('ffff0000ffff0000', 'ffff0000ffff0000'));
    }

    public function test_boundary_6_is_near_duplicate_and_7_is_not(): void
    {
        $base = '0000000000000000';
        $six = '000000000000003f';    // 6 bits set
        $seven = '000000000000007f';  // 7 bits set
        $this->assertSame(6, Hamming::distance($base, $six));
        $this->assertSame(7, Hamming::distance($base, $seven));
        $this->assertTrue(Hamming::isNearDuplicate($base, $six));
        $this->assertFalse(Hamming::isNearDuplicate($base, $seven));
    }

    public function test_rejects_bad_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Hamming::distance('abc', 'abcd');
    }
}
