<?php

namespace App\Support;

use App\Models\User;
use App\Support\Assistant\Assistant;

/**
 * The top menu: every OmniVEN page, grouped. Desktop shows each group as a mega-menu panel; phones show the
 * same groups in the hamburger drawer. Items are launchpad page ids, so titles and gates stay in one place.
 */
class Menu
{
    /** [label, blurb, assistant focus, [page ids]]; pages a person can't open are left out, and so are empty groups. */
    public const GROUPS = [
        'work' => ['Work', 'Your day, the team’s tasks and the weekly runbook', 'tasks', [2, 1, 10, 31, 8, 3, 4, 21]],
        'revenue' => ['Revenue', 'Sales by channel and brand, and the numbers behind them', 'sales', [14, 22, 17, 19, 40]],
        'growth' => ['Growth', 'Campaigns, publishers and the links that track them', 'campaigns', [28, 15, 26, 27]],
        'search' => ['Search & AI', 'Google, AI assistants and the content they need', 'search', [20, 23, 16, 24]],
        'seo' => ['SEO', 'Rankings, keywords, competitors, backlinks, audits and AI visibility', 'search', [33, 34, 35, 36, 37, 38, 39]],
        'opportunities' => ['Opportunities', 'New business for Ocusafe: government bids and RFPs', 'campaigns', [41]],
        'reputation' => ['Reputation', 'What customers and communities say about us', 'reputation', [18, 25]],
        'brands' => ['Brands & tools', 'Brand hubs, accounts, tools and who has access', 'site', [5, 6, 7, 29]],
    ];

    /** Page ids that belong in the account menu instead of the main groups. */
    public const ACCOUNT = [32, 9, 11, 12, 13];

    /** Extra routes that light up a group (pages inside a section that aren't menu items themselves). */
    private const ALSO = [
        'work' => ['my-day', 'work.*', 'tasks.*', 'calendar.*', 'templates.*'],
        'growth' => ['campaigns.*', 'outreach.*', 'assets.*', 'links.*'],
        'search' => ['content-gaps', 'site-check*', 'ai-answers*'],
        'revenue' => ['metabase*'],
        'opportunities' => ['opportunities*'],
        'seo' => ['seo-suite*'],
        'reputation' => ['reddit*', 'reviews*'],
        'brands' => ['brands.*', 'coverage*'],
    ];

    public static function for(User $user): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => [$label, $blurb, $focus, $ids]) {
            $items = array_values(array_filter(array_map(fn ($id) => self::item($user, $id), $ids)));
            if ($key === 'work') {
                array_splice($items, 1, 0, [['t' => 'My day', 's' => 'Your tasks and routines for today', 'u' => route('my-day'), 'route' => 'my-day', 'hash' => null, 'key' => null]]);
            }
            if ($items) {
                $groups[$key] = ['label' => $label, 'blurb' => $blurb, 'focus' => $focus, 'items' => $items, 'active' => self::active($key, $items)];
            }
        }

        return [
            'groups' => $groups,
            'account' => array_values(array_filter(array_map(fn ($id) => self::item($user, $id), self::ACCOUNT))),
            'ai' => Assistant::configured() && $user->can('use-assistant'),
        ];
    }

    private static function item(User $user, int $id): ?array
    {
        if (! Launchpad::placeExists($user, 'page', $id) || ($id === 31 && ! Assistant::configured())) {
            return null;
        }
        [$t, $s, $route, $hash] = Launchpad::PAGES[$id];

        return ['t' => $t, 's' => str_starts_with($s, 'OmniVEN tab') ? self::TAB_BLURBS[$hash] ?? $s : $s,
            'u' => route($route).($hash ? '#'.$hash : ''), 'route' => $route, 'hash' => $hash, 'key' => 'page:'.$id];
    }

    /** Dashboard tabs say "OmniVEN tab" on the launchpad; the menu says what's on them. */
    private const TAB_BLURBS = [
        'routines' => 'Recurring checklists for every brand',
        'tasks' => 'Everyone’s board: open, overdue and done',
        'postit' => 'Private notes and shared checklists',
        'slack' => 'Team channels without leaving OmniVEN',
        'brands' => 'Every brand’s hub, accounts and socials',
        'tools' => 'The tools we use, by category',
        'access' => 'Who has which logins, and requests',
    ];

    private static function active(string $key, array $items): bool
    {
        $here = request()->route()?->getName();
        if (! $here || $here === 'dashboard') {
            return false;
        }
        foreach ($items as $i) {
            if ($i['route'] === $here) {
                return true;
            }
        }

        return request()->routeIs(...(self::ALSO[$key] ?? ['__none']));
    }
}
