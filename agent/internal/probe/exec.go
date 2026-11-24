package probe

import (
	"context"
	"crypto/tls"
	"fmt"
	"io"
	"net"
	"net/http"
	"strings"
	"time"
)

const (
	maxBodyBytes = 1 << 20
	maxErrLen    = 300
	defaultTO    = 10 * time.Second
)

func timeoutOf(c Check) time.Duration {
	if c.TimeoutSec <= 0 {
		return defaultTO
	}
	return time.Duration(c.TimeoutSec) * time.Second
}

func truncErr(s string) string {
	if len(s) > maxErrLen {
		s = strings.ToValidUTF8(s[:maxErrLen], "")
	}
	return s
}

func finish(c Check, start time.Time, err error, meta map[string]any) Result {
	r := Result{CheckID: c.ID, LatencyMS: time.Since(start).Milliseconds(), Metadata: meta}
	if err == nil {
		r.OK = true
	} else {
		s := truncErr(err.Error())
		r.Error = &s
	}
	return r
}

// Execute exécute un check selon son type.
func Execute(ctx context.Context, c Check) Result {
	switch c.Type {
	case "http":
		return execHTTP(ctx, c)
	case "tcp":
		return execTCP(ctx, c)
	case "dns":
		return execDNS(ctx, c)
	case "icmp":
		return execICMP(ctx, c)
	}
	return finish(c, time.Now(), fmt.Errorf("type de check non supporté: %s", c.Type), nil)
}

func execHTTP(ctx context.Context, c Check) Result {
	start := time.Now()
	ctx, cancel := context.WithTimeout(ctx, timeoutOf(c))
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, c.Target, nil)
	if err != nil {
		return finish(c, start, err, nil)
	}
	req.Header.Set("User-Agent", "phare-probe/1")
	client := &http.Client{
		Transport: &http.Transport{
			Proxy:             nil,
			DisableKeepAlives: true,
			TLSClientConfig:   &tls.Config{MinVersion: tls.VersionTLS12},
		},
		CheckRedirect: func(*http.Request, []*http.Request) error { return http.ErrUseLastResponse },
	}
	resp, err := client.Do(req)
	if err != nil {
		return finish(c, start, err, nil)
	}
	defer resp.Body.Close()
	meta := map[string]any{"http_status": resp.StatusCode}
	if resp.TLS != nil && len(resp.TLS.PeerCertificates) > 0 {
		days := int(time.Until(resp.TLS.PeerCertificates[0].NotAfter).Hours() / 24)
		meta["ssl_days_remaining"] = days
	}
	var body []byte
	if c.Config.Keyword != "" {
		body, err = io.ReadAll(io.LimitReader(resp.Body, maxBodyBytes))
		if err != nil {
			return finish(c, start, err, meta)
		}
	}
	elapsed := time.Since(start)
	okStatus := resp.StatusCode < 400
	if c.Config.ExpectedStatus != 0 {
		okStatus = resp.StatusCode == c.Config.ExpectedStatus
	}
	var ferr error
	switch {
	case !okStatus:
		ferr = fmt.Errorf("HTTP %d", resp.StatusCode)
	case c.Config.Keyword != "" && !strings.Contains(string(body), c.Config.Keyword):
		ferr = fmt.Errorf("Keyword not found")
	}
	r := finish(c, start, ferr, meta)
	r.LatencyMS = elapsed.Milliseconds()
	return r
}

func execTCP(ctx context.Context, c Check) Result {
	start := time.Now()
	d := net.Dialer{Timeout: timeoutOf(c)}
	conn, err := d.DialContext(ctx, "tcp", c.Target)
	lat := time.Since(start)
	if err == nil {
		conn.Close()
	}
	r := finish(c, start, err, nil)
	r.LatencyMS = lat.Milliseconds()
	return r
}

func execDNS(ctx context.Context, c Check) Result {
	start := time.Now()
	ctx, cancel := context.WithTimeout(ctx, timeoutOf(c))
	defer cancel()
	addrs, err := net.DefaultResolver.LookupHost(ctx, c.Target)
	if err == nil && len(addrs) == 0 {
		err = fmt.Errorf("no addresses for %s", c.Target)
	}
	return finish(c, start, err, nil)
}
