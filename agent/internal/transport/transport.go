// Package transport pousse les lots réduits vers POST /api/v1/ingest (push-only) avec retry et buffer borné.
package transport

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"regexp"
	"strings"
	"sync"
	"time"

	"github.com/phare/phare/agent/internal/collect"
	"github.com/phare/phare/agent/internal/meta"
)

const (
	MaxMetrics     = 64
	MaxBufferBytes = 10 << 20
	// Le hub horodate à la réception : un petit buffer suffit, les vieux lots sont peu utiles.
	maxBufferBatches = 120
)

var (
	ErrUnauthorized = errors.New("transport: 401 token refusé par le hub")
	keyRe           = regexp.MustCompile(`^[a-z0-9_.]+$`)
)

// Batch est le corps JSON de /api/v1/ingest.
type Batch struct {
	AgentVersion string           `json:"agent_version"`
	WindowSec    int              `json:"window_sec"`
	Metrics      []collect.Metric `json:"metrics"`
	HostMeta     *meta.Snapshot   `json:"host_meta,omitempty"`
}

// permanentError : rejet définitif (4xx hors 401/408/429) — inutile de réessayer.
type permanentError struct{ status int }

func (e permanentError) Error() string {
	return fmt.Sprintf("transport: hub a rejeté le lot (%d)", e.status)
}

// Client envoie un lot.
type Client struct {
	HubURL string
	Token  string
	HTTP   *http.Client
}

// Sanitize filtre les clés invalides et plafonne à MaxMetrics.
func Sanitize(ms []collect.Metric) []collect.Metric {
	out := make([]collect.Metric, 0, len(ms))
	for _, m := range ms {
		if keyRe.MatchString(m.Key) && len(out) < MaxMetrics {
			out = append(out, m)
		}
	}
	return out
}

// Push envoie un lot ; attend 202.
func (c *Client) Push(ctx context.Context, b Batch) error {
	hc := c.HTTP
	if hc == nil {
		hc = &http.Client{Timeout: 15 * time.Second}
	}
	body, err := json.Marshal(b)
	if err != nil {
		return err
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, strings.TrimRight(c.HubURL, "/")+"/api/v1/ingest", bytes.NewReader(body))
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+c.Token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	resp, err := hc.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	_, _ = io.Copy(io.Discard, io.LimitReader(resp.Body, 1<<16))
	switch {
	case resp.StatusCode == http.StatusAccepted:
		return nil
	case resp.StatusCode == http.StatusUnauthorized:
		return ErrUnauthorized
	case resp.StatusCode >= 400 && resp.StatusCode < 500 && resp.StatusCode != 408 && resp.StatusCode != 429:
		return permanentError{resp.StatusCode}
	default:
		return fmt.Errorf("transport: statut inattendu %d", resp.StatusCode)
	}
}

type queued struct {
	batch Batch
	size  int
}

// Sender met en file les lots et les envoie dans l'ordre, avec backoff exponentiel.
type Sender struct {
	Client      *Client
	Logf        func(format string, args ...any)
	Sleep       func(ctx context.Context, d time.Duration) // injectable pour les tests
	Attempts    int                                        // tentatives par lot et par appel (défaut 4)
	BaseBackoff time.Duration                              // défaut 1s, doublé à chaque échec
	MaxBackoff  time.Duration                              // défaut 30s
	AuthPause   time.Duration                              // pause après 401 (défaut 5 min)

	mu         sync.Mutex
	queue      []queued
	bytes      int
	pausedTill time.Time
	Now        func() time.Time
}

func (s *Sender) logf(f string, a ...any) {
	if s.Logf != nil {
		s.Logf(f, a...)
	}
}

func (s *Sender) now() time.Time {
	if s.Now != nil {
		return s.Now()
	}
	return time.Now()
}

// Pending renvoie le nombre de lots en attente.
func (s *Sender) Pending() int {
	s.mu.Lock()
	defer s.mu.Unlock()
	return len(s.queue)
}

func (s *Sender) enqueue(b Batch) {
	raw, _ := json.Marshal(b)
	s.mu.Lock()
	defer s.mu.Unlock()
	s.queue = append(s.queue, queued{b, len(raw)})
	s.bytes += len(raw)
	for len(s.queue) > 1 && (s.bytes > MaxBufferBytes || len(s.queue) > maxBufferBatches) {
		s.bytes -= s.queue[0].size
		s.queue = s.queue[1:]
		s.logf("buffer plein : lot le plus ancien abandonné")
	}
}

func (s *Sender) head() (queued, bool) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if len(s.queue) == 0 {
		return queued{}, false
	}
	return s.queue[0], true
}

func (s *Sender) pop() {
	s.mu.Lock()
	defer s.mu.Unlock()
	if len(s.queue) > 0 {
		s.bytes -= s.queue[0].size
		s.queue = s.queue[1:]
	}
}

func sleepCtx(ctx context.Context, d time.Duration) {
	t := time.NewTimer(d)
	defer t.Stop()
	select {
	case <-ctx.Done():
	case <-t.C:
	}
}

// Send enfile b puis vide la file. Les lots en échec restent en buffer pour le prochain appel.
func (s *Sender) Send(ctx context.Context, b Batch) error {
	b.Metrics = Sanitize(b.Metrics)
	if len(b.Metrics) > 0 {
		s.enqueue(b)
	}
	attempts, base, maxB, pause := s.Attempts, s.BaseBackoff, s.MaxBackoff, s.AuthPause
	if attempts <= 0 {
		attempts = 4
	}
	if base <= 0 {
		base = time.Second
	}
	if maxB <= 0 {
		maxB = 30 * time.Second
	}
	if pause <= 0 {
		pause = 5 * time.Minute
	}
	sleep := s.Sleep
	if sleep == nil {
		sleep = sleepCtx
	}

	s.mu.Lock()
	paused := s.now().Before(s.pausedTill)
	s.mu.Unlock()
	if paused {
		return ErrUnauthorized
	}

	var lastErr error
	for {
		item, ok := s.head()
		if !ok {
			return nil
		}
		delay := base
		for i := 0; ; i++ {
			err := s.Client.Push(ctx, item.batch)
			if err == nil {
				s.pop()
				break
			}
			var perm permanentError
			if errors.As(err, &perm) {
				s.logf("%v : lot abandonné", err)
				s.pop()
				break
			}
			if errors.Is(err, ErrUnauthorized) {
				s.logf("%v : nouvel essai dans %s", err, pause)
				s.mu.Lock()
				s.pausedTill = s.now().Add(pause)
				s.mu.Unlock()
				return err
			}
			lastErr = err
			if i+1 >= attempts || ctx.Err() != nil {
				s.logf("envoi échoué (%v), %d lot(s) en buffer", err, s.Pending())
				return lastErr
			}
			sleep(ctx, delay)
			if delay *= 2; delay > maxB {
				delay = maxB
			}
		}
	}
}
