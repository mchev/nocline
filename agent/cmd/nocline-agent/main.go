// nocline-agent — role host (push métriques) ou probe (pull checks, push résultats) (voir agent/README.md).
package main

import (
	"context"
	"flag"
	"fmt"
	"log"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/nocline/nocline/agent/internal/collect"
	"github.com/nocline/nocline/agent/internal/config"
	"github.com/nocline/nocline/agent/internal/enroll"
	"github.com/nocline/nocline/agent/internal/legacy"
	"github.com/nocline/nocline/agent/internal/meta"
	"github.com/nocline/nocline/agent/internal/probe"
	"github.com/nocline/nocline/agent/internal/transport"
	"github.com/nocline/nocline/agent/internal/update"
)

var version = "0.2.0"

const (
	sampleEvery   = 5 * time.Second
	hostMetaEvery = time.Hour
)

func main() {
	args := os.Args[1:]
	if len(args) > 0 && args[0] == "enroll" {
		os.Exit(runEnroll(args[1:]))
	}
	os.Exit(runAgent(args))
}

func runEnroll(args []string) int {
	fs := flag.NewFlagSet("enroll", flag.ExitOnError)
	hub := fs.String("hub", "", "URL du hub (ex. https://ops.example.com)")
	token := fs.String("token", "", "token d'enrôlement (usage unique)")
	path := fs.String("config", config.DefaultPath, "chemin du fichier de config")
	_ = fs.Parse(args)
	if *hub == "" || *token == "" {
		fmt.Fprintln(os.Stderr, "usage: nocline-agent enroll --hub URL --token TOKEN [--config path]")
		return 2
	}
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	res, err := enroll.Enroll(ctx, nil, *hub, *token, version)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	cfg := config.Config{
		HubURL: *hub, Role: "host", Token: res.Token, IntervalSec: config.DefaultIntervalSec,
		AutoUpdate: true,
	}
	if res.Role == "probe" {
		cfg.Role = "probe"
		cfg.IntervalSec = config.DefaultProbeIntervalSec
	}
	if err := config.Save(*path, cfg); err != nil {
		fmt.Fprintln(os.Stderr, "écriture config:", err)
		return 1
	}
	fmt.Printf("enrôlé (host_id=%s), config écrite dans %s\n", res.HostID, *path)
	return 0
}

func runAgent(args []string) int {
	fs := flag.NewFlagSet("nocline-agent", flag.ExitOnError)
	configFlag := fs.String("config", config.DefaultPath, "chemin du fichier de config")
	showVersion := fs.Bool("v", false, "affiche la version")
	fs.BoolVar(showVersion, "version", false, "affiche la version")
	_ = fs.Parse(args)
	if *showVersion {
		fmt.Println("nocline-agent", version)
		return 0
	}

	if legacy.NeedsMigration() {
		if err := legacy.Migrate(log.Printf); err != nil {
			log.Println("migration:", err)
		}
	}

	configPath := legacy.ResolveConfigPath(*configFlag, config.DefaultPath, config.LegacyDefaultPath)
	cfg, err := config.Load(configPath)
	if err == nil {
		err = cfg.Validate()
	}
	if err != nil {
		log.Println(err)
		return 1
	}
	if cfg.Role == "probe" {
		return runProbe(cfg)
	}
	if !collect.Supported() {
		log.Println("avertissement : collecte host non supportée sur cet OS (Linux uniquement), aucune métrique")
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	sender := &transport.Sender{
		Client: &transport.Client{HubURL: cfg.HubURL, Token: cfg.Token},
		Logf:   log.Printf,
		OnAccepted: func(ctx context.Context, offer *update.Offer) {
			update.MaybeApply(ctx, offer, update.Settings{
				AutoUpdate: cfg.AutoUpdate,
				NowVersion: version,
				Logf:       log.Printf,
			})
		},
	}
	batches := make(chan transport.Batch, 4)
	done := make(chan struct{})
	go func() {
		defer close(done)
		for b := range batches {
			if err := sender.Send(ctx, b); err != nil {
				log.Println("push:", err)
			}
		}
	}()

	collectLoop(ctx, cfg, batches)
	close(batches)
	<-done
	return 0
}

func runProbe(cfg config.Config) int {
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()
	caps := probe.ResolveCapabilities(cfg.Capabilities, probe.ICMPAvailable, log.Printf)
	log.Printf("probe démarrée (capabilities=%v, interval=%ds)", caps, cfg.IntervalSec)
	r := &probe.Runner{
		Client:       &probe.Client{HubURL: cfg.HubURL, Token: cfg.Token},
		Capabilities: caps,
		Interval:     time.Duration(cfg.IntervalSec) * time.Second,
		Logf:         log.Printf,
	}
	r.Run(ctx)
	return 0
}

func collectLoop(ctx context.Context, cfg config.Config, out chan<- transport.Batch) {
	interval := time.Duration(cfg.IntervalSec) * time.Second
	step := sampleEvery
	if step > interval {
		step = interval
	}
	col := collect.NewCollector()
	red := collect.NewReducer()
	col.Sample() // amorce le delta CPU
	windowStart := time.Now()
	lastMeta := time.Time{}
	tick := time.NewTicker(step)
	defer tick.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case now := <-tick.C:
			for k, v := range col.Sample() {
				red.Add(k, v)
			}
			if now.Sub(windowStart) < interval-step/2 {
				continue
			}
			ms := red.Flush()
			sec := int(now.Sub(windowStart).Round(time.Second) / time.Second)
			windowStart = now
			if len(ms) == 0 {
				continue
			}
			b := transport.Batch{AgentVersion: version, WindowSec: sec, Metrics: ms}
			if lastMeta.IsZero() || now.Sub(lastMeta) >= hostMetaEvery {
				snap := meta.Collect()
				b.HostMeta = &snap
				lastMeta = now
			}
			select {
			case out <- b:
			default:
				log.Println("envoi en retard : lot abandonné")
			}
		}
	}
}
