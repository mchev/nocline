<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="kiosk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="google" content="notranslate">
    <meta name="phare-hub-revision" content="{{ $hubRevision }}">
    <title>{{ __('Wallboard') }} — {{ $name }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            overflow: hidden;
        }
        html.kiosk {
            font-size: clamp(18px, 2.5vmin, 30px);
            cursor: none;
        }
        html.kiosk * { cursor: none; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #1a1f1c;
            color: #f4f5f3;
            display: flex;
            flex-direction: column;
            padding: clamp(0.5rem, 1.5vmin, 1rem);
        }
        .aurora {
            position: fixed; inset: 0; pointer-events: none; opacity: 0.3; z-index: 0;
            background: radial-gradient(ellipse 80% 50% at 20% 0%, rgba(210, 90, 50, 0.22), transparent 55%),
                radial-gradient(ellipse 60% 40% at 90% 20%, rgba(80, 160, 120, 0.12), transparent 50%);
        }
        .wrap {
            position: relative;
            z-index: 1;
            flex: 1;
            min-height: 0;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .stale {
            flex-shrink: 0;
            background: #e8c96a;
            color: #111;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            font-size: 0.95em;
            font-weight: 600;
        }
        header {
            flex-shrink: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        h1 {
            margin: 0;
            font-size: clamp(1.25rem, 5vmin, 2.5rem);
            font-weight: 600;
            line-height: 1.1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 55vw;
        }
        .status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.9rem;
            border-radius: 6px;
            font-size: clamp(1rem, 4vmin, 2.25rem);
            font-weight: 700;
            flex-shrink: 0;
        }
        .status-dot { width: 0.75em; height: 0.75em; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .status-good { background: #6fcf8f; color: #0a0a0a; }
        .status-bad { background: #e05252; color: #fff; }
        .status-wait { background: #3a423d; color: #a8afa9; }
        #content {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            overflow: hidden;
        }
        .incident-bar {
            flex-shrink: 0;
            border: 1px solid #c44;
            background: rgba(224, 82, 82, 0.18);
            border-radius: 6px;
            padding: 0.35rem 0.65rem;
            font-size: clamp(0.85rem, 2.8vmin, 1.35rem);
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .board-columns {
            flex: 1;
            min-height: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        @media (max-width: 640px) {
            .board-columns { grid-template-columns: 1fr; }
        }
        .board-columns--single {
            grid-template-columns: 1fr;
        }
        .board-pane {
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: rgba(38, 43, 40, 0.85);
            border: 1px solid #454d49;
            border-radius: 6px;
            overflow: hidden;
        }
        .pane-title {
            flex-shrink: 0;
            margin: 0;
            padding: 0.35rem 0.6rem;
            font-size: clamp(0.75rem, 2.2vmin, 1.1rem);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #a8afa9;
            border-bottom: 1px solid #454d49;
        }
        .pane-list {
            list-style: none;
            margin: 0;
            padding: 0.25rem;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .surface {
            background: #262b28;
            border: 1px solid #454d49;
            border-radius: 4px;
        }
        .row {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.15rem 0.5rem;
            min-height: 0;
            flex: 1 1 0;
            overflow: hidden;
            text-align: left;
        }
        .row.row-down { border-color: #c44; background: rgba(224, 82, 82, 0.14); }
        .row.host-offline { border-color: #c44; background: rgba(224, 82, 82, 0.1); opacity: 0.92; }
        .dot { width: 0.55em; height: 0.55em; border-radius: 50%; flex-shrink: 0; background: #6a756f; }
        .dot.up { background: #6fcf8f; }
        .dot.down { background: #e05252; }
        .dot.unknown { background: #6a756f; }
        .row-name {
            font-weight: 600;
            font-size: clamp(0.85rem, 2.8vmin, 1.35rem);
            flex: 1 1 4rem;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .row-meta {
            font-size: clamp(0.7rem, 2.2vmin, 1rem);
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: #a8afa9;
            flex-shrink: 0;
        }
        .site-history {
            display: flex;
            gap: 2px;
            flex-shrink: 0;
            align-items: center;
            max-width: 4.5rem;
        }
        .site-hist-dot {
            flex: 1;
            height: 0.35rem;
            border-radius: 2px;
            background: #3a423d;
            max-width: 0.35rem;
        }
        .site-hist-dot.up { background: #6fcf8f; }
        .site-hist-dot.down { background: #e05252; }
        .site-hist-dot.unknown { background: #6a756f; }
        .site-hist-dot.maintenance { background: #e8c96a; }
        .c-ok { color: #6fcf8f; }
        .c-warn { color: #e8c96a; }
        .c-bad { color: #e05252; }
        .c-muted { color: #a8afa9; }
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
            <div id="incidents" class="incident-bar hidden" role="alert"></div>

            <div class="board-columns">
                <section id="sites-pane" class="board-pane hidden" aria-label="{{ __('Websites') }}">
                    <h2 class="pane-title">{{ __('Websites') }}</h2>
                    <ul class="pane-list" id="site-list"></ul>
                </section>

                <section id="servers-pane" class="board-pane hidden" aria-label="{{ __('Servers') }}">
                    <h2 class="pane-title">{{ __('Servers') }}</h2>
                    <ul class="pane-list" id="host-list"></ul>
                </section>
            </div>
        </div>
    </main>

    <script>
        (function () {
            var STALE_MS = 90000;
            var REFRESH_MS = 15000;
            var MAX_ROWS = 24;
            var apiUrl = @json($apiUrl);
            var pageRevision = @json($hubRevision);
            var lastSuccess = null;
            var data = null;
            var strings = {
                allGood: @json(__('ALL GOOD')),
                attention: @json(__('ATTENTION')),
                ack: @json(__('ack')),
                stale: @json(__('Stale data — last update :ago ago'))
            };

            function since(iso) {
                if (!iso) return '';
                var m = Math.max(0, Math.floor((Date.now() - Date.parse(iso)) / 60000));
                return m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min';
            }

            function levelClass(v, warn, crit) {
                if (v === null || v === undefined) return 'c-muted';
                if (v >= crit) return 'c-bad';
                if (v >= warn) return 'c-warn';
                return 'c-ok';
            }

            function siteRank(s) {
                if (s.enabled && !s.in_maintenance && s.status === 'down') return 0;
                if (s.in_maintenance) return 2;
                if (s.status === 'unknown') return 1;
                return 3;
            }

            function hostRank(h) {
                return h.online ? 1 : 0;
            }

            function setPaneRows(listEl, items, renderItem) {
                listEl.innerHTML = '';
                var n = Math.min(items.length, MAX_ROWS);
                if (n === 0) return 0;
                for (var i = 0; i < n; i++) {
                    listEl.appendChild(renderItem(items[i]));
                }
                return n;
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

                var inc = document.getElementById('incidents');
                if (data.active_incidents.length) {
                    inc.textContent = data.active_incidents.map(function (i) {
                        return i.title + ' — ' + since(i.since) + (i.acknowledged ? ' (' + strings.ack + ')' : '');
                    }).join(' · ');
                    inc.classList.remove('hidden');
                } else {
                    inc.classList.add('hidden');
                }

                var sites = (data.sites || []).slice().sort(function (a, b) {
                    var d = siteRank(a) - siteRank(b);
                    return d !== 0 ? d : (a.name < b.name ? -1 : a.name > b.name ? 1 : 0);
                });

                var sitePane = document.getElementById('sites-pane');
                var siteList = document.getElementById('site-list');
                setPaneRows(siteList, sites, function (s) {
                    var li = document.createElement('li');
                    var down = s.enabled && !s.in_maintenance && s.status === 'down';
                    li.className = 'surface row' + (down ? ' row-down' : '');
                    var dot = document.createElement('span');
                    dot.className = 'dot ' + (s.in_maintenance ? 'unknown' : (s.status === 'up' ? 'up' : (s.status === 'down' ? 'down' : 'unknown')));
                    var name = document.createElement('span');
                    name.className = 'row-name';
                    name.textContent = s.name;
                    var hist = document.createElement('div');
                    hist.className = 'site-history';
                    (s.history || []).forEach(function (st) {
                        var d = document.createElement('span');
                        d.className = 'site-hist-dot ' + st;
                        hist.appendChild(d);
                    });
                    var lat = document.createElement('span');
                    if (s.latency_ms != null) {
                        lat.textContent = s.latency_ms + ' ms';
                        lat.className = 'row-meta ' + (s.latency_ms >= 2000 ? 'c-bad' : s.latency_ms >= 800 ? 'c-warn' : 'c-ok');
                    } else {
                        lat.textContent = '—';
                        lat.className = 'row-meta c-muted';
                    }
                    li.appendChild(dot);
                    li.appendChild(name);
                    if ((s.history || []).length) li.appendChild(hist);
                    li.appendChild(lat);
                    return li;
                });
                if (sites.length) {
                    sitePane.classList.remove('hidden');
                } else {
                    sitePane.classList.add('hidden');
                }

                var hosts = (data.hosts || []).slice().sort(function (a, b) {
                    var d = hostRank(a) - hostRank(b);
                    return d !== 0 ? d : (a.name < b.name ? -1 : a.name > b.name ? 1 : 0);
                });

                var hostPane = document.getElementById('servers-pane');
                var hostList = document.getElementById('host-list');
                setPaneRows(hostList, hosts, function (h) {
                    var li = document.createElement('li');
                    li.className = 'surface row' + (h.online ? '' : ' host-offline');
                    var dot = document.createElement('span');
                    dot.className = 'dot ' + (h.online ? 'up' : 'down');
                    var name = document.createElement('span');
                    name.className = 'row-name';
                    name.textContent = h.name;
                    var meta = document.createElement('span');
                    var cpu = h.cpu_avg_5m;
                    var mem = h.mem_pct;
                    var disk = h.disk_pct;
                    meta.className = 'row-meta';
                    meta.innerHTML =
                        '<span class="' + levelClass(cpu, 70, 90) + '">CPU ' + (cpu != null ? cpu : '–') + '%</span> ' +
                        '<span class="' + levelClass(mem, 80, 92) + '">RAM ' + (mem != null ? mem : '–') + '%</span> ' +
                        '<span class="' + levelClass(disk, 80, 92) + '">DSK ' + (disk != null ? disk : '–') + '%</span>';
                    li.appendChild(dot);
                    li.appendChild(name);
                    li.appendChild(meta);
                    return li;
                });
                if (hosts.length) {
                    hostPane.classList.remove('hidden');
                } else {
                    hostPane.classList.add('hidden');
                }

                var cols = document.querySelector('.board-columns');
                var showSites = sites.length > 0;
                var showHosts = hosts.length > 0;
                cols.classList.toggle('board-columns--single', showSites !== showHosts);
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
                        if (json.hub_revision && json.hub_revision !== pageRevision) {
                            window.location.reload();
                            return;
                        }
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