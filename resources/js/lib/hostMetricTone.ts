export type MetricSparklineTone =
    | 'default'
    | 'neutral'
    | 'success'
    | 'warning'
    | 'danger'
    | 'primary';

/** Sparkline color from current usage (CPU/RAM high = bad; disk *free* high = good). */
export function hostMetricTone(
    v: number | null,
    invert = false,
): MetricSparklineTone {
    if (v === null) {
        return 'default';
    }

    if (invert) {
        if (v <= 10) {
            return 'danger';
        }

        if (v <= 25) {
            return 'warning';
        }

        return 'neutral';
    }

    if (v >= 90) {
        return 'danger';
    }

    if (v >= 75) {
        return 'warning';
    }

    return 'neutral';
}
