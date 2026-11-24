<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="google" content="notranslate">
    <title>{{ __('Wallboard') }} — {{ $name }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #1a1f1c;
            color: #f4f5f3;
            min-height: 100vh;
            padding: 1.5rem;
        }
        @media (min-width: 1024px) { body { padding: 2.5rem; } }
        .wrap { position: relative; max-width: 100%; }
        .aurora {
            position: fixed; inset: 0; pointer-events: none; opacity: 0.35;
            background: radial-gradient(ellipse 80% 50% at 20% 0%, rgba(210, 90, 50, 0.25), transparent 55%),
                radial-gradient(ellipse 60% 40% at 90% 20%, rgba(80, 160, 120, 0.15), transparent 50%);
        }
        .stale {
            background: #e8c96a; color: #111; padding: 0.75rem 1.5rem;
            border-radius: 6px; font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;
        }
        header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 2rem; }
        h1 { margin: 0; font-size: clamp(2rem, 4vw, 3rem); font-weight: 600; }
        .status {
            display: flex; align-items: center; gap: 1rem; padding: 0.75rem 2rem;
            border-radius: 6px; font-size: clamp(1.5rem, 3vw, 2.25rem); font-weight: 700;
        }
        .status-dot { width: 1rem; height: 1rem; border-radius: 50%; background: currentColor; }
        .status-good { background: #6fcf8f; color: #0a0a0a; }
        .status-bad { background: #e05252; color: #fff; }
        .status-wait { background: #3a423d; color: #a8afa9; }
        .grid-4 {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;
        }
        @media (min-width: 1024px) { .grid-4 { grid-template-columns: repeat(4, 1fr); gap: 1.5rem; } }
        .surface {
            background: #262b28; border: 1px solid #454d49; border-radius: 6px;
            padding: 1.5rem; text-align: center;
        }
        @media (min-width: 1024px) { .surface { padding: 2rem; } }
        .surface.danger { border-color: #c44; }
        .num { font-size: clamp(3rem, 8vw, 5rem); font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
        .num-ok { color: #6fcf8f; }
        .num-bad { color: #e05252; }
        .num-muted { color: rgba(168, 175, 169, 0.5); }
        .num-fg { color: rgba(244, 245, 243, 0.85); }
        .num-hi { color: #f0b060; }
        .label { margin-top: 0.5rem; font-size: 1.25rem; color: #a8afa9; }
        .section { margin-top: 2rem; }
        h2 { font-size: 1.75rem; font-weight: 600; margin: 0 0 0.75rem; }
        .incident-box {
            border: 1px solid #c44; background: rgba(224, 82, 82, 0.15);
            border-radius: 6px; padding: 1.5rem;
        }
        ul.plain { list-style: none; padding: 0; margin: 0; font-size: 1.25rem; }
        ul.plain li { margin-bottom: 0.5rem; }
        .fail-row {
            display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem;
            padding: 0.75rem 1.5rem; margin-bottom: 0.5rem;
        }
        .fail-name { color: #e05252; font-weight: 600; }
        .fail-meta { color: #a8afa9; }
        .hosts { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px) { .hosts { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .hosts { grid-template-columns: repeat(4, 1fr); } }
        .host-card { padding: 1.25rem; }
        .host-card.offline { border-color: #c44; opacity: 0.65; }
        .host-name { font-size: 1.25rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .metrics { margin-top: 0.5rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; font-size: 1.1rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .c-ok { color: #6fcf8f; }
        .c-warn { color: #e8c96a; }
        .c-bad { color: #e05252; }
        .c-muted { color: #a8afa9; }
        .site-list { list-style: none; padding: 0; margin: 0; }
        .site-row {
            display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1rem;
            padding: 0.75rem 1.25rem; margin-bottom: 0.5rem; text-align: left;
        }
        .site-row.site-down { border-color: #c44; background: rgba(224, 82, 82, 0.12); }
        .site-dot { width: 0.65rem; height: 0.65rem; border-radius: 50%; flex-shrink: 0; background: #6a756f; }
        .site-dot.up { background: #6fcf8f; }
        .site-dot.down { background: #e05252; }
        .site-name { font-size: 1.25rem; font-weight: 600; flex: 1 1 8rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .site-lat { font-variant-numeric: tabular-nums; font-weight: 600; font-size: 1.1rem; }
        .site-history { display: flex; gap: 2px; flex: 1 1 10rem; min-width: 6rem; max-width: 16rem; align-items: center; }
        .site-hist-dot { flex: 1; height: 0.45rem; border-radius: 2px; background: #3a423d; max-width: 0.5rem; }
        .site-hist-dot.up { background: #6fcf8f; }
        .site-hist-dot.down { background: #e05252; }
        .site-hist-dot.unknown { background: #6a756f; }
        .site-hist-dot.maintenance { background: #e8c96a; }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <div class="aurora" aria-hidden="true"></div>
    <main class="wrap">
        <div id="stale-banner" class="stale hidden" role="alert"></div>

        <header>
            <h1 id="board-name">{{ $name }}</h1>
            <div id="status-pill" class="status status-wait" role="status">
                <span class="status-dot" aria-hidden="true"></span>
                <span id="status-text">…</span>
            </div>
        </header>

        <div id="content" class="hidden">
            <section class="grid-4" aria-label="{{ __('Summary') }}">
                <div class="surface">
                    <div class="num num-ok" id="sum-up">0</div>
                    <div class="label">{{ __('checks OK') }}</div>
                </div>
                <div class="surface" id="surface-down">
                    <div class="num num-muted" id="sum-down">0</div>
                    <div class="label">{{ __('down') }}</div>
                </div>
                <div class="surface">
                    <div class="num num-fg" id="sum-unknown">0</div>
                    <div class="label">{{ __('unknown') }}</div>
                    <div class="label c-warn hidden" id="sum-maint"></div>
                </div>
                <div class="surface">
                    <div class="num num-hi" id="sum-hosts">0/0</div>
                    <div class="label">{{ __('hosts online') }}</div>
                </div>
            </section>

            <section id="incidents" class="section incident-box hidden">
                <h2>{{ __('Unresolved incidents') }}</h2>
                <ul class="plain" id="incident-list"></ul>
            </section>

            <section id="sites" class="section hidden">
                <h2>{{ __('Websites') }}</h2>
                <ul class="site-list" id="site-list"></ul>
            </section>

            <section id="failures" class="section hidden">
                <h2>{{ __('Ongoing failures') }}</h2>
                <ul class="plain" id="fail-list"></ul>
            </section>

            <section id="servers" class="section hidden">
                <h2>{{ __('Servers') }}</h2>
                <div class="hosts" id="host-grid"></div>
            </section>
        </div>
    </main>

    <script>
        (function () {
            var STALE_MS = 90000;
            var REFRESH_MS = 15000;
            var apiUrl = @json($apiUrl);
            var lastSuccess = null;
            var data = null;
            var strings = {
                allGood: @json(__('ALL GOOD')),
                attention: @json(__('ATTENTION')),
                ack: @json(__('ack')),
                inMaintenance: @json(__('in maintenance')),
                stale: @json(__('Stale data — last update :ago ago'))
            };

            function since(iso) {
                if (!iso) return '';
                var m = Math.max(0, Math.floor((Date.now() - Date.parse(iso)) / 60000));
                return m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min';
            }

            function level(v, warn, crit) {
                if (v === null || v === undefined) return 'c-muted';
                if (v >= crit) return 'c-bad';
                if (v >= warn) return 'c-warn';
                return 'c-ok';
            }

            function render() {
                var pill = document.getElementById('status-pill');
                var text = document.getElementById('status-text');
                if (!data) {
                    pill.className = 'status status-wait';
                    text.textContent = '…';
                    return;
                }
                var allGood = data.summary.down === 0 && data.active_incidents.length === 0;
                pill.className = 'status ' + (allGood ? 'status-good' : 'status-bad');
                text.textContent = allGood ? strings.allGood : strings.attention;

                document.getElementById('content').classList.remove('hidden');
                var s = data.summary;
                document.getElementById('sum-up').textContent = s.up;
                var downEl = document.getElementById('sum-down');
                var downSurf = document.getElementById('surface-down');
                downEl.textContent = s.down;
                downEl.className = 'num ' + (s.down ? 'num-bad' : 'num-muted');
                if (s.down) downSurf.classList.add('danger'); else downSurf.classList.remove('danger');
                document.getElementById('sum-unknown').textContent = s.unknown;
                document.getElementById('sum-hosts').textContent = s.hosts_online + '/' + s.hosts_total;
                var maint = document.getElementById('sum-maint');
                if (s.maintenance) {
                    maint.textContent = s.maintenance + ' ' + strings.inMaintenance;
                    maint.classList.remove('hidden');
                } else {
                    maint.classList.add('hidden');
                }

                var incSec = document.getElementById('incidents');
                var incList = document.getElementById('incident-list');
                incList.innerHTML = '';
                if (data.active_incidents.length) {
                    incSec.classList.remove('hidden');
                    data.active_incidents.forEach(function (i) {
                        var li = document.createElement('li');
                        li.textContent = i.title + ' — ' + since(i.since) + (i.acknowledged ? ' (' + strings.ack + ')' : '');
                        incList.appendChild(li);
                    });
                } else {
                    incSec.classList.add('hidden');
                }

                var siteSec = document.getElementById('sites');
                var siteList = document.getElementById('site-list');
                siteList.innerHTML = '';
                var sites = (data.sites || []).slice(0, 24);
                if (sites.length) {
                    siteSec.classList.remove('hidden');
                    sites.forEach(function (s) {
                        var li = document.createElement('li');
                        var down = s.enabled && !s.in_maintenance && s.status === 'down';
                        li.className = 'surface site-row' + (down ? ' site-down' : '');
                        var dot = document.createElement('span');
                        dot.className = 'site-dot ' + (s.in_maintenance ? 'unknown' : (s.status || 'unknown'));
                        var name = document.createElement('span');
                        name.className = 'site-name';
                        name.textContent = s.name;
                        var hist = document.createElement('div');
                        hist.className = 'site-history';
                        var buckets = s.history && s.history.length ? s.history : [];
                        buckets.forEach(function (st) {
                            var d = document.createElement('span');
                            d.className = 'site-hist-dot ' + st;
                            hist.appendChild(d);
                        });
                        var lat = document.createElement('span');
                        lat.className = 'site-lat c-muted';
                        if (s.latency_ms != null) {
                            lat.textContent = s.latency_ms + ' ms';
                            lat.className = 'site-lat ' + (s.latency_ms >= 2000 ? 'c-bad' : s.latency_ms >= 800 ? 'c-warn' : 'c-ok');
                        } else {
                            lat.textContent = '—';
                        }
                        li.appendChild(dot);
                        li.appendChild(name);
                        if (buckets.length) li.appendChild(hist);
                        li.appendChild(lat);
                        siteList.appendChild(li);
                    });
                } else {
                    siteSec.classList.add('hidden');
                }

                var failSec = document.getElementById('failures');
                var failList = document.getElementById('fail-list');
                failList.innerHTML = '';
                if (data.checks_failing.length) {
                    failSec.classList.remove('hidden');
                    data.checks_failing.forEach(function (c) {
                        var li = document.createElement('li');
                        li.className = 'surface fail-row';
                        li.innerHTML = '<span class="fail-name"></span><span class="fail-meta"></span>';
                        li.querySelector('.fail-name').textContent = c.name;
                        li.querySelector('.fail-meta').textContent = (c.error || '') + ' · ' + since(c.since);
                        failList.appendChild(li);
                    });
                } else {
                    failSec.classList.add('hidden');
                }

                var hostGrid = document.getElementById('host-grid');
                var servSec = document.getElementById('servers');
                hostGrid.innerHTML = '';
                if (data.hosts.length) {
                    servSec.classList.remove('hidden');
                    data.hosts.forEach(function (h) {
                        var card = document.createElement('div');
                        card.className = 'surface host-card' + (h.online ? '' : ' offline');
                        card.innerHTML =
                            '<div class="host-name"></div>' +
                            '<div class="metrics">' +
                            '<span class="m-cpu"></span><span class="m-mem"></span><span class="m-disk"></span>' +
                            '</div>';
                        card.querySelector('.host-name').textContent = h.name;
                        var cpu = card.querySelector('.m-cpu');
                        var mem = card.querySelector('.m-mem');
                        var disk = card.querySelector('.m-disk');
                        cpu.className = level(h.cpu_avg_5m, 70, 90);
                        mem.className = level(h.mem_pct, 80, 92);
                        disk.className = level(h.disk_pct, 80, 92);
                        cpu.textContent = 'CPU ' + (h.cpu_avg_5m != null ? h.cpu_avg_5m : '–') + '%';
                        mem.textContent = 'RAM ' + (h.mem_pct != null ? h.mem_pct : '–') + '%';
                        disk.textContent = 'DSK ' + (h.disk_pct != null ? h.disk_pct : '–') + '%';
                        hostGrid.appendChild(card);
                    });
                } else {
                    servSec.classList.add('hidden');
                }
            }

            function updateStale() {
                var banner = document.getElementById('stale-banner');
                if (lastSuccess === null || Date.now() - lastSuccess > STALE_MS) {
                    var ago = lastSuccess ? since(new Date(lastSuccess).toISOString()) : '—';
                    banner.textContent = strings.stale.replace(':ago', ago);
                    banner.classList.remove('hidden');
                } else {
                    banner.classList.add('hidden');
                }
            }

            function refresh() {
                fetch(apiUrl, { headers: { Accept: 'application/json' } })
                    .then(function (res) {
                        if (!res.ok) throw new Error(String(res.status));
                        return res.json();
                    })
                    .then(function (json) {
                        data = json;
                        if (json.name) {
                            document.getElementById('board-name').textContent = json.name;
                        }
                        lastSuccess = Date.now();
                        render();
                        updateStale();
                    })
                    .catch(function () { updateStale(); });
            }

            refresh();
            setInterval(refresh, REFRESH_MS);
            setInterval(updateStale, 1000);
        })();
    </script>
</body>
</html>