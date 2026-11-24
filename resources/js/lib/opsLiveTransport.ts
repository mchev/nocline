import type { LiveChannel, LiveEventHandler, LiveTransport } from '@inertiajs/core';

/**
 * Polling stand-in for Echo/Reverb so Inertia live props work on a plain
 * self-hosted install. When Reverb is configured later, swap to echo().
 */
export function opsPollingTransport(intervalMs = 10_000): LiveTransport {
    const listeners = new Map<string, Set<LiveEventHandler>>();
    let timer: ReturnType<typeof setInterval> | undefined;

    const keyOf = (channel: LiveChannel, event: string) =>
        `${channel.type}:${channel.name}:${event}`;

    const ensureTimer = () => {
        if (timer !== undefined || typeof window === 'undefined') {
            return;
        }

        timer = window.setInterval(() => {
            if (document.visibilityState === 'hidden') {
                return;
            }

            for (const handlers of listeners.values()) {
                for (const handler of handlers) {
                    handler({});
                }
            }
        }, intervalMs);
    };

    const clearTimerIfIdle = () => {
        if (listeners.size > 0 || timer === undefined) {
            return;
        }

        window.clearInterval(timer);
        timer = undefined;
    };

    return {
        subscribe(channel, event, handler) {
            if (channel.name !== 'ops') {
                return () => {};
            }

            const key = keyOf(channel, event);
            const set = listeners.get(key) ?? new Set<LiveEventHandler>();
            set.add(handler);
            listeners.set(key, set);
            ensureTimer();

            // Immediate refresh so the first paint catches up with the simulator.
            window.setTimeout(() => handler({}), 0);

            return () => {
                set.delete(handler);
                if (set.size === 0) {
                    listeners.delete(key);
                }
                clearTimerIfIdle();
            };
        },
    };
}
