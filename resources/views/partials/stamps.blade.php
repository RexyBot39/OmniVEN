{{-- Admins only: when something was assigned and completed, to the minute (Eastern). Include inside @can('admin'). --}}
@php($tz = config('app.timezone'))
@if(! empty($assignedAt) || ! empty($doneAt))
    <div class="stamps" aria-label="Assignment and completion times">
        @if(! empty($assignedAt))
            <span class="stamp"><b>Assigned</b> {{ $assignedAt->timezone($tz)->format('M j, Y g:ia') }}@if(! empty($assignedTo)) to {{ $assignedTo->firstName() }}@endif{{ ! empty($assignedBy) ? ' by '.$assignedBy->firstName() : '' }}</span>
        @endif
        @if(! empty($doneAt))
            <span class="stamp done"><b>{{ $doneLabel ?? 'Done' }}</b> {{ $doneAt->timezone($tz)->format('M j, Y g:ia') }}{{ ! empty($doneBy) ? ' by '.$doneBy->firstName() : '' }}</span>
        @endif
    </div>
@endif
