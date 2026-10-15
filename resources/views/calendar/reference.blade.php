{{-- Standing rules for the Q4 plan, shown beside every week. --}}
<section class="panel side">
    <h3>The 36-hour boost rule</h3>
    <div class="bd rule">
        <p>Post organically into its slot.</p>
        <p>At <b>36 hours</b>, compare engagement to the median of the last <b>8 posts in that same format</b>.</p>
        <p>Beat it → boost. 39DG: top <b>2</b> per week at <b>$150</b> / 5 days. Ocusafe DTC: top <b>2</b> at <b>$125</b>. {{ \App\Support\BrandIcon::html('linkedin', 'xs') }}LinkedIn: <b>never boosted</b>.</p>
        <p>Fewer than two winners? <b>Don't spend it.</b> Carry it.</p>
    </div>
</section>
<section class="panel side">
    <h3>Funnel keywords</h3>
    <div class="bd kwlist">
        @foreach([
            ['GUIDE', 'Face shape fit guide · email gate'], ['BREAKDOWN', 'Where the $400 goes · email gate'],
            ['PD', 'Rx & pupillary distance · email gate'], ['RETURNS', 'Risk reversal · no gate'],
            ['SUPPORT', 'Existing order · never gate'], ['SPECSHEET', 'Ocusafe B2B · Z87.1 spec sheet · email gate'],
            ['PROGRAM', 'Ocusafe B2B · corporate · email gate'], ['FOG', 'Ocusafe DTC · start here · email gate'],
            ['Z87', 'Ocusafe DTC · who pays for yours · email gate'], ['TRADE', 'Ocusafe DTC · job-by-job fit · email gate'],
            ['FSA', 'Q4 only · both brands · never gate a deadline'], ['FL41', 'Neurolux · Marc writes final copy'],
        ] as [$kw, $what])
            <div class="kwrow"><code>{{ $kw }}</code><span>{{ $what }}</span></div>
        @endforeach
    </div>
</section>
<section class="panel side">
    <h3>Weekly caps</h3>
    <div class="bd">
        <p><strong>1,000</strong> active contacts / month — about 33 conversations a day across all brands.</p>
        <p><strong>1,250</strong> AI credits / month. flux-dev at 10 is the default; a run should cost under 60.</p>
        <p>{{ \App\Support\BrandIcon::html('instagram', 'xs') }}<strong>50</strong> IG posts / 24h · {{ \App\Support\BrandIcon::html('facebook', 'xs') }}<strong>25</strong> per FB Page / 24h · {{ \App\Support\BrandIcon::html('pinterest', 'xs') }}<strong>10</strong> Pinterest pins / day.</p>
    </div>
</section>
<section class="panel side">
    <h3>{{ \App\Support\BrandIcon::html('pinterest', 'xs') }}Pinterest rules</h3>
    <div class="bd">
        <p><strong>Real photography only.</strong> Pinterest labels AI-generated images and favours authentic content, the one surface where a generated slide hurts.</p>
        <p><strong>Drip, don't dump.</strong> 1–2 fresh pins a day beats 7–10 in one sitting.</p>
        <p><strong>Vertical 2:3</strong>, minimal legible overlay, intent-phrased title, linked to the page it's about.</p>
        <p><strong>Outbound clicks</strong> are the only metric that matters here, not followers, not saves.</p>
    </div>
</section>
