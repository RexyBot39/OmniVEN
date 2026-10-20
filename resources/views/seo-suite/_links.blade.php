{{-- Backlink rows: where the link is, the anchor text, our page, and when it appeared ($date = first_seen) or was lost ($date = lost). --}}
@if(! $rows)
    <div class="empty">{{ $empty }}</div>
@else
    <div class="tablewrap">
        <table class="sales-ch seo-links">
            <thead><tr><th>From</th><th>Our page</th><th class="n">Strength</th><th>{{ $date === 'lost' ? 'Lost' : 'Found' }}</th>@if(! empty($lostTask))<th><span class="sr">Task</span></th>@endif</tr></thead>
            <tbody>
                @foreach($rows as $r)
                    <tr>
                        <td class="seo-key"><a href="{{ $r['from'] }}" target="_blank" rel="noopener noreferrer">{{ $r['domain'] ?: parse_url($r['from'], PHP_URL_HOST) }}</a>@if($r['anchor'] !== '')<div class="sub">“{{ $r['anchor'] }}”{{ $r['dofollow'] ? '' : ' · nofollow' }}</div>@endif</td>
                        <td class="seo-key">{{ parse_url($r['to'], PHP_URL_PATH) ?: '/' }}</td>
                        <td class="n">{{ $r['rank'] }}</td>
                        <td>{{ $r[$date] ? \Carbon\CarbonImmutable::parse($r[$date])->format('M j, Y') : '—' }}</td>
                        @if(! empty($lostTask))
                            <td><a class="mini" href="{{ route('work.create', ['type' => 'task', 'title' => \Illuminate\Support\Str::limit('SEO: ask '.$r['domain'].' to restore their link', 190), 'details' => "Lost link from: ".$r['from']."\nIt pointed to: ".$r['to'].($r['anchor'] !== '' ? "\nLink text: ".$r['anchor'] : '')."\nCheck why it went (page removed, link edited, our page moved), then ask them to restore or update it."]) }}">Make a task</a></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
