# Documentation sources

- **Published**: [mchev.github.io/nocline](https://mchev.github.io/nocline/user/installation) (VitePress + GitHub Pages, workflow `docs.yml`).
- **Local**: `npm run docs:dev` → `http://localhost:5173/nocline/`
- **Build**: `npm run docs:build`

## Layout

| Locale            | Path       | Entry                   |
| ----------------- | ---------- | ----------------------- |
| English (default) | `docs/`    | `/user/installation`    |
| Français          | `docs/fr/` | `/fr/user/installation` |

Update `docs/.vitepress/config.ts` when adding sidebar pages. Run `npm run docs:build` before merging.

`VITEPRESS_BASE=/my-fork/` for local fork previews.
