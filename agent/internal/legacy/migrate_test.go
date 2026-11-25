package legacy

import (
	"os"
	"path/filepath"
	"testing"
)

func TestResolveConfigPath(t *testing.T) {
	dir := t.TempDir()
	noc := filepath.Join(dir, "nocline", "config.yaml")
	leg := filepath.Join(dir, "phare", "config.yaml")
	_ = os.MkdirAll(filepath.Dir(noc), 0o755)
	_ = os.MkdirAll(filepath.Dir(leg), 0o755)
	_ = os.WriteFile(leg, []byte("hub_url: https://x\n"), 0o600)

	got := ResolveConfigPath(noc, noc, leg)
	if got != leg {
		t.Fatalf("want legacy %q got %q", leg, got)
	}
	_ = os.WriteFile(noc, []byte("hub_url: https://y\n"), 0o600)
	got = ResolveConfigPath(noc, noc, leg)
	if got != noc {
		t.Fatalf("want nocline %q got %q", noc, got)
	}
}
