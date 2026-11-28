//go:build !linux

package meta

func RebootRequired() (bool, string) {
	return false, ""
}