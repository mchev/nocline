package probe

import (
	"context"
	"errors"
	"strings"
	"sync"
	"time"
)

// Workers est la taille du pool d'exécution.
const Workers = 8

// DefaultCapabilities quand la config n'en liste pas.
var DefaultCapabilities = []string{"http", "tcp", "dns"}

// ResolveCapabilities filtre la liste configurée ; icmp est retiré (avec warning) si icmpOK est faux.
func ResolveCapabilities(configured []string, icmpOK func() bool, logf func(string, ...any)) []string {
	if len(configured) == 0 {
		configured = DefaultCapabilities
	}
	seen := map[string]bool{}
	var out []string
	for _, c := range configured {
		c = strings.ToLower(strings.TrimSpace(c))
		if c == "" || seen[c] {
			continue
		}
		switch c {
		case "http", "tcp", "dns":
		case "icmp":
			if icmpOK == nil || !icmpOK() {
				logf("avertissement : capability icmp indisponible (socket raw refusée, voir NET_RAW), ignorée")
				continue
			}
		default:
			logf("avertissement : capability inconnue %q ignorée", c)
			continue
		}
		seen[c] = true
		out = append(out, c)
	}
	return out
}

// RunChecks exécute les checks via un pool borné et renvoie les résultats.
func RunChecks(ctx context.Context, checks []Check, exec func(context.Context, Check) Result) []Result {
	out := make([]Result, len(checks))
	jobs := make(chan int)
	var wg sync.WaitGroup
	n := Workers
	if len(checks) < n {
		n = len(checks)
	}
	for w := 0; w < n; w++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for i := range jobs {
				out[i] = exec(ctx, checks[i])
			}
		}()
	}
	for i := range checks {
		jobs <- i
	}
	close(jobs)
	wg.Wait()
	return out
}

// Runner est la boucle poll → exécution → push.
type Runner struct {
	Client       *Client
	Capabilities []string
	Interval     time.Duration
	Logf         func(string, ...any)
	Exec         func(context.Context, Check) Result // défaut : Execute
	Sleep        func(context.Context, time.Duration)
	AuthPause    time.Duration // défaut 5 min
}

func sleepCtx(ctx context.Context, d time.Duration) {
	t := time.NewTimer(d)
	defer t.Stop()
	select {
	case <-ctx.Done():
	case <-t.C:
	}
}

// Once effectue un cycle poll/exécution/push.
func (r *Runner) Once(ctx context.Context) error {
	checks, err := r.Client.FetchWork(ctx, r.Capabilities)
	if err != nil || len(checks) == 0 {
		return err
	}
	exec := r.Exec
	if exec == nil {
		exec = Execute
	}
	results := RunChecks(ctx, checks, exec)
	sleep := r.Sleep
	if sleep == nil {
		sleep = sleepCtx
	}
	return r.Client.PostResultsRetry(ctx, results, 4, time.Second, 30*time.Second, sleep)
}

// Run boucle jusqu'à l'annulation du contexte.
func (r *Runner) Run(ctx context.Context) {
	sleep := r.Sleep
	if sleep == nil {
		sleep = sleepCtx
	}
	pause := r.AuthPause
	if pause <= 0 {
		pause = 5 * time.Minute
	}
	for ctx.Err() == nil {
		wait := r.Interval
		if err := r.Once(ctx); err != nil && ctx.Err() == nil {
			if errors.Is(err, ErrUnauthorized) {
				r.Logf("%v : nouvel essai dans %s", err, pause)
				wait = pause
			} else {
				r.Logf("probe: %v", err)
			}
		}
		sleep(ctx, wait)
	}
}
