# Nocline agent update (Linux). Keeps /etc/nocline-agent/config.yaml — no re-enrollment.
set -eu

AGENT_URL_PREFIX={!! $agentDownloadPrefix !!}

say() { printf '%s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

say "Nocline - updating the monitoring agent..."

[ "$(uname -s)" = "Linux" ] || fail "This installer only supports Linux servers."
[ "$(id -u)" = "0" ] || fail "Please run it as administrator: add 'sudo' before 'sh'."
[ -f /etc/nocline-agent/config.yaml ] || fail "No agent config at /etc/nocline-agent/config.yaml — use the full install line first."

case "$(uname -m)" in
    x86_64|amd64) ARCH=amd64 ;;
    aarch64|arm64) ARCH=arm64 ;;
    *) fail "Unsupported processor: $(uname -m)." ;;
esac

BIN=/usr/local/bin/nocline-agent
TMP="$(mktemp)"
AGENT_URL="${AGENT_URL_PREFIX}${ARCH}"

say "1/2 Downloading the agent..."
if command -v curl >/dev/null 2>&1; then
    curl -fsSL "$AGENT_URL" -o "$TMP" || fail "Download failed. The official Nocline release {{ $agentReleaseTag }} must be published on GitHub (this server needs outbound HTTPS)."
elif command -v wget >/dev/null 2>&1; then
    wget -q "$AGENT_URL" -O "$TMP" || fail "Download failed."
else
    fail "Neither curl nor wget is installed."
fi
chmod 755 "$TMP"
mv "$TMP" "$BIN"

say "2/2 Restarting the agent..."
if command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]; then
    if systemctl is-enabled nocline-agent >/dev/null 2>&1 || systemctl is-active nocline-agent >/dev/null 2>&1; then
        systemctl restart nocline-agent
    else
        cat > /etc/systemd/system/nocline-agent.service <<'UNIT'
[Unit]
Description=Nocline monitoring agent
After=network-online.target
Wants=network-online.target

[Service]
ExecStart=/usr/local/bin/nocline-agent -config /etc/nocline-agent/config.yaml
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
UNIT
        systemctl daemon-reload
        systemctl enable --now nocline-agent
    fi
    say "Done! Metrics should resume within a minute (same API token)."
else
    say "Binary updated. Restart the agent manually:"
    say "  $BIN -config /etc/nocline-agent/config.yaml"
fi