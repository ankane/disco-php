<?php

namespace Disco;

class Metrics
{
    public static function rmse($act, $exp)
    {
        if (count($act) != count($exp)) {
            throw new Exception('Size mismatch');
        }
        $sum = 0.0;
        $count = count($act);
        for ($i = 0; $i < $count; $i++) {
            $sum += ($act[$i] - $exp[$i]) ** 2;
        }
        return sqrt($sum / $count);
    }
}
