<?php

return [
    /*
    | Installed version (the VERSION file, bumped at each release) and the GitHub repository
    | ("owner/name") whose latest release is compared against it once a day. Set
    | PHARE_UPDATE_CHECK=false to never contact GitHub.
    */
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',

    /*
    | Optional override for wallboard auto-reload (kiosk). When set, public JSON exposes this
    | as hub_revision and clients reload when it changes. Useful if deploy bumps assets without
    | touching VERSION or manifest.json on the server yet.
    */
    'deploy_revision' => env('PHARE_DEPLOY_REVISION'),

    'repository' => env('PHARE_REPOSITORY', 'mchev/phare'),

    /*
    | GitHub repository that publishes phare-agent binaries (releases). Independent from PHARE_REPOSITORY
    | (which may be a fork for hub update checks). Agent install always downloads from here.
    */
    'official_repository' => env('PHARE_OFFICIAL_REPOSITORY', 'mchev/phare'),
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
    | Optional override for agent download URL ({arch} = amd64|arm64). When unset, the installer uses
    | the official repository release matching VERSION (releases/download/vX.Y.Z/...).
    | agent_binary_dir is only for local dev after scripts/build-agent.sh (not used in production).
    */
    'agent_binary_dir' => env('PHARE_AGENT_BINARY_DIR', storage_path('app/agent')),
    'agent_download_url' => env('PHARE_AGENT_DOWNLOAD_URL'),

    /*
    | When true, outdated agents receive agent_update in POST /api/v1/ingest (SHA256-verified self-update).
    */
    'agent_auto_update' => (bool) env('PHARE_AGENT_AUTO_UPDATE', true),

    /*
    | Smart detection of a website added in the wizard: refuse addresses on private networks
    | (loopback, LAN, link-local/metadata). Set PHARE_DETECT_ALLOW_PRIVATE=true to detect
    | sites hosted on your own network (*.test, 192.168.x.x...).
    */
    'detect_allow_private_networks' => (bool) env('PHARE_DETECT_ALLOW_PRIVATE', false),
];
