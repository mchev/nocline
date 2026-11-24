<?php

namespace App\Modules\Checks\Events;

use App\Modules\Checks\Models\Check;
use Illuminate\Foundation\Events\Dispatchable;

class CheckCreated
{
    use Dispatchable;

    public function __construct(public Check $check) {}
}
