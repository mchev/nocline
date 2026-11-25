export type MetricSparklineTone =
    | 'default'
    | 'neutral'
    | 'success'
    | 'warning'
    | 'danger'
    | 'primary';

export type MetricToneThresholds = {
    warning: number;
    danger: number;
};

/** Sparkline color from current usage (high = bad; disk free% uses invert). */
export function hostMetricTone(
    v: number | null,
    invert = false,
    custom?: MetricToneThresholds,
): MetricSparklineTone {
    if (v === null) {
        return 'default';
    }

    if (invert) {
        const d = custom?.danger ?? 10;
        const w = custom?.warning ?? 25;

        if (v <= d) {
            return 'danger';
        }

        if (v <= w) {
            return 'warning';
        }

        return 'neutral';
    }

    const d = custom?.danger ?? 90;
    const w = custom?.warning ?? 75;

    if (v >= d) {
        return 'danger';
    }

    if (v >= w) {
        return 'warning';
    }

    return 'neutral';
}

export function metricThresholdsFromAlert(pct: number): MetricToneThresholds {
    return {
        warning: Math.max(1, pct - 10),
        danger: pct,
    };
}
