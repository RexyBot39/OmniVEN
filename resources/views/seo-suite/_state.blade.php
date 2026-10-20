{{-- A report's state: still loading, failed (admins see why) or stale, plus an admin "Refresh now". Pass $report and $kind. --}}
@if(! $report['data'])
    <div class="empty">
        @if($report['error'] && ! $report['loading'])
            Can’t read DataForSEO yet.@can('admin') {{ $report['error'] }}@endcan
        @else
            Loading from DataForSEO… refresh in a minute.
        @endif
    </div>
@elseif($report['error'])
    <div class="callout warn-callout" role="status"><div><b>The last update from DataForSEO failed.</b> Showing numbers from {{ $report['at']?->format('M j') }}.@can('admin') {{ $report['error'] }}@endcan</div></div>
@endif
@can('admin')
    @if($report['data'] || ($report['error'] && ! $report['loading']))
        <form method="POST" action="{{ route('seo-suite.refresh') }}" class="seo-refresh">@csrf
            <input type="hidden" name="brand" value="{{ $brand->slug }}"><input type="hidden" name="kind" value="{{ $kind }}">
            <button class="mini" type="submit">Refresh now</button>
        </form>
    @endif
@endcan
