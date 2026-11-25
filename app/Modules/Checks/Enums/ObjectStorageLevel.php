<?php

namespace App\Modules\Checks\Enums;

enum ObjectStorageLevel: string
{
    /** TCP check on the provider S3 endpoint (port 443). */
    case Platform = 'platform';

    /** HTTP GET on a public object URL (bucket + health file). */
    case Bucket = 'bucket';

    /** Creates a heartbeat check for backup/cron jobs (no S3 probe). */
    case Backup = 'backup';
}
