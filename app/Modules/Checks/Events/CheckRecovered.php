<?php

namespace App\Modules\Checks\Events;

use App\Modules\Checks\Models\Check;
use Illuminate\Foundation\Events\Dispatchable;

class CheckRecovered
{
    use Dispatchable;

    public function __construct(public Check $check) {}
}
