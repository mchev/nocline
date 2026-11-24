#!/usr/bin/env bash
# Builds the phare-agent binaries served by the hub (/install/agent/linux-{amd64,arm64}).
set -euo pipefail
cd "$(dirname "$0")/../agent"
VERSION="$(tr -d '[:space:]' < ../VERSION)"
OUT="${PHARE_AGENT_BINARY_DIR:-../storage/app/agent}"
mkdir -p "$OUT"
for arch in amd64 arm64; do
    CGO_ENABLED=0 GOOS=linux GOARCH=$arch go build -trimpath -ldflags "-s -w -X main.version=$VERSION" -o "$OUT/phare-agent-linux-$arch" ./cmd/phare-agent
    echo "built $OUT/phare-agent-linux-$arch"
done
