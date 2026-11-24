//go:build !linux

package collect

// Collector est un stub : la collecte host n'est supportée que sur Linux.
type Collector struct{}

func NewCollector() *Collector { return &Collector{} }

// Supported indique si la collecte est réelle sur cet OS.
func Supported() bool { return false }

// Sample ne renvoie rien hors Linux.
func (c *Collector) Sample() map[string]float64 { return map[string]float64{} }
