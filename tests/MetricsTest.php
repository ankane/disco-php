<?php

use PHPUnit\Framework\TestCase;

final class MetricsTest extends TestCase
{
    public function testRmse()
    {
        $this->assertEqualsWithDelta(2, Disco\Metrics::rmse([0, 0, 0, 1, 1], [0, 2, 4, 1, 1]), 0.001);
    }
}
