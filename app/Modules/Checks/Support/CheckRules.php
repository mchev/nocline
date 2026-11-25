<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckType;
use Illuminate\Validation\Rule;

/**
 * Validation rules for a check payload, shared by the web form and the REST API.
 */
final class CheckRules
{
    /** Longest expected ping period of a heartbeat check (30 days); other checks stop at one day. */
    public const HEARTBEAT_MAX_INTERVAL_SEC = 2592000;

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function make(mixed $type = null): array
    {
        $isHeartbeat = $type === CheckType::Heartbeat->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CheckType::class)],
            'target' => ['required_unless:type,agent,heartbeat', 'nullable', 'string', 'max:500'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'host_id' => ['nullable', 'integer', 'exists:hosts,id', 'required_if:type,agent'],
            'interval_sec' => ['integer', 'min:30', 'max:'.($isHeartbeat ? self::HEARTBEAT_MAX_INTERVAL_SEC : 86400)],
            'timeout_sec' => ['integer', 'min:1', 'max:60'],
            'failure_threshold' => ['integer', 'min:1', 'max:10'],
            'config' => ['nullable', 'array'],
            'config.keyword' => ['nullable', 'string', 'max:255'],
            'config.expected_status' => ['nullable', 'integer', 'between:100,599'],
            'config.latency_alert_ms' => ['nullable', 'integer', 'min:100', 'max:120000'],
            'config.latency_sustain' => ['nullable', 'integer', 'min:1', 'max:20'],
            'config.grace_sec' => ['nullable', 'integer', 'between:0,'.self::HEARTBEAT_MAX_INTERVAL_SEC],
            'category' => ['sometimes', Rule::enum(CheckCategory::class)],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'enabled' => ['boolean'],
            'execution' => [Rule::enum(CheckExecution::class)],
            'quorum' => ['in:any,majority,all'],
        ];
    }
}
