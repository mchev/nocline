//go:build linux

package meta

import (
	"net"
)

func localIPv4s() []string {
	ifaces, err := net.Interfaces()
	if err != nil {
		return nil
	}
	var out []string
	for _, iface := range ifaces {
		if iface.Flags&net.FlagUp == 0 || iface.Flags&net.FlagLoopback != 0 {
			continue
		}
		addrs, err := iface.Addrs()
		if err != nil {
			continue
		}
		for _, a := range addrs {
			var ip net.IP
			switch v := a.(type) {
			case *net.IPNet:
				ip = v.IP
			case *net.IPAddr:
				ip = v.IP
			}
			if ip == nil || ip.To4() == nil {
				continue
			}
			out = append(out, ip.String())
		}
	}
	return out
}

func pickLikelyPublic(ips []string) string {
	for _, ip := range ips {
		if !isPrivateIPv4(ip) {
			return ip
		}
	}
	if len(ips) > 0 {
		return ips[0]
	}
	return ""
}

func isPrivateIPv4(ip string) bool {
	parsed := net.ParseIP(ip)
	if parsed == nil {
		return true
	}
	return parsed.IsPrivate() || parsed.IsLoopback() || parsed.IsLinkLocalUnicast()
}