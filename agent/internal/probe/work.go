// Package probe implémente le rôle probe : pull des checks, exécution locale, push des résultats.
package probe

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"
)

// MaxResultsPerRequest est la taille max d'un POST /probe/results.
const MaxResultsPerRequest = 200

// ErrUnauthorized : le hub refuse le token (401).
var ErrUnauthorized = errors.New("probe: 401 token refusé par le hub")

// Check est un travail renvoyé par GET /api/v1/probe/work.
type Check struct {
	ID         int64       `json:"id"`
	Type       string      `json:"type"`
	Target     string      `json:"target"`
	TimeoutSec int         `json:"timeout_sec"`
	Config     CheckConfig `json:"config"`
}

// CheckConfig regroupe les options optionnelles d'un check.
type CheckConfig struct {
	Keyword        string `json:"keyword"`
	ExpectedStatus int    `json:"expected_status"`
}

// UnmarshalJSON tolère un tableau vide (« [] », sérialisation PHP d'un objet vide) ou null.
func (c *CheckConfig) UnmarshalJSON(b []byte) error {
	if t := bytes.TrimSpace(b); len(t) == 0 || t[0] != '{' {
		*c = CheckConfig{}
		return nil
	}
	type plain CheckConfig
	var p plain
	if err := json.Unmarshal(b, &p); err != nil {
		return err
	}
	*c = CheckConfig(p)
	return nil
}

// Result est un résultat poussé vers POST /api/v1/probe/results.
type Result struct {
	CheckID   int64          `json:"check_id"`
	OK        bool           `json:"ok"`
	LatencyMS int64          `json:"latency_ms"`
	Error     *string        `json:"error"`
	Metadata  map[string]any `json:"metadata,omitempty"`
}

// Client dialogue avec le hub.
type Client struct {
	HubURL string
	Token  string
	HTTP   *http.Client
}

func (c *Client) do(ctx context.Context, method, path string, body []byte) (*http.Response, error) {
	hc := c.HTTP
	if hc == nil {
		hc = &http.Client{Timeout: 20 * time.Second}
	}
	var rd io.Reader
	if body != nil {
		rd = bytes.NewReader(body)
	}
	req, err := http.NewRequestWithContext(ctx, method, strings.TrimRight(c.HubURL, "/")+path, rd)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Authorization", "Bearer "+c.Token)
	req.Header.Set("Accept", "application/json")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	return hc.Do(req)
}

// FetchWork appelle GET /api/v1/probe/work?capabilities=...
func (c *Client) FetchWork(ctx context.Context, capabilities []string) ([]Check, error) {
	// Virgules gardées lisibles dans la query.
	path := "/api/v1/probe/work?capabilities=" + strings.ReplaceAll(url.QueryEscape(strings.Join(capabilities, ",")), "%2C", ",")
	resp, err := c.do(ctx, http.MethodGet, path, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	switch {
	case resp.StatusCode == http.StatusUnauthorized:
		return nil, ErrUnauthorized
	case resp.StatusCode != http.StatusOK:
		return nil, fmt.Errorf("probe: work: statut %d", resp.StatusCode)
	}
	return ParseWork(raw)
}

// ParseWork décode {"checks":[...]}.
func ParseWork(raw []byte) ([]Check, error) {
	var w struct {
		Checks []Check `json:"checks"`
	}
	if err := json.Unmarshal(raw, &w); err != nil {
		return nil, fmt.Errorf("probe: réponse work invalide: %w", err)
	}
	return w.Checks, nil
}

type permanentError struct{ status int }

func (e permanentError) Error() string {
	return fmt.Sprintf("probe: hub a rejeté les résultats (%d)", e.status)
}

// PostResults envoie un lot (≤ MaxResultsPerRequest) ; attend 202.
func (c *Client) PostResults(ctx context.Context, rs []Result) error {
	body, err := json.Marshal(map[string]any{"results": rs})
	if err != nil {
		return err
	}
	resp, err := c.do(ctx, http.MethodPost, "/api/v1/probe/results", body)
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
		return fmt.Errorf("probe: results: statut inattendu %d", resp.StatusCode)
	}
}

// PostResultsRetry découpe en lots de 200 et réessaie avec backoff exponentiel.
func (c *Client) PostResultsRetry(ctx context.Context, rs []Result, attempts int, base, maxB time.Duration, sleep func(context.Context, time.Duration)) error {
	if attempts <= 0 {
		attempts = 4
	}
	for len(rs) > 0 {
		n := len(rs)
		if n > MaxResultsPerRequest {
			n = MaxResultsPerRequest
		}
		chunk := rs[:n]
		rs = rs[n:]
		delay := base
		for i := 0; ; i++ {
			err := c.PostResults(ctx, chunk)
			if err == nil {
				break
			}
			var perm permanentError
			if errors.As(err, &perm) {
				break // lot rejeté définitivement : abandon
			}
			if errors.Is(err, ErrUnauthorized) || i+1 >= attempts || ctx.Err() != nil {
				return err
			}
			sleep(ctx, delay)
			if delay *= 2; delay > maxB {
				delay = maxB
			}
		}
	}
	return nil
}
