const GIB = 1024 ** 3;

/** Human disk size for wallboard (e.g. 5,2 Go). */
export function formatGiB(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined || Number.isNaN(bytes)) {
        return '—';
    }

    const gib = bytes / GIB;

    return `${new Intl.NumberFormat(undefined, {
        minimumFractionDigits: gib < 10 ? 1 : 0,
        maximumFractionDigits: 1,
    }).format(gib)} Go`;
}