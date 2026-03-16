<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;

class UtilsController
{
    public function health()
    {
        ResponseHelper::ok([]);
    }
}
