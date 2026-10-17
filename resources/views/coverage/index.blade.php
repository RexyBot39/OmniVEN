@extends('layouts.app', ['title' => 'Coverage'])
@use('App\Support\BrandIcon')

@section('content')
<main class="wrap sales coverage">
    <div class="act-head">
        <div>
            <div class="eyebrow"><span>Coverage</span><span>Every brand × every platform</span></div>
            <h1 class="form-h">Coverage @include('partials.star', ['key' => 'page:29', 'label' => 'Coverage'])</h1>
            <p class="sub">Which accounts each brand has, what’s missing, and which need fixing.@can('admin') Mark a gap “not needed” so it stops counting.@endcan</p>
        </div>
    </div>

    <section class="tiles sales-tiles" aria-label="Summary">
        <div class="tile"><span class="tl">Coverage</span><span class="tv">{{ $score }}%</span><span class="sub">of the accounts brands should have</span></div>
        <div class="tile"><span class="tl">Gaps</span><span class="tv">{{ $gaps }}</span><span class="sub">accounts not set up</span></div>
        <div class="tile"><span class="tl">Need attention</span><span class="tv">{{ $attention->count() }}</span><span class="sub">broken, no link, or access not checked</span></div>
    </section>

    @foreach($groups as $group => $platforms)
        <section id="g-{{ $group }}">
            <div class="h2row"><h2>{{ $groupLabels[$group] ?? ucfirst($group) }}</h2></div>
            <div class="tablewrap">
                <table class="cov-t">
                    <thead><tr><th scope="col">Brand</th>@foreach($platforms as $key => $p)<th scope="col"><span class="cov-ph">{{ BrandIcon::html(BrandIcon::forPlatform($key), 'xs') }}{{ $p['label'] }}</span></th>@endforeach</tr></thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr @class(['shared-row' => $row['source']])>
                                <th scope="row"><a href="{{ route('brands.show', $row['brand']) }}"><span class="dot" style="background:var(--c{{ $row['brand']->colorSlot() }})"></span>{{ $row['brand']->name }}</a>@if($row['source'])<small class="shared-note">uses {{ $row['source']->name }}</small>@endif</th>
                                @foreach($platforms as $key => $p)
                                    @include('coverage.cell', ['row' => $row, 'key' => $key, 'p' => $p])
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section>
        <div class="h2row"><h2>Brand scores</h2><span class="sub">Accounts set up of those needed</span></div>
        <ul class="cov-scores">
            @foreach($rows->whereNull('source') as $row)
                <li><span class="dot" style="background:var(--c{{ $row['brand']->colorSlot() }})"></span><b>{{ $row['brand']->name }}</b><span class="cov-bar"><i style="width:{{ $row['expected'] ? round($row['have'] / $row['expected'] * 100) : 0 }}%"></i></span><span class="sub">{{ $row['have'] }} of {{ $row['expected'] }}</span></li>
            @endforeach
        </ul>
    </section>

    @if($attention->isNotEmpty())
        <section>
            <div class="h2row"><h2>Need attention</h2></div>
            <ul class="review-list">
                @foreach($attention as $x)
                    <li><b>{{ $x['account']->title() }}</b> <span class="sub">· {{ implode(' · ', $x['issues']) }}</span>
                        <span class="cov-acts">
                            @can('admin')<a class="mini" href="{{ route('accounts.edit', $x['account']) }}">Fix</a>@endcan
                            <a class="mini" href="{{ route('work.create', ['type' => 'task', 'title' => \Illuminate\Support\Str::limit('Check '.$x['account']->title(), 190), 'details' => implode('. ', $x['issues']).".\nConfirm the login and owner still work, add the direct link if it's missing, and mark access verified on OmniVEN."]) }}">Make a task</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</main>
@endsection
