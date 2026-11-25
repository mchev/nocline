// Package update applies an optional hub-offered agent upgrade (SHA256, backup, systemd restart).
package update

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"sync"
	"time"
)

const (
	minBinaryBytes = 500_000
	cooldown       = 6 * time.Hour
	serviceName    = "phare-agent"
)

// Offer is returned by the hub in POST /api/v1/ingest when a newer agent exists.
type Offer struct {
	Version   string                       `json:"version"`
	Binaries  map[string]BinaryRef         `json:"binaries"`
	Binary    *BinaryRef                   `json:"-"` // resolved arch
}

type BinaryRef struct {
	URL    string `json:"url"`
	SHA256 string `json:"sha256"`
}

var (
	mu              sync.Mutex
	lastAttempt     time.Time
	ErrNotEligible  = errors.New("update: not eligible")
	ErrOfferInvalid = errors.New("update: invalid offer")
)

// Resolve picks the binary for the running GOARCH (linux amd64/arm64).
func (o *Offer) Resolve() error {
	if o == nil {
		return ErrOfferInvalid
	}
	arch := runtime.GOARCH
	switch arch {
	case "amd64", "arm64":
	default:
		return fmt.Errorf("update: unsupported arch %q", arch)
	}
	ref, ok := o.Binaries[arch]
	if !ok || ref.URL == "" || ref.SHA256 == "" {
		return ErrOfferInvalid
	}
	o.Binary = &ref
	return nil
}

// Settings controls whether an offered update may run.
type Settings struct {
	AutoUpdate   bool
	NowVersion   string
	Logf         func(string, ...any)
	HTTP         *http.Client
	Now          func() time.Time
	ExecPath     string // default os.Executable()
	RestartCmd   func() error
}

// MaybeApply runs at most one upgrade per cooldown when enabled and the hub version is newer.
func MaybeApply(ctx context.Context, offer *Offer, st Settings) {
	if offer == nil || !st.AutoUpdate {
		return
	}
	if err := offer.Resolve(); err != nil {
		return
	}
	if !versionOlder(st.NowVersion, offer.Version) {
		return
	}
	mu.Lock()
	now := time.Now()
	if st.Now != nil {
		now = st.Now()
	}
	if !lastAttempt.IsZero() && now.Sub(lastAttempt) < cooldown {
		mu.Unlock()
		return
	}
	lastAttempt = now
	mu.Unlock()

	if st.Logf == nil {
		st.Logf = func(string, ...any) {}
	}

	st.Logf("mise à jour agent disponible (%s → %s), téléchargement…", st.NowVersion, offer.Version)

	if err := apply(ctx, offer, st); err != nil {
		st.Logf("mise à jour agent échouée: %v", err)
	}
}

func apply(ctx context.Context, offer *Offer, st Settings) error {
	if err := validateOfferURL(offer.Binary.URL); err != nil {
		return err
	}
	execPath := st.ExecPath
	if execPath == "" {
		var err error
		execPath, err = os.Executable()
		if err != nil {
			return err
		}
		execPath, err = filepath.EvalSymlinks(execPath)
		if err != nil {
			return err
		}
	}

	client := st.HTTP
	if client == nil {
		client = &http.Client{Timeout: 5 * time.Minute}
	}

	tmp, err := os.CreateTemp(filepath.Dir(execPath), ".phare-agent-new-*")
	if err != nil {
		return err
	}
	tmpPath := tmp.Name()
	defer func() {
		tmp.Close()
		os.Remove(tmpPath)
	}()

	if err := download(ctx, client, offer.Binary.URL, tmp); err != nil {
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}

	sum, err := fileSHA256(tmpPath)
	if err != nil {
		return err
	}
	want := strings.ToLower(strings.TrimSpace(offer.Binary.SHA256))
	if sum != want {
		return fmt.Errorf("update: sha256 mismatch (got %s)", sum)
	}

	info, err := os.Stat(tmpPath)
	if err != nil {
		return err
	}
	if info.Size() < minBinaryBytes {
		return errors.New("update: binary too small")
	}
	if err := os.Chmod(tmpPath, 0o755); err != nil {
		return err
	}

	backup := execPath + ".prev"
	_ = os.Remove(backup)
	if _, err := os.Stat(execPath); err == nil {
		if err := os.Rename(execPath, backup); err != nil {
			return err
		}
	}
	if err := os.Rename(tmpPath, execPath); err != nil {
		_ = os.Rename(backup, execPath)
		return err
	}
	os.Remove(tmpPath)

	if st.Logf != nil {
		st.Logf("agent %s installé, redémarrage du service…", offer.Version)
	}

	restart := st.RestartCmd
	if restart == nil {
		restart = systemdRestart
	}
	return restart()
}

func validateOfferURL(raw string) error {
	u, err := url.Parse(raw)
	if err != nil {
		return err
	}
	if u.Scheme != "https" || u.Host == "" {
		return errors.New("update: url must be https")
	}
	return nil
}

func download(ctx context.Context, client *http.Client, raw string, dest *os.File) error {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, raw, nil)
	if err != nil {
		return err
	}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("update: download status %d", resp.StatusCode)
	}
	lim := io.LimitReader(resp.Body, 80<<20)
	if _, err := io.Copy(dest, lim); err != nil {
		return err
	}
	return nil
}

func fileSHA256(path string) (string, error) {
	f, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer f.Close()
	h := sha256.New()
	if _, err := io.Copy(h, f); err != nil {
		return "", err
	}
	return hex.EncodeToString(h.Sum(nil)), nil
}

func systemdRestart() error {
	if _, err := exec.LookPath("systemctl"); err != nil {
		return errors.New("update: systemctl introuvable — redémarrez l'agent à la main")
	}
	return exec.Command("systemctl", "restart", serviceName).Run()
}

// versionOlder compares dotted numeric versions (ignores suffix after '-').
func versionOlder(current, target string) bool {
	c := parseVersion(current)
	t := parseVersion(target)
	for i := 0; i < 3; i++ {
		if c[i] < t[i] {
			return true
		}
		if c[i] > t[i] {
			return false
		}
	}
	return false
}

func parseVersion(v string) [3]int {
	var out [3]int
	v = strings.TrimSpace(strings.TrimPrefix(strings.TrimSpace(v), "v"))
	if i := strings.IndexAny(v, "-+"); i >= 0 {
		v = v[:i]
	}
	parts := strings.Split(v, ".")
	for i := 0; i < len(parts) && i < 3; i++ {
		var n int
		fmt.Sscanf(parts[i], "%d", &n)
		out[i] = n
	}
	return out
}

// ParseOfferJSON decodes agent_update from an ingest response body.
func ParseOfferJSON(data []byte) *Offer {
	var outer struct {
		AgentUpdate *Offer `json:"agent_update"`
	}
	if json.Unmarshal(data, &outer) != nil {
		return nil
	}
	return outer.AgentUpdate
}