<?php

namespace Tests\Unit;

use App\Support\ZkTecoMemoryStatistics;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ZkTecoMemoryStatisticsTest extends TestCase
{
    #[Test]
    public function it_parses_pyzk_eighty_byte_capacity_layout(): void
    {
        $fields = array_fill(0, 20, 0);
        $fields[4] = 120;
        $fields[6] = 240;
        $fields[8] = 1500;
        $fields[14] = 3000;
        $fields[15] = 500;
        $fields[16] = 80000;
        $fields[17] = 2760;
        $fields[18] = 380;
        $fields[19] = 78500;

        $payload = pack('V20', ...$fields);

        $stats = ZkTecoMemoryStatistics::parse($payload);

        $this->assertSame(120, $stats['users_used']);
        $this->assertSame(500, $stats['users_capacity']);
        $this->assertSame(380, $stats['users_free']);
        $this->assertSame(1500, $stats['attendance_used']);
        $this->assertSame(80000, $stats['attendance_capacity']);
        $this->assertSame(78500, $stats['attendance_free']);
    }
}
