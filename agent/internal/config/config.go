// Package config charge et écrit /etc/phare-agent/config.yaml (sous-ensemble YAML plat, sans dépendance).
package config

import (
	"bufio"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

const (
	DefaultPath        = "/etc/phare-agent/config.yaml"
	DefaultIntervalSec = 30
	// DefaultProbeIntervalSec : période de poll des probes.
	DefaultProbeIntervalSec = 15
	MinIntervalSec          = 5
)

// Config est la configuration de l'agent.
type Config struct {
	HubURL      string
	Role        string
	Token       string
	IntervalSec int
	// AutoUpdate : mise à jour auto quand le hub propose agent_update (vérif SHA256).
	AutoUpdate bool
	// Capabilities (probe) : types de checks exécutables (http, tcp, dns, icmp).
	Capabilities []string
}

// Parse lit un YAML plat « clé: valeur ». Les clés inconnues, listes et lignes indentées sont ignorées.
func Parse(data []byte) (Config, error) {
	c := Config{IntervalSec: DefaultIntervalSec}
	sc := bufio.NewScanner(strings.NewReader(string(data)))
	intervalSet, inCaps := false, false
	for sc.Scan() {
		line := sc.Text()
		if inCaps {
			if t := strings.TrimSpace(line); strings.HasPrefix(t, "- ") || t == "-" {
				if v := cleanValue(strings.TrimPrefix(t, "-")); v != "" {
					c.Capabilities = append(c.Capabilities, v)
				}
				continue
			} else if t == "" || t[0] == '#' {
				continue
			}
			inCaps = false
		}
		if line == "" || line[0] == ' ' || line[0] == '\t' || line[0] == '#' || line[0] == '-' {
			continue
		}
		key, val, ok := strings.Cut(line, ":")
		if !ok {
			continue
		}
		val = cleanValue(val)
		switch strings.TrimSpace(key) {
		case "hub_url":
			c.HubURL = val
		case "role":
			c.Role = val
		case "token":
			c.Token = val
		case "interval_sec":
			n, err := strconv.Atoi(val)
			if err != nil {
				return c, fmt.Errorf("config: interval_sec invalide %q", val)
			}
			c.IntervalSec = n
			intervalSet = true
		case "auto_update":
			c.AutoUpdate = val == "true" || val == "1" || val == "yes"
		case "capabilities":
			if strings.HasPrefix(val, "[") {
				for _, p := range strings.Split(strings.Trim(val, "[] "), ",") {
					if p = cleanValue(p); p != "" {
						c.Capabilities = append(c.Capabilities, p)
					}
				}
			} else if val == "" {
				inCaps = true
			}
		}
	}
	if !intervalSet && c.Role == "probe" {
		c.IntervalSec = DefaultProbeIntervalSec
	}
	return c, sc.Err()
}

func cleanValue(v string) string {
	v = strings.TrimSpace(v)
	if len(v) > 0 && (v[0] == '"' || v[0] == '\'') {
		q := v[0]
		if end := strings.IndexByte(v[1:], q); end >= 0 {
			return v[1 : 1+end]
		}
		return strings.Trim(v, `"'`)
	}
	if i := strings.Index(v, " #"); i >= 0 {
		v = v[:i]
	}
	return strings.TrimSpace(v)
}

// Load lit et parse le fichier de config.
func Load(path string) (Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return Config{}, err
	}
	return Parse(data)
}

// Validate vérifie les champs requis (rôles host et probe).
func (c Config) Validate() error {
	if c.HubURL == "" {
		return errors.New("config: hub_url requis")
	}
	if c.Token == "" {
		return errors.New("config: token requis")
	}
	switch c.Role {
	case "host", "probe":
	default:
		return fmt.Errorf("config: role invalide %q (host ou probe attendu)", c.Role)
	}
	if c.IntervalSec < MinIntervalSec {
		return fmt.Errorf("config: interval_sec doit être >= %d", MinIntervalSec)
	}
	return nil
}

// Save écrit le fichier de façon atomique avec le mode 0600.
func Save(path string, c Config) error {
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return err
	}
	body := fmt.Sprintf("hub_url: %s\nrole: %s\ntoken: \"%s\"\ninterval_sec: %d\n", c.HubURL, c.Role, c.Token, c.IntervalSec)
	if c.AutoUpdate {
		body += "auto_update: true\n"
	}
	if len(c.Capabilities) > 0 {
		body += "capabilities: [" + strings.Join(c.Capabilities, ", ") + "]\n"
	}
	tmp, err := os.CreateTemp(filepath.Dir(path), ".config-*.tmp")
	if err != nil {
		return err
	}
	defer os.Remove(tmp.Name())
	if err := tmp.Chmod(0o600); err != nil {
		tmp.Close()
		return err
	}
	if _, err := tmp.WriteString(body); err != nil {
		tmp.Close()
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}
	return os.Rename(tmp.Name(), path)
}
