/** Pick a column count so tiles grow evenly and fill the pane (NOC wallboard). */
export function wallboardGridLayout(
    count: number,
    width: number,
    height: number,
    gapPx = 4,
): { cols: number; rows: number; cellMin: number } {
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

        if (cellW < 52 || cellH < 36) {
            continue;
        }

        const empty = cols * rows - count;
        // Favor wider cells so hostnames stay readable (NOC tiles are read at a glance).
        const score = Math.min(cellW, cellH * 2) - empty * 2;

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

function hostFromUrl(raw: string | undefined): string | null {
    if (!raw) {
        return null;
    }

    try {
        const host = new URL(raw).hostname
            .replace(/^www\./i, '')
            .toLowerCase();

        return host || null;
    } catch {
        return null;
    }
}