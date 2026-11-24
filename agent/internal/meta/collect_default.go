//go:build !linux

package meta

// Collect returns an empty snapshot on unsupported platforms.
func Collect() Snapshot {
	return Snapshot{}
}