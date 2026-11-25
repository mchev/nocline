# Documentation (sources)

- **Site public** : [https://mchev.github.io/phare/](https://mchev.github.io/phare/) (VitePress + GitHub Pages, workflow `docs.yml`).
- **Local** : `npm run docs:dev` puis ouvrir `http://localhost:5173/phare/` dans le navigateur
- **Preview build** : `npm run docs:build` puis `npm run docs:preview`

## Contribuer à la doc

1. Contenu : Markdown dans `docs/` (**français**, racine) et `docs/en/` (**anglais**). Voir [traductions.md](user/traductions.md#documentation-site-vitepress).
2. Navigation : mettre à jour `docs/.vitepress/config.ts` (`locales.root` et `locales.en`) pour une **nouvelle section** ou une page hors sidebar.
3. Après une **feature produit** : guide utilisateur + API / déploiement si le comportement change (voir skill Phare).
4. Avant une PR qui touche la structure : `npm run docs:build` doit réussir.

Le `base` VitePress suit le nom du repo GitHub (`/phare/`). Fork : définir `VITEPRESS_BASE=/mon-repo/` en local si besoin.
