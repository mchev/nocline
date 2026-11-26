//go:build linux

package meta

import (
	"bytes"
	"context"
	"os/exec"
	"regexp"
	"strings"
	"time"
)

const stackProbeBudget = 4 * time.Second

var (
	versionToken = regexp.MustCompile(`[0-9]+(?:\.[0-9A-Za-z+~.-]+){0,5}`)
	safeVersion  = regexp.MustCompile(`^[0-9A-Za-z.+~\-]+$`)
)

type stackProbe struct {
	key     string
	binary  string
	args    []string
	stderr  bool
}

// CollectStack runs read-only version probes on fixed binaries (no shell, no DB credentials).
// Intended at most once per hour alongside host_meta.
func CollectStack() map[string]string {
	ctx, cancel := context.WithTimeout(context.Background(), stackProbeBudget)
	defer cancel()

	probes := []stackProbe{
		{key: "php", binary: "php", args: []string{"-r", "echo PHP_VERSION;"}},
		{key: "node", binary: "node", args: []string{"-p", "process.versions.node"}},
		{key: "mysql", binary: "mysql", args: []string{"--version"}},
		{key: "mariadb", binary: "mariadb", args: []string{"--version"}},
		{key: "postgresql", binary: "psql", args: []string{"--version"}},
		{key: "redis", binary: "redis-server", args: []string{"--version"}},
		{key: "nginx", binary: "nginx", args: []string{"-v"}, stderr: true},
		{key: "docker", binary: "docker", args: []string{"version", "--format", "{{.Server.Version}}"}},
	}

	out := make(map[string]string)

	for _, p := range probes {
		if v := runStackProbe(ctx, p); v != "" {
			out[p.key] = v
		}
	}

	if len(out) == 0 {
		return nil
	}

	return out
}

func runStackProbe(ctx context.Context, p stackProbe) string {
	path, err := exec.LookPath(p.binary)
	if err != nil {
		return ""
	}

	per, cancel := context.WithTimeout(ctx, 900*time.Millisecond)
	defer cancel()

	cmd := exec.CommandContext(per, path, p.args...)
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	if err := cmd.Run(); err != nil {
		if p.stderr && stderr.Len() > 0 {
			return sanitizeVersion(stderr.String())
		}

		return ""
	}

	raw := stdout.String()
	if p.stderr && raw == "" {
		raw = stderr.String()
	}

	return sanitizeVersion(raw)
}

func sanitizeVersion(raw string) string {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return ""
	}

	if m := versionToken.FindString(raw); m != "" {
		raw = m
	}

	if len(raw) > 32 {
		raw = raw[:32]
	}

	if !safeVersion.MatchString(raw) {
		return ""
	}

	return raw
}