// Package collect échantillonne les métriques host et les réduit en agrégats par fenêtre.
package collect

import (
	"math"
	"sort"
)

// Metric est un agrégat envoyé au hub (nombres uniquement).
type Metric struct {
	Key string  `json:"key"`
	Min float64 `json:"min"`
	Max float64 `json:"max"`
	Avg float64 `json:"avg"`
	P95 float64 `json:"p95"`
}

// Reducer accumule des échantillons par clé le temps d'une fenêtre.
type Reducer struct {
	values map[string][]float64
}

func NewReducer() *Reducer {
	return &Reducer{values: map[string][]float64{}}
}

// Add ignore les NaN/Inf.
func (r *Reducer) Add(key string, v float64) {
	if math.IsNaN(v) || math.IsInf(v, 0) {
		return
	}
	r.values[key] = append(r.values[key], v)
}

// Flush réduit puis vide le reducer. Résultat trié par clé.
func (r *Reducer) Flush() []Metric {
	out := make([]Metric, 0, len(r.values))
	for k, vs := range r.values {
		out = append(out, Reduce(k, vs))
	}
	sort.Slice(out, func(i, j int) bool { return out[i].Key < out[j].Key })
	r.values = map[string][]float64{}
	return out
}

// Reduce calcule min/max/avg/p95 (nearest-rank). vs doit être non vide.
func Reduce(key string, vs []float64) Metric {
	s := append([]float64(nil), vs...)
	sort.Float64s(s)
	sum := 0.0
	for _, v := range s {
		sum += v
	}
	idx := int(math.Ceil(0.95*float64(len(s)))) - 1
	if idx < 0 {
		idx = 0
	}
	return Metric{Key: key, Min: s[0], Max: s[len(s)-1], Avg: sum / float64(len(s)), P95: s[idx]}
}
