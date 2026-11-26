//go:build !linux

package meta

// CollectStack returns no stack facts on unsupported platforms.
func CollectStack() map[string]string {
	return nil
}