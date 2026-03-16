<?php

namespace App\Helpers;

use App\Helpers\UtilsHelper;

class Config
{
    public static function get($key)
    {
        return UtilsHelper::settings($key);
    }
}
