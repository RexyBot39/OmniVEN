{{-- A per-brand routine's brands, each ticked on its own. $r: the routine; $return: where to come back to. --}}
@if($r->isSplit())
    @php
        $checks = $r->currentBrandChecks();
        $brands = $r->splitBrands();
    @endphp
    <div class="rbrands" role="group" aria-label="Brands for {{ $r->title }}: {{ $checks->count() }} of {{ $brands->count() }} done">
        @foreach($brands as $b)
            <form method="POST" action="{{ route('routines.toggle-brand', [$r, $b]) }}">@csrf
                @if(! empty($return))<input type="hidden" name="return" value="{{ $return }}">@endif
                <button type="submit" class="rbrand {{ isset($checks[$b->id]) ? 'on' : '' }}" style="--bc:var(--c{{ $b->colorSlot() }})" aria-pressed="{{ isset($checks[$b->id]) ? 'true' : 'false' }}" title="{{ isset($checks[$b->id]) ? 'Done by '.($checks[$b->id]->user?->firstName() ?? 'someone').'. Click to untick.' : 'Tick '.$b->name }}">{{ isset($checks[$b->id]) ? '✓ ' : '' }}{{ $b->name }}</button>
            </form>
        @endforeach
        <span class="sub">{{ $checks->count() }} of {{ $brands->count() }}</span>
    </div>
@endif
