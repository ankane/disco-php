<?php

namespace Disco;

class Library
{
    public static function check($event)
    {
        return \Libmf\Vendor::check($event);
    }
}
