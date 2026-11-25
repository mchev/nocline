//go:build linux

package collect

import (
	"os"
	"syscall"
)

// Collector lit /proc et statfs ; garde le dernier relevé CPU pour le delta.
type Collector struct {
	prev    cpuTimes
	hasPrev bool
}

func NewCollector() *Collector { return &Collector{} }

// Supported indique si la collecte est réelle sur cet OS.
func Supported() bool { return true }

// Sample renvoie les valeurs instantanées disponibles (les erreurs partielles sont ignorées).
func (c *Collector) Sample() map[string]float64 {
	out := map[string]float64{}

	if b, err := os.ReadFile("/proc/stat"); err == nil {
		if cur, err := parseCPUStat(string(b)); err == nil {
			if c.hasPrev {
				if v, ok := cpuUsagePct(c.prev, cur); ok {
					out["cpu.usage_pct"] = v
				}
			}
			c.prev, c.hasPrev = cur, true
		}
	}
	if b, err := os.ReadFile("/proc/meminfo"); err == nil {
		if total, avail, err := parseMeminfo(string(b)); err == nil {
			if avail > total {
				avail = total
			}
			used := total - avail
			out["mem.used_bytes"] = float64(used)
			out["mem.used_pct"] = float64(used) / float64(total) * 100
		}
	}
	var st syscall.Statfs_t
	if err := syscall.Statfs("/", &st); err == nil {
		bsize := uint64(st.Bsize)
		if bsize == 0 {
			bsize = 1
		}
		used := uint64(st.Blocks) - uint64(st.Bfree)
		denom := used + uint64(st.Bavail) // même formule que df
		if denom > 0 {
			out["disk.used_pct"] = float64(used) / float64(denom) * 100
		}
		out["disk.used_bytes"] = float64(used * bsize)
		out["disk.total_bytes"] = float64(denom * bsize)
	}
	if b, err := os.ReadFile("/proc/loadavg"); err == nil {
		if v, err := parseLoad1(string(b)); err == nil {
			out["load.1m"] = v
		}
	}
	return out
}
