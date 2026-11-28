//go:build linux

package meta

import (
	"context"
	"os"
	"os/exec"
	"time"
)

var rebootMarkerPath = "/var/run/reboot-required"

// RebootRequired reports whether the OS asked for a reboot (kernel update, etc.).
func RebootRequired() (required bool, sinceRFC3339 string) {
	if st, err := os.Stat(rebootMarkerPath); err == nil {
		return true, st.ModTime().UTC().Format(time.RFC3339)
	}

	path := "/usr/bin/needs-restarting"
	if _, err := os.Stat(path); err != nil {
		return false, ""
	}

	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()

	err := exec.CommandContext(ctx, path, "-r").Run()
	if err == nil {
		return false, ""
	}

	if exit, ok := err.(*exec.ExitError); ok && exit.ExitCode() == 1 {
		return true, ""
	}

	return false, ""
}