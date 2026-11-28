<?php

return [
    /*
    | Installed version (the VERSION file, bumped at each release) and the GitHub repository
    | ("owner/name") whose latest release is compared against it once a day. Set
    | NOCLINE_UPDATE_CHECK=false to never contact GitHub.
    */
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',

    /*
    | Optional override for wallboard auto-reload (kiosk). When set, public JSON exposes this
    | as hub_revision and clients reload when it changes. Useful if deploy bumps assets without
    | touching VERSION or manifest.json on the server yet.
    */
    'deploy_revision' => env('NOCLINE_DEPLOY_REVISION'),

    'repository' => env('NOCLINE_REPOSITORY', 'mchev/nocline'),

    /*
    | GitHub repository that publishes nocline-agent binaries (releases). Independent from NOCLINE_REPOSITORY
    | (which may be a fork for hub update checks). Agent install always downloads from here.
    */
    'official_repository' => env('NOCLINE_OFFICIAL_REPOSITORY', 'mchev/nocline'),
    'update_check' => (bool) env('NOCLINE_UPDATE_CHECK', true),

    /*
    | Daily fetch of endoflife.date release cycles for server stack version warnings.
    | Web requests only read the cache (nocline:stack-eol:refresh).
    */
    'stack_eol_check' => (bool) env('NOCLINE_STACK_EOL_CHECK', true),
    'stack_eol_api' => env('NOCLINE_STACK_EOL_API', 'https://endoflife.date/api/v1'),

    /* API token middleware (see docs/api.md). Lower in phpunit.xml for fast throttle tests. */
    'api_token_rate_limit' => (int) env('NOCLINE_API_TOKEN_RATE_LIMIT', 120),
    'api_token_failure_limit' => (int) env('NOCLINE_API_TOKEN_FAILURE_LIMIT', 30),

    /* When false, CheckCreated does not dispatch ExecuteHubCheck (avoids ~10s HTTP timeouts in tests). */
    'eager_hub_check_on_create' => (bool) env('NOCLINE_EAGER_HUB_CHECK_ON_CREATE', true),
    'backup_dir' => env('NOCLINE_BACKUP_DIR', storage_path('app/backups')),

    /*
    | Days the activity log (audit_logs) is kept before nocline:audit:prune deletes older entries.
    */
    'audit_retention_days' => (int) env('NOCLINE_AUDIT_RETENTION_DAYS', 365),

    /*
    | Path of the .env file rewritten by the first-setup wizard.
    */
    'env_path' => env('NOCLINE_ENV_PATH', base_path('.env')),

    /*
    | Marker file created once the first setup is done (keep storage/ persistent).
    */
    'installed_marker' => env('NOCLINE_INSTALLED_MARKER', storage_path('app/nocline-installed')),

    /*
    | Skip the setup wizard (CI, tests, pre-provisioned deployments).
    */
    'force_installed' => (bool) env('NOCLINE_FORCE_INSTALLED', false),

    /*
    | Extra notification adapter classes (third-party packages). Adapters placed in
    | app/Modules/Alertes/Adapters are discovered automatically.
    */
    'notification_adapters' => [],

    /*
    | Optional override for agent download URL ({arch} = amd64|arm64). When unset, the installer uses
    | the official repository release matching VERSION (releases/download/vX.Y.Z/...).
    | agent_binary_dir is only for local dev after scripts/build-agent.sh (not used in production).
    */
    'agent_binary_dir' => env('NOCLINE_AGENT_BINARY_DIR', storage_path('app/agent')),
    'agent_download_url' => env('NOCLINE_AGENT_DOWNLOAD_URL'),

    /*
    | When true, outdated agents receive agent_update in POST /api/v1/ingest (SHA256-verified self-update).
    */
    'agent_auto_update' => (bool) env('NOCLINE_AGENT_AUTO_UPDATE', true),

    /*
    | Notify when the agent reports reboot_required (kernel update pending). Immediate alert on transition
    | plus optional daily digest (nocline:hosts:reboot-remind).
    */
    'reboot_required_alerts' => (bool) env('NOCLINE_REBOOT_REQUIRED_ALERTS', true),
    'reboot_required_daily_remind' => (bool) env('NOCLINE_REBOOT_REQUIRED_DAILY_REMIND', true),

    /*
    | Per-host metric alerts (CPU/RAM/disk %, optional load 1m). Evaluated on agent ingest rollups.
    */
    'metric_alerts' => [
        'enabled' => (bool) env('NOCLINE_METRIC_ALERTS', true),
        'cpu_pct' => (int) env('NOCLINE_METRIC_ALERT_CPU', 90),
        'mem_pct' => (int) env('NOCLINE_METRIC_ALERT_MEM', 90),
        'disk_pct' => (int) env('NOCLINE_METRIC_ALERT_DISK', 90),
        'load_1m' => env('NOCLINE_METRIC_ALERT_LOAD') !== null
            ? (float) env('NOCLINE_METRIC_ALERT_LOAD')
            : null,
        'sustain_minutes' => (int) env('NOCLINE_METRIC_ALERT_SUSTAIN', 5),
    ],

    /*
    | Smart detection of a website added in the wizard: refuse addresses on private networks
    | (loopback, LAN, link-local/metadata). Set NOCLINE_DETECT_ALLOW_PRIVATE=true to detect
    | sites hosted on your own network (*.test, 192.168.x.x...).
    */
    'detect_allow_private_networks' => (bool) env('NOCLINE_DETECT_ALLOW_PRIVATE', false),
];
