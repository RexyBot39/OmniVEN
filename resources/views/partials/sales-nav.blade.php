{{-- Tabs across the Sales pages. --}}
<nav class="seg" aria-label="Sales pages">
    <a class="seg-b" href="{{ route('sales') }}" aria-pressed="{{ request()->routeIs('sales') ? 'true' : 'false' }}">Revenue by channel</a>
    <a class="seg-b" href="{{ route('affiliates') }}" aria-pressed="{{ request()->routeIs('affiliates') ? 'true' : 'false' }}">Affiliates</a>
    <a class="seg-b" href="{{ route('email') }}" aria-pressed="{{ request()->routeIs('email') ? 'true' : 'false' }}">Email &amp; SMS</a>
    <a class="seg-b" href="{{ route('reports.monthly') }}" aria-pressed="{{ request()->routeIs('reports.monthly') ? 'true' : 'false' }}">Monthly report</a>
</nav>
