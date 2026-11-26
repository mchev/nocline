import { defineConfig, type DefaultTheme } from 'vitepress';

const repo = process.env.GITHUB_REPOSITORY?.split('/')[1] ?? 'nocline';
const base = process.env.VITEPRESS_BASE ?? `/${repo}/`;

const enApiSidebar: DefaultTheme.SidebarItem = {
    text: 'REST API v1',
    link: '/api',
};

const rootSidebar: DefaultTheme.SidebarItem[] = [
    {
        text: 'Get started',
        collapsed: false,
        items: [
            { text: 'Installation', link: '/user/installation' },
            { text: 'Your first hour', link: '/user/getting-started' },
            { text: 'Docker', link: '/deploy/docker' },
            { text: 'VPS / Forge', link: '/deploy/hub' },
            { text: 'Migrate from Uptime Kuma', link: '/migration-kuma' },
        ],
    },
    {
        text: 'Day to day',
        collapsed: false,
        items: [
            { text: 'Add monitors', link: '/user/surveiller' },
            { text: 'Servers and agent', link: '/user/servers-and-agent' },
            {
                text: 'Checks, incidents, alerts',
                link: '/user/checks-incidents-alerts',
            },
            { text: 'Probes and wallboard', link: '/user/sondes-wallboard' },
            {
                text: 'Scheduled tasks and API',
                link: '/user/scheduled-tasks-and-api',
            },
            { text: 'Vault and renewals', link: '/user/vault-and-renewals' },
            {
                text: 'Maintenance and status pages',
                link: '/user/maintenance-and-status',
            },
            { text: 'Accounts and roles', link: '/user/accounts-and-roles' },
            { text: 'Translations', link: '/user/translations' },
        ],
    },
    {
        text: 'Project',
        items: [
            { text: 'Overview (screenshots)', link: '/apercu' },
            { text: 'Roadmap', link: '/roadmap' },
            { text: 'Releases', link: '/releases' },
            { text: 'Security', link: '/security' },
            { text: 'Privacy', link: '/privacy' },
            enApiSidebar,
            {
                text: 'Notification adapters',
                link: '/contributing/notification-adapters',
            },
        ],
    },
];

const frSidebar: DefaultTheme.SidebarItem[] = [
    {
        text: 'Démarrer',
        collapsed: false,
        items: [
            { text: 'Installation', link: '/fr/user/installation' },
            { text: 'Première heure', link: '/fr/user/getting-started' },
            { text: 'Docker', link: '/deploy/docker' },
            { text: 'VPS / Forge', link: '/fr/deploy/hub' },
            { text: 'Migration Kuma', link: '/fr/migration-kuma' },
        ],
    },
    {
        text: 'Au quotidien',
        collapsed: false,
        items: [
            { text: 'Surveiller', link: '/fr/user/surveiller' },
            { text: 'Serveurs et agent', link: '/fr/user/hosts-agent' },
            {
                text: 'Checks, incidents, alertes',
                link: '/fr/user/checks-incidents-alertes',
            },
            { text: 'Sondes et wallboard', link: '/fr/user/sondes-wallboard' },
            {
                text: 'Tâches planifiées et API',
                link: '/fr/user/taches-planifiees-api',
            },
            {
                text: 'Coffre et renouvellements',
                link: '/fr/user/coffre-renouvellements',
            },
            {
                text: 'Maintenance et statut',
                link: '/fr/user/maintenance-statut',
            },
            { text: 'Comptes et rôles', link: '/fr/user/utilisateurs' },
            { text: 'Traductions', link: '/fr/user/traductions' },
        ],
    },
    {
        text: 'Projet',
        items: [
            { text: 'Aperçu', link: '/fr/apercu' },
            { text: 'Roadmap', link: '/fr/roadmap' },
            { text: 'Releases', link: '/fr/releases' },
            { text: 'Sécurité', link: '/fr/security' },
            { text: 'Confidentialité', link: '/fr/privacy' },
            enApiSidebar,
            {
                text: 'Adapters de notification',
                link: '/contributing/notification-adapters',
            },
        ],
    },
];

const sharedTheme = {
    logo: '/brand/nocline-symbole-signal.png',
    siteTitle: 'NOCLINE',
    socialLinks: [
        { icon: 'github' as const, link: 'https://github.com/mchev/nocline' },
    ],
    search: { provider: 'local' as const },
    footer: {
        message: 'Elastic License 2.0',
        copyright: 'Nocline',
    },
};

export default defineConfig({
    base,
    srcExclude: ['**/guideline/**', '**/README.md'],
    cleanUrls: true,
    lastUpdated: true,
    markdown: {
        image: { lazyLoading: true },
    },
    head: [
        [
            'link',
            { rel: 'icon', href: `${base}brand/nocline-symbole-signal.png` },
        ],
    ],
    locales: {
        root: {
            label: 'English',
            lang: 'en-US',
            title: 'NOCLINE',
            description:
                'Nocline documentation — self-hosted operations center',
            themeConfig: {
                ...sharedTheme,
                nav: [
                    { text: 'Install', link: '/user/installation' },
                    { text: 'Guides', link: '/user/getting-started' },
                    { text: 'Screenshots', link: '/apercu' },
                    { text: 'Docker', link: '/deploy/docker' },
                    { text: 'API', link: '/api' },
                    {
                        text: 'GitHub',
                        link: 'https://github.com/mchev/nocline',
                    },
                ],
                sidebar: rootSidebar,
                outline: { label: 'On this page' },
                docFooter: {
                    prev: 'Previous',
                    next: 'Next',
                },
                lastUpdated: {
                    text: 'Last updated',
                    formatOptions: {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    },
                },
            },
        },
        fr: {
            label: 'Français',
            lang: 'fr-FR',
            link: '/fr/',
            title: 'NOCLINE',
            description:
                'Documentation Nocline — centre d’opérations self-hosted',
            themeConfig: {
                ...sharedTheme,
                nav: [
                    { text: 'Installation', link: '/fr/user/installation' },
                    { text: 'Guides', link: '/fr/user/getting-started' },
                    { text: 'Aperçu', link: '/fr/apercu' },
                    { text: 'Docker', link: '/deploy/docker' },
                    { text: 'API', link: '/api' },
                    {
                        text: 'GitHub',
                        link: 'https://github.com/mchev/nocline',
                    },
                ],
                sidebar: frSidebar,
            },
        },
    },
});
