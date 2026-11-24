//go:build linux

package meta

import (
	"os"
	"strings"
	"syscall"
)

func distroPretty() string {
	b, err := os.ReadFile("/etc/os-release")
	if err != nil {
		return ""
	}
	for _, line := range strings.Split(string(b), "\n") {
		if strings.HasPrefix(line, "PRETTY_NAME=") {
			return strings.Trim(strings.TrimPrefix(line, "PRETTY_NAME="), `"`)
		}
	}
	return ""
}

func kernelRelease() string {
	var uname syscall.Utsname
	if err := syscall.Uname(&uname); err != nil {
		return ""
	}
	return charsToString(uname.Release[:])
}

func charsToString(ca []int8) string {
	var b strings.Builder
	for _, c := range ca {
		if c == 0 {
			break
		}
		b.WriteByte(byte(c))
	}
	return b.String()
}