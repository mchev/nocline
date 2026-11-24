<?php

return [
    /*
    | Installed version (the VERSION file, bumped at each release) and the GitHub repository
    | ("owner/name") whose latest release is compared against it once a day. Set
    | PHARE_UPDATE_CHECK=false to never contact GitHub.
    */
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',
    'repository' => env('PHARE_REPOSITORY', 'mchev/phare'),
    'update_check' => (bool) env('PHARE_UPDATE_CHECK', true),
    'backup_dir' => env('PHARE_BACKUP_DIR', storage_path('app/backups')),

    /*
    | Days the activity log (audit_logs) is kept before phare:audit:prune deletes older entries.
    */
    'audit_retention_days' => (int) env('PHARE_AUDIT_RETENTION_DAYS', 365),

    /*
    | Path of the .env file rewritten by the first-setup wizard.
    */
    'env_path' => env('PHARE_ENV_PATH', base_path('.env')),

    /*
    | Marker file created once the first setup is done (keep storage/ persistent).
    */
    'installed_marker' => env('PHARE_INSTALLED_MARKER', storage_path('app/phare-installed')),

    /*
    | Skip the setup wizard (CI, tests, pre-provisioned deployments).
    */
    'force_installed' => (bool) env('PHARE_FORCE_INSTALLED', false),

    /*
    | Extra notification adapter classes (third-party packages). Adapters placed in
    | app/Modules/Alertes/Adapters are discovered automatically.
    */
    'notification_adapters' => [],

    /*
    | Where the hub serves the phare-agent binaries from (built by scripts/build-agent.sh), and an
    | optional fallback download URL; {arch} is replaced by amd64|arm64.
    */
    'agent_binary_dir' => env('PHARE_AGENT_BINARY_DIR', storage_path('app/agent')),
    'agent_download_url' => env('PHARE_AGENT_DOWNLOAD_URL', 'https://github.com/'.env('PHARE_REPOSITORY', 'mchev/phare').'/releases/latest/download/phare-agent-linux-{arch}'),

    /*
    | Smart detection of a website added in the wizard: refuse addresses on private networks
    | (loopback, LAN, link-local/metadata). Set PHARE_DETECT_ALLOW_PRIVATE=true to detect
    | sites hosted on your own network (*.test, 192.168.x.x...).
    */
    'detect_allow_private_networks' => (bool) env('PHARE_DETECT_ALLOW_PRIVATE', false),
];
