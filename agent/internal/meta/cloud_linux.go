//go:build linux

package meta

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"strings"
	"time"
)

type cloudHit struct {
	provider   string
	region     string
	publicIPv4 string
}

func detectCloud() (cloudHit, bool) {
	clients := []func() (cloudHit, bool){
		tryHetzner,
		tryDigitalOcean,
		tryGCP,
		tryAzure,
		tryAWS,
	}
	for _, fn := range clients {
		if hit, ok := fn(); ok {
			return hit, true
		}
	}
	return cloudHit{}, false
}

func metadataGET(url string, headers map[string]string, timeout time.Duration) ([]byte, bool) {
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
	if err != nil {
		return nil, false
	}
	for k, v := range headers {
		req.Header.Set(k, v)
	}
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, false
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, false
	}
	raw, err := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	if err != nil {
		return nil, false
	}
	return raw, true
}

func tryHetzner() (cloudHit, bool) {
	raw, ok := metadataGET("http://169.254.169.254/hetzner/v1/metadata", nil, 1200*time.Millisecond)
	if !ok {
		return cloudHit{}, false
	}
	var data map[string]any
	if json.Unmarshal(raw, &data) != nil {
		return cloudHit{}, false
	}
	region, _ := data["region"].(string)
	return cloudHit{provider: "hetzner", region: region}, true
}

func tryDigitalOcean() (cloudHit, bool) {
	raw, ok := metadataGET("http://169.254.169.254/metadata/v1.json", nil, 1200*time.Millisecond)
	if !ok {
		return cloudHit{}, false
	}
	var data struct {
		Region string `json:"region"`
		Public string `json:"public_ipv4"`
	}
	if json.Unmarshal(raw, &data) != nil {
		return cloudHit{}, false
	}
	return cloudHit{provider: "digitalocean", region: data.Region, publicIPv4: data.Public}, true
}

func tryGCP() (cloudHit, bool) {
	zoneRaw, ok := metadataGET("http://metadata.google.internal/computeMetadata/v1/instance/zone", map[string]string{"Metadata-Flavor": "Google"}, 800*time.Millisecond)
	if !ok {
		return cloudHit{}, false
	}
	zone := strings.TrimSpace(string(zoneRaw))
	parts := strings.Split(zone, "/")
	zoneName := parts[len(parts)-1]
	region := zoneName
	if idx := strings.LastIndex(zoneName, "-"); idx > 0 {
		region = zoneName[:idx]
	}
	return cloudHit{provider: "gcp", region: region}, true
}

func tryAzure() (cloudHit, bool) {
	raw, ok := metadataGET("http://169.254.169.254/metadata/instance?api-version=2021-02-01", map[string]string{"Metadata": "true"}, 1200*time.Millisecond)
	if !ok {
		return cloudHit{}, false
	}
	var data struct {
		Compute struct {
			Location string `json:"location"`
		} `json:"compute"`
	}
	if json.Unmarshal(raw, &data) != nil {
		return cloudHit{provider: "azure"}, true
	}
	return cloudHit{provider: "azure", region: data.Compute.Location}, true
}

func tryAWS() (cloudHit, bool) {
	token, ok := awsIMDSv2Token()
	if !ok {
		return cloudHit{}, false
	}
	headers := map[string]string{"X-aws-ec2-metadata-token": token}
	regionRaw, ok := metadataGET("http://169.254.169.254/latest/meta-data/placement/availability-zone", headers, 1200*time.Millisecond)
	if !ok {
		return cloudHit{provider: "aws"}, true
	}
	region := strings.TrimSpace(string(regionRaw))
	if len(region) > 1 {
		region = region[:len(region)-1]
	}
	ipRaw, _ := metadataGET("http://169.254.169.254/latest/meta-data/public-ipv4", headers, 800*time.Millisecond)
	return cloudHit{provider: "aws", region: region, publicIPv4: strings.TrimSpace(string(ipRaw))}, true
}

func awsIMDSv2Token() (string, bool) {
	ctx, cancel := context.WithTimeout(context.Background(), 1200*time.Millisecond)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPut, "http://169.254.169.254/latest/api/token", nil)
	if err != nil {
		return "", false
	}
	req.Header.Set("X-aws-ec2-metadata-token-ttl-seconds", "60")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return "", false
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return "", false
	}
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 256))
	tok := strings.TrimSpace(string(raw))
	return tok, tok != ""
}