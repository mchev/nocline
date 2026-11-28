//go:build linux

package meta

import (
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestRebootRequiredDebianMarker(t *testing.T) {
	dir := t.TempDir()
	marker := filepath.Join(dir, "reboot-required")
	if err := os.WriteFile(marker, []byte("linux-image\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	past := time.Now().Add(-2 * time.Hour).UTC()
	if err := os.Chtimes(marker, past, past); err != nil {
		t.Fatal(err)
	}

	prev := rebootMarkerPath
	rebootMarkerPath = marker
	t.Cleanup(func() { rebootMarkerPath = prev })

	required, since := RebootRequired()

	if !required || since == "" {
		t.Fatalf("expected reboot required, got %v %q", required, since)
	}

	got, err := time.Parse(time.RFC3339, since)
	if err != nil {
		t.Fatal(err)
	}

	if got.Unix() != past.Unix() {
		t.Fatalf("since %v want %v", got, past)
	}
}