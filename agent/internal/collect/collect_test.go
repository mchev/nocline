package collect

import (
	"math"
	"testing"
)

func TestReduce(t *testing.T) {
	vs := make([]float64, 100)
	for i := range vs {
		vs[i] = float64(100 - i) // non trié
	}
	m := Reduce("x", vs)
	if m.Min != 1 || m.Max != 100 || m.Avg != 50.5 || m.P95 != 95 {
		t.Fatalf("%+v", m)
	}
	one := Reduce("y", []float64{7})
	if one.Min != 7 || one.Max != 7 || one.Avg != 7 || one.P95 != 7 {
		t.Fatalf("%+v", one)
	}
}

func TestReducerFlush(t *testing.T) {
	r := NewReducer()
	r.Add("b", 1)
	r.Add("a", 2)
	r.Add("a", math.NaN())
	r.Add("a", 4)
	ms := r.Flush()
	if len(ms) != 2 || ms[0].Key != "a" || ms[0].Avg != 3 || ms[1].Key != "b" {
		t.Fatalf("%+v", ms)
	}
	if len(r.Flush()) != 0 {
		t.Fatal("reducer should be empty after flush")
	}
}

func TestParsers(t *testing.T) {
	a, err := parseCPUStat("cpu  100 0 100 700 100 0 0 0 0 0\ncpu0 1 1 1 1 1\n")
	if err != nil || a.total != 1000 || a.idle != 800 {
		t.Fatalf("%+v %v", a, err)
	}
	b := cpuTimes{total: 2000, idle: 1600}
	if v, ok := cpuUsagePct(a, b); !ok || v != 20 {
		t.Fatalf("%v %v", v, ok)
	}
	if _, ok := cpuUsagePct(a, a); ok {
		t.Fatal("no delta expected")
	}
	tot, av, err := parseMeminfo("MemTotal:  1000 kB\nMemFree: 1 kB\nMemAvailable: 250 kB\n")
	if err != nil || tot != 1000*1024 || av != 250*1024 {
		t.Fatal(tot, av, err)
	}
	if l, err := parseLoad1("0.52 0.40 0.30 1/200 123\n"); err != nil || l != 0.52 {
		t.Fatal(l, err)
	}
}
