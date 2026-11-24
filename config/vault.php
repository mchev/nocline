<?php

return [
    /*
    | 32 random bytes, base64-encoded. MUST be distinct from APP_KEY and kept out of git.
    | Losing it makes every vault secret permanently unreadable (back it up separately).
    | Generate: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    */
    'master_key' => env('VAULT_MASTER_KEY'),
];
