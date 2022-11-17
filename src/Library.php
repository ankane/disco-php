<?php

namespace Disco;

class Library
{
    public static function check($event = null)
    {
        return \Libmf\Vendor::check($event);
    }
}
