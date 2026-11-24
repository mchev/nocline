// Package enroll échange un token d'enrôlement à usage unique contre un token permanent.
package enroll

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"runtime"
	"strings"
	"time"
)

// Result est la réponse 201 du hub.
type Result struct {
	HostID string
	Token  string
	Role   string
}

// Enroll appelle POST {hub}/api/v1/enroll. httpClient peut être nil.
func Enroll(ctx context.Context, httpClient *http.Client, hubURL, enrollmentToken, agentVersion string) (Result, error) {
	if httpClient == nil {
		httpClient = &http.Client{Timeout: 15 * time.Second}
	}
	body, _ := json.Marshal(map[string]string{
		"token":         enrollmentToken,
		"agent_version": agentVersion,
		"os":            runtime.GOOS,
	})
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, strings.TrimRight(hubURL, "/")+"/api/v1/enroll", bytes.NewReader(body))
	if err != nil {
		return Result{}, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	resp, err := httpClient.Do(req)
	if err != nil {
		return Result{}, err
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	if resp.StatusCode != http.StatusCreated {
		return Result{}, fmt.Errorf("enroll: hub a répondu %d: %s", resp.StatusCode, strings.TrimSpace(string(raw)))
	}
	var parsed struct {
		HostID any    `json:"host_id"`
		Token  string `json:"token"`
		Role   string `json:"role"`
	}
	if err := json.Unmarshal(raw, &parsed); err != nil {
		return Result{}, fmt.Errorf("enroll: réponse invalide: %w", err)
	}
	if parsed.Token == "" {
		return Result{}, fmt.Errorf("enroll: token absent de la réponse")
	}
	return Result{HostID: fmt.Sprint(parsed.HostID), Token: parsed.Token, Role: parsed.Role}, nil
}
