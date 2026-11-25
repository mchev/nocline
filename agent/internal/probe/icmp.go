package probe

import (
	"context"
	"encoding/binary"
	"errors"
	"fmt"
	"math/rand"
	"net"
	"time"
)

const (
	icmpEchoRequest = 8
	icmpEchoReply   = 0
)

// Checksum calcule le checksum Internet (RFC 1071).
func Checksum(b []byte) uint16 {
	var sum uint32
	for i := 0; i+1 < len(b); i += 2 {
		sum += uint32(b[i])<<8 | uint32(b[i+1])
	}
	if len(b)%2 == 1 {
		sum += uint32(b[len(b)-1]) << 8
	}
	for sum>>16 != 0 {
		sum = sum&0xffff + sum>>16
	}
	return ^uint16(sum)
}

// BuildEchoRequest construit un paquet ICMP echo request avec checksum.
func BuildEchoRequest(id, seq uint16, payload []byte) []byte {
	p := make([]byte, 8+len(payload))
	p[0] = icmpEchoRequest
	binary.BigEndian.PutUint16(p[4:], id)
	binary.BigEndian.PutUint16(p[6:], seq)
	copy(p[8:], payload)
	binary.BigEndian.PutUint16(p[2:], Checksum(p))
	return p
}

// ParseEchoReply extrait id/seq d'un echo reply (en-tête IPv4 éventuel retiré). ok=false si ce n'est pas un reply valide.
func ParseEchoReply(b []byte) (id, seq uint16, ok bool) {
	if len(b) >= 20 && b[0]>>4 == 4 {
		ihl := int(b[0]&0x0f) * 4
		if ihl < 20 || len(b) < ihl+8 {
			return 0, 0, false
		}
		b = b[ihl:]
	}
	if len(b) < 8 || b[0] != icmpEchoReply || b[1] != 0 || Checksum(b) != 0 {
		return 0, 0, false
	}
	return binary.BigEndian.Uint16(b[4:]), binary.BigEndian.Uint16(b[6:]), true
}

// ICMPAvailable teste si une socket raw ICMP peut être ouverte (root / CAP_NET_RAW).
func ICMPAvailable() bool {
	c, err := net.Dial("ip4:icmp", "127.0.0.1")
	if err != nil {
		return false
	}
	c.Close()
	return true
}

func execICMP(ctx context.Context, c Check) Result {
	start := time.Now()
	lat, err := ping(ctx, c.Target, timeoutOf(c))
	r := finish(c, start, err, nil)
	if err == nil {
		r.LatencyMS = lat.Milliseconds()
	}
	return r
}

func ping(ctx context.Context, target string, timeout time.Duration) (time.Duration, error) {
	d := net.Dialer{Timeout: timeout}
	conn, err := d.DialContext(ctx, "ip4:icmp", target)
	if err != nil {
		return 0, err
	}
	defer conn.Close()
	deadline := time.Now().Add(timeout)
	_ = conn.SetDeadline(deadline)
	id := uint16(rand.Intn(1 << 16))
	seq := uint16(1)
	pkt := BuildEchoRequest(id, seq, []byte("nocline-probe"))
	sent := time.Now()
	if _, err := conn.Write(pkt); err != nil {
		return 0, err
	}
	buf := make([]byte, 1500)
	for {
		if ctx.Err() != nil {
			return 0, ctx.Err()
		}
		n, err := conn.Read(buf)
		if err != nil {
			var ne net.Error
			if errors.As(err, &ne) && ne.Timeout() {
				return 0, fmt.Errorf("ICMP timeout")
			}
			return 0, err
		}
		if rid, rseq, ok := ParseEchoReply(buf[:n]); ok && rid == id && rseq == seq {
			return time.Since(sent), nil
		}
	}
}
