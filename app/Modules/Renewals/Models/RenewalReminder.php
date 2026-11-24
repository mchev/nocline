<?php

namespace App\Modules\Renewals\Models;

use Illuminate\Database\Eloquent\Model;

class RenewalReminder extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
