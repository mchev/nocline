package update

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"runtime"
	"testing"
	"time"
)

func TestVersionOlder(t *testing.T) {
	if !versionOlder("0.1.2", "0.1.3") {
		t.Fatal("want older")
	}
	if versionOlder("0.1.3", "0.1.3") {
		t.Fatal("same version")
	}
	if versionOlder("0.2.0", "0.1.9") {
		t.Fatal("newer current")
	}
}

func TestApplyVerifiesSHA256AndReplaces(t *testing.T) {
	content := append([]byte("#!/bin/sh\necho phare-test-agent\n"), make([]byte, minBinaryBytes)...)
	sum := sha256.Sum256(content)
	hash := hex.EncodeToString(sum[:])

	dir := t.TempDir()
	execPath := filepath.Join(dir, "phare-agent")
	if err := os.WriteFile(execPath, []byte("#!/bin/sh\nold\n"), 0o755); err != nil {
		t.Fatal(err)
	}

	var restarted bool
	srv := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(200)
		_, _ = w.Write(content)
	}))
	defer srv.Close()

	arch := runtime.GOARCH
	offer := &Offer{
		Version: "9.9.9",
		Binaries: map[string]BinaryRef{
			arch: {URL: srv.URL, SHA256: hash},
		},
	}
	if err := offer.Resolve(); err != nil {
		t.Fatalf("resolve: %v", err)
	}

	err := apply(context.Background(), offer, Settings{
		ExecPath: execPath,
		HTTP:     srv.Client(),
		RestartCmd: func() error {
			restarted = true
			return nil
		},
	})
	if err != nil {
		t.Fatal(err)
	}
	got, err := os.ReadFile(execPath)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != len(content) {
		t.Fatal("binary not replaced")
	}
	if _, err := os.Stat(execPath + ".prev"); err != nil {
		t.Fatal("expected .prev backup")
	}
	if !restarted {
		t.Fatal("expected restart")
	}
}

func TestCooldownSkipsApply(t *testing.T) {
	lastAttempt = time.Now()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		t.Fatal("download should not run during cooldown")
	}))
	defer srv.Close()

	arch := runtime.GOARCH
	offer := &Offer{
		Version: "9.9.9",
		Binaries: map[string]BinaryRef{
			arch: {URL: srv.URL, SHA256: hex.EncodeToString(make([]byte, 32))},
		},
	}

	MaybeApply(context.Background(), offer, Settings{
		AutoUpdate: true,
		NowVersion: "0.0.1",
	})
}