<?php

namespace App\Modules\Groups\Enums;

/**
 * How the content of a group is displayed on the monitors page.
 */
enum GroupLayout: string
{
    case List = 'list';
    case Grid = 'grid';
    case Compact = 'compact';
}
