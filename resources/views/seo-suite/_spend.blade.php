{{-- Admins: what DataForSEO has cost this month against the budget. --}}
@if($spend)
    <p class="sub seo-spend">DataForSEO this month: <b>${{ number_format($spend['spent'], 2) }}</b> of the ${{ number_format($spend['budget'], 0) }} budget. Only admins see this.</p>
@endif
