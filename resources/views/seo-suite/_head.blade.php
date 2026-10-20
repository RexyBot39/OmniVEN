{{-- SEO suite header: title, brand picker and the section's tabs. Pass $title, $star (launchpad page id), $lede and, for reports, $at. --}}
@php
    $tabs = [
        'overview' => ['Overview', 'seo-suite'],
        'keywords' => ['Keyword research', 'seo-suite.keywords'],
        'rankings' => ['Rank tracking', 'seo-suite.rankings'],
        'competitors' => ['Competitors', 'seo-suite.competitors'],
        'backlinks' => ['Backlinks', 'seo-suite.backlinks'],
        'audit' => ['Site audit', 'seo-suite.audit'],
        'ai' => ['AI visibility', 'seo-suite.ai'],
    ];
    if (! auth()->user()->can('use-seo-backlinks')) {
        unset($tabs['backlinks']);
    }
    $pageRoute = $tabs[$page][1] ?? 'seo-suite';
@endphp
<div class="act-head">
    <div>
        <div class="eyebrow"><span>SEO</span><span>From DataForSEO</span>@if($domain)<span>{{ $domain }}</span>@endif @if(! empty($at))<span>Updated {{ $at->format('M j, g:i A') }}</span>@endif</div>
        <h1 class="form-h">{{ $title }} @include('partials.star', ['key' => 'page:'.$star, 'label' => $title])</h1>
        @if(! empty($lede))<p class="sub">{{ $lede }}</p>@endif
    </div>
    @if($brands->count() > 1)
        <nav class="sales-brands" aria-label="Brand">
            @foreach($brands as $b)<a href="{{ route($pageRoute, ['brand' => $b->slug]) }}" @if($brand?->is($b)) aria-current="true" @endif><span class="dot" style="background:var(--c{{ $b->colorSlot() }})"></span>{{ $b->name }}</a>@endforeach
        </nav>
    @endif
</div>
<nav class="seo-tabs" aria-label="SEO tools">
    @foreach($tabs as $key => [$label, $route])
        <a href="{{ route($route, array_filter(['brand' => $brand?->slug])) }}" @if($page === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

@if(! $configured)
    <div class="empty sales-setup">
        <p><b>Connect DataForSEO to turn on the SEO suite.</b> Until then only admins can see these pages.</p>
        <ol>
            <li>Sign up at <b>dataforseo.com</b> and add credit (pay as you go; a few dollars a month covers these pages for three brands).</li>
            <li>In the DataForSEO dashboard, open <b>API Access</b> and copy the API login and API password.</li>
            <li>In Railway, set <code>DATAFORSEO_LOGIN</code> and <code>DATAFORSEO_PASSWORD</code>. Optional: <code>DATAFORSEO_MONTHLY_BUDGET</code> (dollars, default 50), <code>DATAFORSEO_DOMAINS</code> (e.g. <code>39dg=39dollarglasses.com,ocusafe=ocusafe.com</code>; otherwise each brand’s website is used) and <code>DATAFORSEO_BACKLINKS=true</code> once the account has the Backlinks subscription.</li>
            <li>To try the pages with sample data first, also set <code>DATAFORSEO_SANDBOX=true</code> (free).</li>
        </ol>
    </div>
@elseif(! $brand)
    <div class="empty">No brand has a website to measure. Add one on the brand’s page, or set <code>DATAFORSEO_DOMAINS</code>.</div>
@endif
