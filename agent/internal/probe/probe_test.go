package probe

import (
	"context"
	"encoding/json"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"reflect"
	"strings"
	"sync/atomic"
	"testing"
	"time"
)

func TestParseWork(t *testing.T) {
	cs, err := ParseWork([]byte(`{"checks":[{"id":1,"type":"http","target":"https://a","timeout_sec":10,"config":{"keyword":"ok","expected_status":200}},{"id":2,"type":"tcp","target":"h:1","timeout_sec":3,"config":[]}]}`))
	if err != nil || len(cs) != 2 || cs[1].Config != (CheckConfig{}) {
		t.Fatalf("config [] must be tolerated: %+v %v", cs, err)
	}
	cs, err = ParseWork([]byte(`{"checks":[{"id":1,"type":"http","target":"https://a","timeout_sec":10,"config":{"keyword":"ok","expected_status":200}}]}`))
	if err != nil || len(cs) != 1 || cs[0].Config.Keyword != "ok" || cs[0].Config.ExpectedStatus != 200 || cs[0].TimeoutSec != 10 {
		t.Fatalf("%+v %v", cs, err)
	}
}

func TestFetchWorkAuthAndQuery(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer tok" || r.URL.Path != "/api/v1/probe/work" || r.URL.Query().Get("capabilities") != "http,tcp" {
			w.WriteHeader(401)
			return
		}
		_, _ = w.Write([]byte(`{"checks":[{"id":7,"type":"dns","target":"localhost","timeout_sec":2,"config":{}}]}`))
	}))
	defer srv.Close()
	c := &Client{HubURL: srv.URL + "/", Token: "tok"}
	cs, err := c.FetchWork(context.Background(), []string{"http", "tcp"})
	if err != nil || len(cs) != 1 || cs[0].ID != 7 {
		t.Fatalf("%+v %v", cs, err)
	}
	bad := &Client{HubURL: srv.URL, Token: "x"}
	if _, err := bad.FetchWork(context.Background(), []string{"http", "tcp"}); err != ErrUnauthorized {
		t.Fatalf("got %v", err)
	}
}

func TestPostResultsShapeAndRetry(t *testing.T) {
	var calls int32
	var got struct {
		Results []map[string]any `json:"results"`
	}
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer tok" || r.Method != "POST" || r.URL.Path != "/api/v1/probe/results" {
			t.Errorf("bad request %s %s", r.Method, r.URL.Path)
		}
		if atomic.AddInt32(&calls, 1) == 1 {
			w.WriteHeader(503)
			return
		}
		b, _ := io.ReadAll(r.Body)
		_ = json.Unmarshal(b, &got)
		w.WriteHeader(202)
	}))
	defer srv.Close()
	c := &Client{HubURL: srv.URL, Token: "tok"}
	msg := "HTTP 503"
	rs := []Result{{CheckID: 1, OK: true, LatencyMS: 12, Metadata: map[string]any{"http_status": 200}}, {CheckID: 2, Error: &msg}}
	noSleep := func(context.Context, time.Duration) {}
	if err := c.PostResultsRetry(context.Background(), rs, 3, time.Millisecond, time.Second, noSleep); err != nil {
		t.Fatal(err)
	}
	if calls != 2 || len(got.Results) != 2 {
		t.Fatalf("calls=%d %+v", calls, got)
	}
	r0, r1 := got.Results[0], got.Results[1]
	if r0["check_id"] != float64(1) || r0["ok"] != true || r0["latency_ms"] != float64(12) || r0["error"] != nil {
		t.Fatalf("%v", r0)
	}
	if r1["ok"] != false || r1["error"] != "HTTP 503" {
		t.Fatalf("%v", r1)
	}
}

func TestPostResultsChunksAnd401(t *testing.T) {
	var sizes []int
	status := 202
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		var in struct{ Results []Result }
		_ = json.NewDecoder(r.Body).Decode(&in)
		sizes = append(sizes, len(in.Results))
		w.WriteHeader(status)
	}))
	defer srv.Close()
	c := &Client{HubURL: srv.URL, Token: "t"}
	noSleep := func(context.Context, time.Duration) {}
	if err := c.PostResultsRetry(context.Background(), make([]Result, 450), 2, 0, 0, noSleep); err != nil || !reflect.DeepEqual(sizes, []int{200, 200, 50}) {
		t.Fatalf("%v %v", sizes, err)
	}
	status = 401
	if err := c.PostResultsRetry(context.Background(), make([]Result, 1), 2, 0, 0, noSleep); err != ErrUnauthorized {
		t.Fatalf("%v", err)
	}
}

