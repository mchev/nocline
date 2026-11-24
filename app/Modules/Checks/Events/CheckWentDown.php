<?php

namespace App\Modules\Checks\Events;

use App\Modules\Checks\Models\Check;
use Illuminate\Foundation\Events\Dispatchable;

class CheckWentDown
{
    use Dispatchable;

    public function __construct(public Check $check, public ?string $error = null) {}
}
