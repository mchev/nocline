export type WallboardGridLayoutOptions = {
    minCellW?: number;
    minCellH?: number;
};

/** Pick a column count so tiles grow evenly and fill the pane (NOC wallboard). */
export function wallboardGridLayout(
    count: number,
    width: number,
    height: number,
    gapPx = 4,
    opts?: WallboardGridLayoutOptions,
): { cols: number; rows: number; cellMin: number } {
    const minCellW = opts?.minCellW ?? 52;
    const minCellH = opts?.minCellH ?? 36;
    if (count <= 0 || width <= 0 || height <= 0) {
        return { cols: 1, rows: 1, cellMin: 56 };
    }

    let best = {
        cols: 1,
        rows: count,
        score: 0,
        cellMin: 0,
    };

    for (let cols = 1; cols <= count; cols++) {
        const rows = Math.ceil(count / cols);
        const cellW = (width - gapPx * (cols - 1)) / cols;
        const cellH = (height - gapPx * (rows - 1)) / rows;
        const cellMin = Math.min(cellW, cellH);

        if (cellW < minCellW || cellH < minCellH) {
            continue;
        }

        const empty = cols * rows - count;
        const lastRow = count - (rows - 1) * cols;
        const rowBalance =
            rows > 1 ? -Math.abs(cols - lastRow) * 4 : 0;
        // Favor wider cells so hostnames stay readable (NOC tiles are read at a glance).
        const score =
            Math.min(cellW, cellH * 2) -
            empty * 3 +
            rowBalance;

        if (score > best.score) {
            best = { cols, rows, score, cellMin };
        }
    }

    return {
        cols: best.cols,
        rows: best.rows,
        cellMin: best.cellMin || 56,
    };
}

/** Short label for a site tile (host, no www). */
export function wallboardSiteLabel(site: {
    name: string;
    target?: string;
}): string {
    const fromTarget = hostFromUrl(site.target);

    if (fromTarget !== null) {
        if (
            site.name.length > fromTarget.length + 4 ||
            site.name.includes(fromTarget)
        ) {
            return fromTarget;
        }
    }

    return site.name;
}

/** Human URL line for wallboard (host + optional path, no scheme). */
export function wallboardSiteAddress(raw: string | undefined): string | null {
    const trimmed = raw?.trim();
    if (!trimmed) {
        return null;
    }

    try {
        const u = new URL(trimmed);
        const path =
            u.pathname !== '/' && u.pathname !== ''
                ? u.pathname.replace(/\/$/, '')
                : '';

        return `${u.host}${path}`;
    } catch {
        return trimmed;
    }
}

/** True when the check has a display title distinct from the monitored URL. */
export function wallboardSiteHasTitle(site: {
    name: string;
    target?: string;
}): boolean {
    const name = site.name.trim();
    if (name === '') {
        return false;
    }

    const address = wallboardSiteAddress(site.target);
    const host = hostFromUrl(site.target);

    if (address !== null) {
        const norm = name.toLowerCase();
        if (
            norm === address.toLowerCase() ||
            norm === site.target?.trim().toLowerCase() ||
            (host !== null && (norm === host || name === host))
        ) {
            return false;
        }
    }

    if (
        host !== null &&
        name.includes(host) &&
        name.length <= host.length + 6
    ) {
        return false;
    }

    return true;
}

/** Second line on wallboard tiles: custom title or URL only (never both). */
export function wallboardSiteCaption(site: {
    name: string;
    target?: string;
}): string {
    if (wallboardSiteHasTitle(site)) {
        return site.name.trim();
    }

    const address = wallboardSiteAddress(site.target);
    if (address !== null) {
        return address;
    }

    return site.name.trim() || '—';
}

function hostFromUrl(raw: string | undefined): string | null {
    if (!raw) {
        return null;
    }

    try {
        const host = new URL(raw).hostname.replace(/^www\./i, '').toLowerCase();

        return host || null;
    } catch {
        return null;
    }
}
