#!/usr/bin/env bash
# Builds the phare-agent binaries served by the hub (/install/agent/linux-{amd64,arm64}).
set -euo pipefail
cd "$(dirname "$0")/../agent"
VERSION="$(tr -d '[:space:]' < ../VERSION)"
OUT="${PHARE_AGENT_BINARY_DIR:-../storage/app/agent}"
mkdir -p "$OUT"
for arch in amd64 arm64; do
    out="$OUT/phare-agent-linux-$arch"
    CGO_ENABLED=0 GOOS=linux GOARCH=$arch go build -trimpath -ldflags "-s -w -X main.version=$VERSION" -o "$out" ./cmd/phare-agent
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$out" | awk '{print $1}' >"$out.sha256"
    elif command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$out" | awk '{print $1}' >"$out.sha256"
    fi
    echo "built $out"
done
