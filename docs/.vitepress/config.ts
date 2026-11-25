import { defineConfig } from 'vitepress'

const repo = process.env.GITHUB_REPOSITORY?.split('/')[1] ?? 'phare'
const base = process.env.VITEPRESS_BASE ?? `/${repo}/`

export default defineConfig({
    title: 'phare.',
    description: 'Documentation Phare — centre d’opérations self-hosted',
    lang: 'fr-FR',
    base,
    cleanUrls: true,
    lastUpdated: true,
    markdown: {
        image: { lazyLoading: true },
    },
    head: [
        ['link', { rel: 'icon', href: `${base}brand/phare-symbole-signal.png` }],
    ],
    themeConfig: {
        logo: '/brand/phare-symbole-signal.png',
        siteTitle: 'phare.',
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
        sidebar: [
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
                    { text: 'Checks, incidents, alertes', link: '/user/checks-incidents-alertes' },
                    { text: 'Sondes et wallboard', link: '/user/sondes-wallboard' },
                    { text: 'Coffre et renouvellements', link: '/user/coffre-renouvellements' },
                    { text: 'Maintenance et statut', link: '/user/maintenance-statut' },
                    { text: 'Heartbeat et API', link: '/user/taches-planifiees-api' },
                    { text: 'Traductions', link: '/user/traductions' },
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
                    { text: 'API REST v1', link: '/api' },
                    { text: 'Guideline produit', link: '/guideline/SKILL' },
                    { text: 'Référence technique', link: '/guideline/reference' },
                    { text: 'Adapters de notification', link: '/contributing/notification-adapters' },
                ],
            },
        ],
        socialLinks: [{ icon: 'github', link: 'https://github.com/mchev/phare' }],
        footer: {
            message: 'Elastic License 2.0',
            copyright: 'Phare',
        },
        search: { provider: 'local' },
    },
})