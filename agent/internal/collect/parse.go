package collect

import (
	"bufio"
	"errors"
	"strconv"
	"strings"
)

// cpuTimes : total et idle (idle+iowait) en jiffies depuis la ligne « cpu » de /proc/stat.
type cpuTimes struct{ total, idle uint64 }

func parseCPUStat(data string) (cpuTimes, error) {
	for _, line := range strings.Split(data, "\n") {
		f := strings.Fields(line)
		if len(f) < 5 || f[0] != "cpu" {
			continue
		}
		var t cpuTimes
		for i, s := range f[1:] {
			if i >= 8 { // guest déjà inclus dans user/nice
				break
			}
			n, err := strconv.ParseUint(s, 10, 64)
			if err != nil {
				return cpuTimes{}, err
			}
			t.total += n
			if i == 3 || i == 4 {
				t.idle += n
			}
		}
		return t, nil
	}
	return cpuTimes{}, errors.New("collect: ligne cpu absente")
}

// cpuUsagePct calcule l'usage entre deux relevés ; ok=false si pas de delta.
func cpuUsagePct(prev, cur cpuTimes) (float64, bool) {
	if cur.total <= prev.total || cur.idle < prev.idle {
		return 0, false
	}
	dt := float64(cur.total - prev.total)
	di := float64(cur.idle - prev.idle)
	return clampPct((dt - di) / dt * 100), true
}

func parseMeminfo(data string) (total, available uint64, err error) {
	sc := bufio.NewScanner(strings.NewReader(data))
	var gotT, gotA bool
	for sc.Scan() {
		f := strings.Fields(sc.Text())
		if len(f) < 2 {
			continue
		}
		n, perr := strconv.ParseUint(f[1], 10, 64)
		if perr != nil {
			continue
		}
		switch f[0] {
		case "MemTotal:":
			total, gotT = n*1024, true
		case "MemAvailable:":
			available, gotA = n*1024, true
		}
	}
	if !gotT || !gotA || total == 0 {
		return 0, 0, errors.New("collect: MemTotal/MemAvailable absents")
	}
	return total, available, nil
}

func parseLoad1(data string) (float64, error) {
	f := strings.Fields(data)
	if len(f) < 1 {
		return 0, errors.New("collect: loadavg vide")
	}
	return strconv.ParseFloat(f[0], 64)
}

func clampPct(v float64) float64 {
	if v < 0 {
		return 0
	}
	if v > 100 {
		return 100
	}
	return v
}
