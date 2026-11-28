//go:build linux

package meta

import (
	"os"
)

// Collect gathers host facts (Linux).
func Collect() Snapshot {
	s := Snapshot{
		Hostname: hostname(),
		IPv4s:    localIPv4s(),
		Distro:   distroPretty(),
		Kernel:   kernelRelease(),
	}
	if cloud, ok := detectCloud(); ok {
		s.Provider = cloud.provider
		s.ProviderRegion = cloud.region
		s.ProviderSource = "agent"
		if cloud.publicIPv4 != "" {
			s.PublicIPv4 = cloud.publicIPv4
		}
	}
	if s.PublicIPv4 == "" && len(s.IPv4s) > 0 {
		s.PublicIPv4 = pickLikelyPublic(s.IPv4s)
	}
	required, since := RebootRequired()
	s.RebootRequired = required
	if required {
		s.RebootRequiredSince = since
	}
	return s
}

func hostname() string {
	h, err := os.Hostname()
	if err != nil {
		return ""
	}
	return h
}