func TestHTTPExecutor(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/ok":
			_, _ = w.Write([]byte("hello world"))
		case "/redir":
			http.Redirect(w, r, "/ok", 302)
		case "/slow":
			time.Sleep(1500 * time.Millisecond)
		default:
			w.WriteHeader(503)
		}
	}))
	defer srv.Close()
	ctx := context.Background()
	run := func(path string, cfg CheckConfig, to int) Result {
		return Execute(ctx, Check{ID: 1, Type: "http", Target: srv.URL + path, TimeoutSec: to, Config: cfg})
	}
	if r := run("/ok", CheckConfig{}, 5); !r.OK || r.Metadata["http_status"] != 200 || r.Error != nil {
		t.Fatalf("%+v", r)
	}
	if r := run("/ok", CheckConfig{Keyword: "world"}, 5); !r.OK {
		t.Fatalf("%+v", r)
	}
	if r := run("/ok", CheckConfig{Keyword: "nope"}, 5); r.OK || *r.Error != "Keyword not found" {
		t.Fatalf("%+v", r)
	}
	if r := run("/err", CheckConfig{}, 5); r.OK || *r.Error != "HTTP 503" {
		t.Fatalf("%+v", r)
	}
	if r := run("/err", CheckConfig{ExpectedStatus: 503}, 5); !r.OK {
		t.Fatalf("%+v", r)
	}
	if r := run("/ok", CheckConfig{ExpectedStatus: 204}, 5); r.OK || *r.Error != "HTTP 200" {
		t.Fatalf("%+v", r)
	}
	if r := run("/redir", CheckConfig{}, 5); !r.OK || r.Metadata["http_status"] != 302 {
		t.Fatalf("redirect must not be followed: %+v", r)
	}
	if r := run("/slow", CheckConfig{}, 1); r.OK || r.Error == nil || r.LatencyMS > 1400 {
		t.Fatalf("%+v", r)
	}
}

func TestHTTPSSLDays(t *testing.T) {
	srv := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {}))
	defer srv.Close()
	// cert de test non fiable : l'échec TLS est attendu et remonté comme erreur réseau.
	r := Execute(context.Background(), Check{ID: 1, Type: "http", Target: srv.URL, TimeoutSec: 3})
	if r.OK || r.Error == nil {
		t.Fatalf("%+v", r)
	}
}

func TestTCPExecutor(t *testing.T) {
	l, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	addr := l.Addr().String()
	go func() {
		for {
			c, err := l.Accept()
			if err != nil {
				return
			}
			c.Close()
		}
	}()
	if r := Execute(context.Background(), Check{ID: 3, Type: "tcp", Target: addr, TimeoutSec: 2}); !r.OK || r.CheckID != 3 {
		t.Fatalf("%+v", r)
	}
	l.Close()
	if r := Execute(context.Background(), Check{ID: 3, Type: "tcp", Target: addr, TimeoutSec: 2}); r.OK || r.Error == nil {
		t.Fatalf("%+v", r)
	}
}

func TestDNSExecutor(t *testing.T) {
	if r := Execute(context.Background(), Check{ID: 1, Type: "dns", Target: "localhost", TimeoutSec: 3}); !r.OK {
		t.Fatalf("%+v", r.Error)
	}
	if r := Execute(context.Background(), Check{ID: 1, Type: "dns", Target: "nonexistent.invalid", TimeoutSec: 3}); r.OK || r.Error == nil {
		t.Fatalf("%+v", r)
	}
}

func TestUnknownTypeAndErrTruncation(t *testing.T) {
	if r := Execute(context.Background(), Check{Type: "zzz"}); r.OK || r.Error == nil {
		t.Fatal("expected failure")
	}
	if got := truncErr(strings.Repeat("é", 400)); len(got) > maxErrLen {
		t.Fatalf("len %d", len(got))
	}
}

