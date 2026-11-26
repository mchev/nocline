//go:build linux

package meta

import "testing"

func TestSanitizeVersion(t *testing.T) {
	if got := sanitizeVersion("mysql  Ver 8.0.36 for Linux on x86_64"); got != "8.0.36" {
		t.Fatalf("mysql: %q", got)
	}
	if got := sanitizeVersion("nginx version: nginx/1.24.0 (Ubuntu)"); got != "1.24.0" {
		t.Fatalf("nginx: %q", got)
	}
	if got := sanitizeVersion(""); got != "" {
		t.Fatal("empty")
	}
	if got := sanitizeVersion("totally bogus !!!"); got != "" {
		t.Fatalf("reject junk: %q", got)
	}
}