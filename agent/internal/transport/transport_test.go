package transport

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"
	"time"

	"github.com/phare/phare/agent/internal/collect"
)

func batch() Batch {
	return Batch{AgentVersion: "t", WindowSec: 30, Metrics: []collect.Metric{
		{Key: "cpu.usage_pct", Min: 1, Max: 3, Avg: 2, P95: 3},
		{Key: "BAD KEY", Min: 1, Max: 1, Avg: 1, P95: 1},
	}}
}

func noSleep(context.Context, time.Duration) {}

func TestPushPayloadAndAuth(t *testing.T) {
	var got Batch
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != "POST" || r.URL.Path != "/api/v1/ingest" {
			t.Errorf("%s %s", r.Method, r.URL.Path)
		}
		if r.Header.Get("Authorization") != "Bearer secret" {
			t.Errorf("auth = %q", r.Header.Get("Authorization"))
		}
		_ = json.NewDecoder(r.Body).Decode(&got)
		w.WriteHeader(202)
	}))
	defer srv.Close()
	s := &Sender{Client: &Client{HubURL: srv.URL, Token: "secret"}, Sleep: noSleep}
	if err := s.Send(context.Background(), batch()); err != nil {
		t.Fatal(err)
	}
	if got.AgentVersion != "t" || got.WindowSec != 30 || len(got.Metrics) != 1 || got.Metrics[0].Key != "cpu.usage_pct" || got.Metrics[0].P95 != 3 {
		t.Fatalf("%+v", got)
	}
	if s.Pending() != 0 {
		t.Fatal("queue should be empty")
	}
}

func TestRetryThenBufferThenFlush(t *testing.T) {
	var calls, fail atomic.Int32
	fail.Store(1)
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls.Add(1)
		if fail.Load() == 1 {
			w.WriteHeader(503)
			return
		}
		w.WriteHeader(202)
	}))
	defer srv.Close()
	s := &Sender{Client: &Client{HubURL: srv.URL, Token: "x"}, Sleep: noSleep, Attempts: 3}
	if err := s.Send(context.Background(), batch()); err == nil {
		t.Fatal("expected error")
	}
	if calls.Load() != 3 || s.Pending() != 1 {
		t.Fatalf("calls=%d pending=%d", calls.Load(), s.Pending())
	}
	fail.Store(0)
	if err := s.Send(context.Background(), batch()); err != nil {
		t.Fatal(err)
	}
	if s.Pending() != 0 || calls.Load() != 5 {
		t.Fatalf("calls=%d pending=%d", calls.Load(), s.Pending())
	}
}

func TestUnauthorizedPauses(t *testing.T) {
	var calls atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls.Add(1)
		w.WriteHeader(401)
	}))
	defer srv.Close()
	now := time.Now()
	s := &Sender{Client: &Client{HubURL: srv.URL, Token: "x"}, Sleep: noSleep, Now: func() time.Time { return now }}
	if err := s.Send(context.Background(), batch()); err != ErrUnauthorized {
		t.Fatal(err)
	}
	_ = s.Send(context.Background(), batch())
	if calls.Load() != 1 {
		t.Fatalf("calls=%d, want 1 (paused)", calls.Load())
	}
	now = now.Add(6 * time.Minute)
	_ = s.Send(context.Background(), batch())
	if calls.Load() != 2 {
		t.Fatalf("calls=%d, want 2", calls.Load())
	}
}

func TestPermanentErrorDropsBatch(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { w.WriteHeader(422) }))
	defer srv.Close()
	s := &Sender{Client: &Client{HubURL: srv.URL, Token: "x"}, Sleep: noSleep}
	if err := s.Send(context.Background(), batch()); err != nil {
		t.Fatal(err)
	}
	if s.Pending() != 0 {
		t.Fatal("batch should be dropped")
	}
}

func TestBufferBounded(t *testing.T) {
	s := &Sender{}
	for i := 0; i < maxBufferBatches+50; i++ {
		s.enqueue(batch())
	}
	if s.Pending() != maxBufferBatches {
		t.Fatalf("pending=%d", s.Pending())
	}
}

func TestSanitizeCapsAt64(t *testing.T) {
	var ms []collect.Metric
	for i := 0; i < 100; i++ {
		ms = append(ms, collect.Metric{Key: "k"})
	}
	if len(Sanitize(ms)) != MaxMetrics {
		t.Fatal("cap")
	}
}
