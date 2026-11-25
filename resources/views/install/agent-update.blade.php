# Phare agent update (Linux). Keeps /etc/phare-agent/config.yaml — no re-enrollment.
set -eu

AGENT_URL_PREFIX={!! $agentDownloadPrefix !!}

say() { printf '%s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

say "Phare - updating the monitoring agent..."

[ "$(uname -s)" = "Linux" ] || fail "This installer only supports Linux servers."
[ "$(id -u)" = "0" ] || fail "Please run it as administrator: add 'sudo' before 'sh'."
[ -f /etc/phare-agent/config.yaml ] || fail "No agent config at /etc/phare-agent/config.yaml — use the full install line first."

case "$(uname -m)" in
    x86_64|amd64) ARCH=amd64 ;;
    aarch64|arm64) ARCH=arm64 ;;
    *) fail "Unsupported processor: $(uname -m)." ;;
esac

BIN=/usr/local/bin/phare-agent
TMP="$(mktemp)"
AGENT_URL="${AGENT_URL_PREFIX}${ARCH}"

say "1/2 Downloading the agent..."
if command -v curl >/dev/null 2>&1; then
    curl -fsSL "$AGENT_URL" -o "$TMP" || fail "Download failed. The official Phare release {{ $agentReleaseTag }} must be published on GitHub (this server needs outbound HTTPS)."
elif command -v wget >/dev/null 2>&1; then
    wget -q "$AGENT_URL" -O "$TMP" || fail "Download failed."
else
    fail "Neither curl nor wget is installed."
fi
chmod 755 "$TMP"
mv "$TMP" "$BIN"

if ! grep -q '^auto_update:' /etc/phare-agent/config.yaml 2>/dev/null; then
    printf '\nauto_update: true\n' >> /etc/phare-agent/config.yaml
fi

say "2/2 Restarting the agent..."
if command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]; then
    if systemctl is-enabled phare-agent >/dev/null 2>&1 || systemctl is-active phare-agent >/dev/null 2>&1; then
        systemctl restart phare-agent
    else
        cat > /etc/systemd/system/phare-agent.service <<'UNIT'
[Unit]
Description=Phare monitoring agent
After=network-online.target
Wants=network-online.target

[Service]
ExecStart=/usr/local/bin/phare-agent -config /etc/phare-agent/config.yaml
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
UNIT
        systemctl daemon-reload
        systemctl enable --now phare-agent
    fi
    say "Done! Metrics should resume within a minute (same API token)."
else
    say "Binary updated. Restart the agent manually:"
    say "  $BIN -config /etc/phare-agent/config.yaml"
fi