func TestChecksumAndEchoPacket(t *testing.T) {
	// Exemple classique : un paquet dont le checksum est correct se re-somme à 0.
	p := BuildEchoRequest(0x1234, 7, []byte("abc"))
	if p[0] != 8 || p[1] != 0 || len(p) != 11 || Checksum(p) != 0 {
		t.Fatalf("%x", p)
	}
	if p[4] != 0x12 || p[5] != 0x34 || p[7] != 7 {
		t.Fatalf("id/seq %x", p)
	}
	if got := Checksum([]byte{0x00, 0x01, 0xf2, 0x03, 0xf4, 0xf5, 0xf6, 0xf7}); got != 0x220d {
		t.Fatalf("rfc1071 sum = %#x", got)
	}
}

func TestParseEchoReply(t *testing.T) {
	reply := BuildEchoRequest(0xbeef, 1, []byte("x"))
	reply[0] = 0
	reply[2], reply[3] = 0, 0
	cs := Checksum(reply)
	reply[2], reply[3] = byte(cs>>8), byte(cs)
	if id, seq, ok := ParseEchoReply(reply); !ok || id != 0xbeef || seq != 1 {
		t.Fatal("bare reply")
	}
	withIP := append([]byte{0x45, 0, 0, 0, 0, 0, 0, 0, 64, 1, 0, 0, 127, 0, 0, 1, 127, 0, 0, 1}, reply...)
	if id, _, ok := ParseEchoReply(withIP); !ok || id != 0xbeef {
		t.Fatal("ip header")
	}
	if _, _, ok := ParseEchoReply(BuildEchoRequest(1, 1, nil)); ok {
		t.Fatal("request is not a reply")
	}
	reply[8] ^= 0xff
	if _, _, ok := ParseEchoReply(reply); ok {
		t.Fatal("corrupt checksum")
	}
}

func TestResolveCapabilities(t *testing.T) {
	var warns int
	logf := func(string, ...any) { warns++ }
	if got := ResolveCapabilities(nil, nil, logf); !reflect.DeepEqual(got, []string{"http", "tcp", "dns"}) || warns != 0 {
		t.Fatalf("%v", got)
	}
	if got := ResolveCapabilities([]string{"http", "icmp"}, func() bool { return false }, logf); !reflect.DeepEqual(got, []string{"http"}) || warns != 1 {
		t.Fatalf("%v", got)
	}
	if got := ResolveCapabilities([]string{"icmp", "tcp"}, func() bool { return true }, logf); !reflect.DeepEqual(got, []string{"icmp", "tcp"}) {
		t.Fatalf("%v", got)
	}
}

func TestRunnerOnceEndToEnd(t *testing.T) {
	var posted []Result
	var inflight, peak int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/api/v1/probe/work" {
			_, _ = w.Write([]byte(`{"checks":[` + strings.TrimSuffix(strings.Repeat(`{"id":1,"type":"fake","target":"x","timeout_sec":1,"config":{}},`, 20), ",") + `]}`))
			return
		}
		var in struct{ Results []Result }
		_ = json.NewDecoder(r.Body).Decode(&in)
		posted = in.Results
		w.WriteHeader(202)
	}))
	defer srv.Close()
	r := &Runner{
		Client:       &Client{HubURL: srv.URL, Token: "t"},
		Capabilities: []string{"http"},
		Exec: func(_ context.Context, c Check) Result {
			n := atomic.AddInt32(&inflight, 1)
			for {
				p := atomic.LoadInt32(&peak)
				if n <= p || atomic.CompareAndSwapInt32(&peak, p, n) {
					break
				}
			}
			time.Sleep(20 * time.Millisecond)
			atomic.AddInt32(&inflight, -1)
			return Result{CheckID: c.ID, OK: true}
		},
	}
	if err := r.Once(context.Background()); err != nil {
		t.Fatal(err)
	}
	if len(posted) != 20 || peak > Workers || peak < 2 {
		t.Fatalf("posted=%d peak=%d", len(posted), peak)
	}
}
