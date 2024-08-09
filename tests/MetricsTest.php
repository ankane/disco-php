<?php

use PHPUnit\Framework\TestCase;

use Disco\Metrics;

final class MetricsTest extends TestCase
{
    public function testRmse()
    {
        $this->assertEqualsWithDelta(2, Metrics::rmse([0, 0, 0, 1, 1], [0, 2, 4, 1, 1]), 0.001);
    }
}
