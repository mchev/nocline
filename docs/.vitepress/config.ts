import { defineConfig, type DefaultTheme } from 'vitepress';

const repo = process.env.GITHUB_REPOSITORY?.split('/')[1] ?? 'phare';
const base = process.env.VITEPRESS_BASE ?? `/${repo}/`;

/** Shared path to operator docs that stay locale-neutral or are already in English. */
const enApiSidebar: DefaultTheme.SidebarItem = {
    text: 'REST API v1',
    link: '/api',
};

const frSidebar: DefaultTheme.SidebarItem[] = [
    {
        text: 'Introduction',
        items: [
            { text: 'Aperçu', link: '/apercu' },
            { text: 'Roadmap', link: '/roadmap' },
            { text: 'Migration Kuma', link: '/migration-kuma' },
        ],
    },
    {
        text: 'Guides utilisateur',
        collapsed: false,
        items: [
            { text: 'Sommaire', link: '/user/' },
            { text: 'Installation', link: '/user/installation' },
            { text: 'Surveiller', link: '/user/surveiller' },
            { text: 'Comptes et rôles', link: '/user/utilisateurs' },
            { text: 'Serveurs et agent', link: '/user/hosts-agent' },
            {
                text: 'Checks, incidents, alertes',
                link: '/user/checks-incidents-alertes',
            },
            { text: 'Sondes et wallboard', link: '/user/sondes-wallboard' },
            {
                text: 'Coffre et renouvellements',
                link: '/user/coffre-renouvellements',
            },
            {
                text: 'Maintenance et statut',
                link: '/user/maintenance-statut',
            },
            {
                text: 'Heartbeat et API',
                link: '/user/taches-planifiees-api',
            },
            { text: 'Traductions (app et doc)', link: '/user/traductions' },
        ],
    },
    {
        text: 'Déploiement et ops',
        items: [
            { text: 'Hub (VPS / Forge)', link: '/deploy/hub' },
            { text: 'Releases et mises à jour', link: '/releases' },
            { text: 'Sécurité', link: '/security' },
            { text: 'Confidentialité (RGPD)', link: '/privacy' },
        ],
    },
    {
        text: 'Référence',
        items: [
            enApiSidebar,
            { text: 'Guideline produit', link: '/guideline/SKILL' },
            { text: 'Référence technique', link: '/guideline/reference' },
            {
                text: 'Adapters de notification',
                link: '/contributing/notification-adapters',
            },
        ],
    },
];

const enSidebar: DefaultTheme.SidebarItem[] = [
    {
        text: 'Introduction',
        items: [
            { text: 'Overview', link: '/apercu' },
            { text: 'Roadmap', link: '/roadmap' },
        ],
    },
    {
        text: 'User guides',
        collapsed: false,
        items: [
            { text: 'Index', link: '/user/' },
            { text: 'Installation', link: '/user/installation' },
            {
                text: 'Monitoring (sites, servers…)',
                link: '/user/surveiller',
            },
            { text: 'Probes and wallboard', link: '/user/sondes-wallboard' },
            { text: 'Translations (app and docs)', link: '/user/translations' },
        ],
    },
    {
        text: 'Deploy and ops',
        items: [
            { text: 'Hub (VPS / Forge)', link: '/deploy/hub' },
            { text: 'Releases and upgrades', link: '/releases' },
            { text: 'Security', link: '/security' },
        ],
    },
    {
        text: 'Reference',
        items: [
            enApiSidebar,
            { text: 'Technical reference', link: '/guideline/reference' },
            {
                text: 'Notification adapters',
                link: '/contributing/notification-adapters',
            },
        ],
    },
];

const sharedTheme = {
    logo: '/brand/phare-symbole-signal.png',
    siteTitle: 'phare.',
    socialLinks: [
        { icon: 'github' as const, link: 'https://github.com/mchev/phare' },
    ],
    search: { provider: 'local' as const },
    footer: {
        message: 'Elastic License 2.0',
        copyright: 'Phare',
    },
};

export default defineConfig({
    base,
    cleanUrls: true,
    lastUpdated: true,
    markdown: {
        image: { lazyLoading: true },
    },
    head: [
        [
            'link',
            { rel: 'icon', href: `${base}brand/phare-symbole-signal.png` },
        ],
    ],
    locales: {
        root: {
            label: 'Français',
            lang: 'fr-FR',
            title: 'phare.',
            description:
                'Documentation Phare — centre d’opérations self-hosted',
            themeConfig: {
                ...sharedTheme,
                nav: [
                    { text: 'Accueil', link: '/' },
                    { text: 'Guides', link: '/user/' },
                    { text: 'Déploiement', link: '/deploy/hub' },
                    { text: 'API', link: '/api' },
                    {
                        text: 'GitHub',
                        link: 'https://github.com/mchev/phare',
                    },
                ],
                sidebar: frSidebar,
            },
        },
        en: {
            label: 'English',
            lang: 'en-US',
            link: '/en/',
            title: 'phare.',
            description: 'Phare documentation — self-hosted operations center',
            themeConfig: {
                ...sharedTheme,
                nav: [
                    { text: 'Home', link: '/' },
                    { text: 'Guides', link: '/user/' },
                    { text: 'Deploy', link: '/deploy/hub' },
                    { text: 'API', link: '/api' },
                    {
                        text: 'GitHub',
                        link: 'https://github.com/mchev/phare',
                    },
                ],
                sidebar: enSidebar,
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
    },
});
