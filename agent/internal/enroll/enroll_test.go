package enroll

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"runtime"
	"testing"
)

func TestEnrollOK(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != "POST" || r.URL.Path != "/api/v1/enroll" {
			t.Errorf("%s %s", r.Method, r.URL.Path)
		}
		var in map[string]string
		_ = json.NewDecoder(r.Body).Decode(&in)
		if in["token"] != "enr" || in["agent_version"] != "1.2.3" || in["os"] != runtime.GOOS {
			t.Errorf("payload %v", in)
		}
		w.WriteHeader(201)
		_, _ = w.Write([]byte(`{"host_id":42,"role":"probe","token":"perm"}`))
	}))
	defer srv.Close()
	res, err := Enroll(context.Background(), nil, srv.URL+"/", "enr", "1.2.3")
	if err != nil || res.Token != "perm" || res.HostID != "42" || res.Role != "probe" {
		t.Fatalf("%+v %v", res, err)
	}
}

func TestEnrollRejected(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(422)
	}))
	defer srv.Close()
	if _, err := Enroll(context.Background(), nil, srv.URL, "bad", "v"); err == nil {
		t.Fatal("expected error")
	}
}
