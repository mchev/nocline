package config

import (
	"os"
	"path/filepath"
	"reflect"
	"testing"
)

func TestRoundTrip(t *testing.T) {
	path := filepath.Join(t.TempDir(), "sub", "config.yaml")
	in := Config{HubURL: "https://ops.example.com", Role: "host", Token: "tok-en", IntervalSec: 15}
	if err := Save(path, in); err != nil {
		t.Fatal(err)
	}
	st, err := os.Stat(path)
	if err != nil {
		t.Fatal(err)
	}
	if st.Mode().Perm() != 0o600 {
		t.Fatalf("perm = %o", st.Mode().Perm())
	}
	out, err := Load(path)
	if err != nil {
		t.Fatal(err)
	}
	if !reflect.DeepEqual(out, in) {
		t.Fatalf("got %+v want %+v", out, in)
	}
}

func TestParseDefaultsAndComments(t *testing.T) {
	c, err := Parse([]byte("# c\nhub_url: https://x # inline\nrole: host\ntoken: 'abc'\nmetrics: [cpu, memory]\n"))
	if err != nil {
		t.Fatal(err)
	}
	if c.HubURL != "https://x" || c.Token != "abc" || c.IntervalSec != 30 {
		t.Fatalf("%+v", c)
	}
	if err := c.Validate(); err != nil {
		t.Fatal(err)
	}
}

func TestValidate(t *testing.T) {
	if err := (Config{HubURL: "h", Token: "t", Role: "probe", IntervalSec: 15}).Validate(); err != nil {
		t.Fatal(err)
	}
	if (Config{HubURL: "h", Token: "t", Role: "x", IntervalSec: 30}).Validate() == nil {
		t.Fatal("unknown role should fail")
	}
	if (Config{Role: "host", IntervalSec: 30}).Validate() == nil {
		t.Fatal("missing hub/token should fail")
	}
}

func TestProbeConfig(t *testing.T) {
	c, err := Parse([]byte("hub_url: https://x\nrole: probe\ntoken: t\ncapabilities: [http, icmp]\n"))
	if err != nil || c.IntervalSec != 15 || !reflect.DeepEqual(c.Capabilities, []string{"http", "icmp"}) {
		t.Fatalf("%+v %v", c, err)
	}
	c, _ = Parse([]byte("role: probe\ncapabilities:\n  - tcp\n  - dns\ninterval_sec: 20\n"))
	if c.IntervalSec != 20 || !reflect.DeepEqual(c.Capabilities, []string{"tcp", "dns"}) {
		t.Fatalf("%+v", c)
	}
	if err := c.Validate(); err == nil {
		t.Fatal("hub/token missing")
	}
}
