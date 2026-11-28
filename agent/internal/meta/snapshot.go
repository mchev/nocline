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
	// RebootRequired : noyau ou paquets critiques installés mais pas actifs (lecture seule, Linux).
	RebootRequired bool `json:"reboot_required"`
	// RebootRequiredSince : horodatage RFC3339 (souvent mtime de /var/run/reboot-required).
	RebootRequiredSince string `json:"reboot_required_since,omitempty"`
}