{{-- Sidebar icons (outline, 24px grid). $name is a menu group key or a page route. --}}
<svg class="sb-ic" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
    @case('work')<rect x="4" y="4" width="16" height="17" rx="2.5"/><path d="M9 3h6v3H9zM8 11l2 2 4-4M8 17h8"/>@break
    @case('revenue')<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>@break
    @case('growth')<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>@break
    @case('search')<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2M11 8v6M8 11h6"/>@break
    @case('seo')<path d="M4 19h16"/><path d="M7 15l3-4 3 2 4-6"/><circle cx="17" cy="7" r="1.6"/>@break
    @case('reputation')<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>@break
    @case('brands')<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>@break
    @case('guide')<path d="M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2z"/><path d="M8 7h6M8 11h6"/>@break
    @case('help')<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 0 1 4.9.7c0 1.7-2.4 2.3-2.4 3.8M12 17h.01"/>@break
    @case('activity.index')<path d="M3 12h4l3 7 4-14 3 7h4"/>@break
    @case('people.index')<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14a6 6 0 0 1 3 6"/>@break
    @case('access.review')<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>@break
    @default<circle cx="12" cy="12" r="8"/>
@endswitch
</svg>
