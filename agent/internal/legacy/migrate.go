// Package legacy migrates installs from nocline-agent to nocline-agent (binary, config dir, systemd).
package legacy

import (
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
)

const (
	LegacyBin        = "/usr/local/bin/phare-agent"
	NewBin           = "/usr/local/bin/nocline-agent"
	LegacyConfigDir  = "/etc/phare-agent"
	NewConfigDir     = "/etc/nocline-agent"
	LegacyConfigFile = LegacyConfigDir + "/config.yaml"
	NewConfigFile    = NewConfigDir + "/config.yaml"
	LegacyService    = "phare-agent"
	NewService       = "nocline-agent"
)

var systemdUnit = []byte(`[Unit]
Description=Nocline monitoring agent
After=network-online.target
Wants=network-online.target

[Service]
ExecStart=/usr/local/bin/nocline-agent -config /etc/nocline-agent/config.yaml
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
`)

// NeedsMigration reports whether legacy phare paths are still in use.
func NeedsMigration() bool {
	if _, err := os.Stat(LegacyBin); err == nil {
		return true
	}
	if _, err := os.Stat(LegacyConfigDir); err == nil {
		if _, err := os.Stat(NewConfigDir); errors.Is(err, os.ErrNotExist) {
			return true
		}
	}
	if systemdLegacyActive() {
		return true
	}

	return false
}

func systemdLegacyActive() bool {
	if _, err := exec.LookPath("systemctl"); err != nil {
		return false
	}
	if out, err := exec.Command("systemctl", "is-enabled", LegacyService).CombinedOutput(); err == nil {
		s := string(out)
		return s == "enabled\n" || s == "enabled"
	}
	if out, err := exec.Command("systemctl", "is-active", LegacyService).CombinedOutput(); err == nil {
		s := string(out)
		return s == "active\n" || s == "active"
	}

	return false
}

// Migrate renames binary and config paths and switches systemd from nocline-agent to nocline-agent.
// Idempotent when already on nocline layout.
func Migrate(logf func(string, ...any)) error {
	if logf == nil {
		logf = func(string, ...any) {}
	}
	if !NeedsMigration() {
		return nil
	}

	logf("migration phare-agent → nocline-agent…")

	if err := migrateConfigDir(logf); err != nil {
		return err
	}
	if err := migrateBinary(logf); err != nil {
		return err
	}
	if err := migrateSystemd(logf); err != nil {
		return err
	}

	logf("migration terminée (nocline-agent)")
	return nil
}

func migrateConfigDir(logf func(string, ...any)) error {
	if _, err := os.Stat(NewConfigFile); err == nil {
		return nil
	}
	if _, err := os.Stat(LegacyConfigFile); errors.Is(err, os.ErrNotExist) {
		return nil
	}
	if err := os.MkdirAll(NewConfigDir, 0o755); err != nil {
		return fmt.Errorf("legacy: mkdir %s: %w", NewConfigDir, err)
	}
	if err := os.Rename(LegacyConfigFile, NewConfigFile); err != nil {
		return fmt.Errorf("legacy: move config: %w", err)
	}
	_ = os.Remove(LegacyConfigDir)
	logf("config déplacée vers %s", NewConfigFile)

	return nil
}

func migrateBinary(logf func(string, ...any)) error {
	if _, err := os.Stat(NewBin); err == nil {
		_ = os.Remove(LegacyBin)
		return nil
	}
	if _, err := os.Stat(LegacyBin); errors.Is(err, os.ErrNotExist) {
		return nil
	}
	if err := os.Rename(LegacyBin, NewBin); err != nil {
		return fmt.Errorf("legacy: rename binary: %w", err)
	}
	logf("binaire déplacé vers %s", NewBin)

	return nil
}

func migrateSystemd(logf func(string, ...any)) error {
	if _, err := exec.LookPath("systemctl"); err != nil {
		logf("systemd absent — démarrez %s à la main si besoin", NewBin)
		return nil
	}
	if !systemdLegacyActive() && !unitFileExists(LegacyService) {
		return writeNoclineUnitIfMissing(logf)
	}

	unitPath := "/etc/systemd/system/" + NewService + ".service"
	if err := os.WriteFile(unitPath, systemdUnit, 0o644); err != nil {
		return fmt.Errorf("legacy: write unit: %w", err)
	}

	_ = exec.Command("systemctl", "stop", LegacyService).Run()
	_ = exec.Command("systemctl", "disable", LegacyService).Run()
	_ = os.Remove("/etc/systemd/system/" + LegacyService + ".service")

	if out, err := exec.Command("systemctl", "daemon-reload").CombinedOutput(); err != nil {
		return fmt.Errorf("legacy: daemon-reload: %w (%s)", err, out)
	}
	_ = exec.Command("systemctl", "enable", NewService).Run()
	if out, err := exec.Command("systemctl", "restart", NewService).CombinedOutput(); err != nil {
		return fmt.Errorf("legacy: restart %s: %w (%s)", NewService, err, out)
	}
	logf("service systemd basculé vers %s", NewService)

	return nil
}

func unitFileExists(name string) bool {
	_, err := os.Stat("/etc/systemd/system/" + name + ".service")
	return err == nil
}

func writeNoclineUnitIfMissing(logf func(string, ...any)) error {
	unitPath := "/etc/systemd/system/" + NewService + ".service"
	if _, err := os.Stat(unitPath); err == nil {
		return nil
	}
	if err := os.WriteFile(unitPath, systemdUnit, 0o644); err != nil {
		return err
	}
	_ = exec.Command("systemctl", "daemon-reload").Run()
	_ = exec.Command("systemctl", "enable", NewService).Run()
	logf("unité %s créée", NewService)

	return nil
}

// ResolveConfigPath prefers the nocline config path, then legacy phare path.
func ResolveConfigPath(requested, defaultPath, legacyPath string) string {
	if requested != defaultPath && requested != legacyPath {
		return requested
	}
	if _, err := os.Stat(defaultPath); err == nil {
		return defaultPath
	}
	if _, err := os.Stat(legacyPath); err == nil {
		return legacyPath
	}

	return defaultPath
}

// ExecutablePath returns the current binary path, preferring nocline then legacy install locations.
func ExecutablePath() (string, error) {
	if st, err := os.Stat(NewBin); err == nil && !st.IsDir() {
		return filepath.EvalSymlinks(NewBin)
	}
	exe, err := os.Executable()
	if err != nil {
		return "", err
	}

	return filepath.EvalSymlinks(exe)
}