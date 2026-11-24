{{ $incident->title }}

{{ __('Status') }}: {{ $kind === 'resolved' ? __('resolved') : __('down') }}
{{ __('Started') }}: {{ $incident->started_at->isoFormat('LLLL') }}
@if ($incident->resolved_at)
{{ __('Resolved') }}: {{ $incident->resolved_at->isoFormat('LLLL') }}
@endif
@if ($incident->last_error)
{{ __('Error') }}: {{ $incident->last_error }}
@endif
