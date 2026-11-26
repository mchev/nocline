package meta

// Snapshot is pushed to the hub (enroll + periodic ingest).
type Snapshot struct {
	Hostname        string   `json:"hostname,omitempty"`
	PublicIPv4      string   `json:"public_ipv4,omitempty"`
	IPv4s           []string `json:"ipv4s,omitempty"`
	Distro          string   `json:"distro,omitempty"`
	Kernel          string   `json:"kernel,omitempty"`
	Provider        string   `json:"provider,omitempty"`
	ProviderRegion  string   `json:"provider_region,omitempty"`
	ProviderSource  string   `json:"provider_source,omitempty"`
	// Stack : versions détectées sur le serveur (php, node, …) — snapshot, pas de heartbeat.
	Stack map[string]string `json:"stack,omitempty"`
}