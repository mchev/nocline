#!/usr/bin/env bash
# Build phare-agent for Linux and attach binaries to the GitHub release for VERSION (mchev/phare).
# Usage: scripts/publish-agent-assets.sh [vX.Y.Z]
# Requires: go, gh CLI authenticated (gh auth login).
set -euo pipefail
cd "$(dirname "$0")/.."

TAG="${1:-v$(tr -d '[:space:]' < VERSION)}"
REPO="${PHARE_OFFICIAL_REPOSITORY:-mchev/phare}"

command -v go >/dev/null || { echo "Go is required. Install Go or use GitHub Actions (push tag v*)." >&2; exit 1; }
command -v gh >/dev/null || { echo "GitHub CLI (gh) is required." >&2; exit 1; }

DIST="$PWD/dist"
rm -rf "$DIST"
PHARE_AGENT_BINARY_DIR="$DIST" scripts/build-agent.sh

if ! gh release view "$TAG" --repo "$REPO" >/dev/null 2>&1; then
  echo "Release $TAG not found on $REPO. Create it first, or push the tag to trigger .github/workflows/release.yml"
  exit 1
fi

echo "Uploading to $REPO release $TAG ..."
gh release upload "$TAG" "$DIST"/* --clobber --repo "$REPO"
echo "Done. Test:"
echo "  curl -fsSI https://github.com/$REPO/releases/download/$TAG/phare-agent-linux-amd64 | head -3"