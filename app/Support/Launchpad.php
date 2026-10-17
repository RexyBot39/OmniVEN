<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Favorite;
use App\Models\RecentOpen;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A person's own launchpad: starred items, in the order they arranged them, and recently opened ones.
 * Tools, accounts and brands are records; pages and tool categories are fixed places in OmniVEN.
 */
class Launchpad
{
    public const TYPES = ['tool' => Tool::class, 'account' => Account::class, 'brand' => Brand::class];

    /** Places that can be starred without a database record. */
    public const PLACES = ['page', 'category'];

    /**
     * Major pages. Ids are stored on stars, so never reuse or renumber one; add new pages at the end.
     * [title, subtitle, route, hash, gate]
     */
    public const PAGES = [
        1 => ['Routines', 'OmniVEN tab', 'dashboard', 'routines', null],
        2 => ['Tasks', 'OmniVEN tab', 'dashboard', 'tasks', null],
        3 => ['Sticky Notes', 'OmniVEN tab', 'dashboard', 'postit', null],
        4 => ['Slack', 'OmniVEN tab', 'dashboard', 'slack', null],
        5 => ['Brands', 'OmniVEN tab', 'dashboard', 'brands', null],
        6 => ['Tools', 'OmniVEN tab', 'dashboard', 'tools', null],
        7 => ['Access', 'OmniVEN tab', 'dashboard', 'access', null],
        8 => ['Action calendar', 'This week\'s runbook', 'calendar', null, null],
        9 => ['Help', 'Field guide', 'help', null, null],
        10 => ['New task', 'Add a task for anyone', 'work.create', null, null],
        11 => ['Activity report', 'Admin', 'activity.index', null, 'view-activity'],
        12 => ['People', 'Admin', 'people.index', null, 'admin'],
        13 => ['Access review', 'Admin · quarterly', 'access.review', null, 'admin'],
        14 => ['Sales', 'Revenue by channel and brand', 'sales', null, 'see-revenue'],
        15 => ['Campaign links', 'UTM link builder', 'links.index', null, null],
    ];

    /** The page id for a dashboard tab, e.g. "tools" => 6. */
    public static function pageForTab(string $tab): ?int
    {
        foreach (self::PAGES as $id => $p) {
            if ($p[2] === 'dashboard' && $p[3] === $tab) {
                return $id;
            }
        }

        return null;
    }

    /** A tool category's stable id: a checksum of its name. */
    public static function categoryId(string $name): int
    {
        return crc32(mb_strtolower(trim($name)));
    }

    public static function categorySlug(string $name): string
    {
        return \Illuminate\Support\Str::slug($name);
    }

    /** Every tool category in use or configured, by id. */
    public static function categories(): array
    {
        $names = collect(config('omniven.categories'))->merge(Tool::whereNotNull('category')->distinct()->pluck('category'))->filter()->unique();

        return $names->mapWithKeys(fn ($n) => [self::categoryId($n) => $n])->all();
    }

    /** Whether this place exists and this person may open it. */
    public static function placeExists(User $user, string $type, int $id): bool
    {
        return match ($type) {
            'page' => isset(self::PAGES[$id]) && (! self::PAGES[$id][4] || $user->can(self::PAGES[$id][4])),
            'category' => isset(self::categories()[$id]),
            default => false,
        };
    }

    public const KEEP_RECENT = 20;

    public const SHOW_RECENT = 6;

    public static function typeOf(Model $model): string
    {
        return array_search($model::class, self::TYPES, true);
    }

    public static function find(string $type, mixed $id): ?Model
    {
        $class = self::TYPES[$type] ?? null;

        return $class && ctype_digit((string) $id) ? $class::find($id) : null;
    }

    /** Set of "type:id" keys this person has starred, for the star buttons. */
    public static function favoriteKeys(User $user): Collection
    {
        return Favorite::where('user_id', $user->id)->get()
            ->map(fn ($f) => $f->item_type.':'.$f->item_id)->flip();
    }

    /** The signed-in person's starred keys, looked up once per request (for star buttons on any page). */
    public static function myKeys(): Collection
    {
        static $keys = [];
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        return $keys[$user->id] ??= self::favoriteKeys($user);
    }

    /** Star or un-star an item (a record, or a "page"/"category" place). Returns true when it is now starred. */
    public static function toggle(User $user, Model|string $item, ?int $id = null): bool
    {
        $type = $item instanceof Model ? self::typeOf($item) : $item;
        $where = ['user_id' => $user->id, 'item_type' => $type, 'item_id' => $item instanceof Model ? $item->getKey() : $id];
        $existing = Favorite::where($where)->first();
        if ($existing) {
            $existing->delete();

            return false;
        }
        // New stars go to the end of the person's list.
        Favorite::create($where + ['position' => (int) Favorite::where('user_id', $user->id)->max('position') + 1]);

        return true;
    }

    /** Save the order the person arranged their stars in. Keys they don't own are ignored. */
    public static function reorder(User $user, array $keys): void
    {
        $favs = Favorite::where('user_id', $user->id)->get()->keyBy(fn ($f) => $f->item_type.':'.$f->item_id);
        $pos = 1;
        foreach (array_unique($keys) as $key) {
            if ($fav = $favs->pull($key)) {
                $fav->update(['position' => $pos++]);
            }
        }
        // Anything not sent keeps its relative order after the arranged ones.
        foreach ($favs->sortBy(fn ($f) => [$f->position ?? PHP_INT_MAX, $f->id]) as $fav) {
            $fav->update(['position' => $pos++]);
        }
    }

    public static function recordOpen(User $user, Model $item): void
    {
        RecentOpen::updateOrCreate(
            ['user_id' => $user->id, 'item_type' => self::typeOf($item), 'item_id' => $item->getKey()],
            ['opened_at' => now()],
        );
        $keep = RecentOpen::where('user_id', $user->id)->orderByDesc('opened_at')->orderByDesc('id')
            ->limit(self::KEEP_RECENT)->pluck('id');
        RecentOpen::where('user_id', $user->id)->whereNotIn('id', $keep)->delete();
    }

    /** ['favorites' => [...items], 'recent' => [...items]] ready for the strip and the launcher. */
    public static function forUser(User $user, int $recentLimit = self::SHOW_RECENT): array
    {
        $favs = Favorite::where('user_id', $user->id)->orderByRaw('position is null')->orderBy('position')->orderBy('id')->get();
        $recents = RecentOpen::where('user_id', $user->id)->orderByDesc('opened_at')->orderByDesc('id')->get();
        $models = self::load($favs->concat($recents), $user);

        $present = fn ($rows) => $rows->map(fn ($r) => $models[$r->item_type.':'.$r->item_id] ?? null)->filter()->values();
        $favorites = $present($favs);
        $favKeys = $favorites->pluck('key')->flip();

        return [
            'favorites' => $favorites,
            'recent' => $present($recents)->reject(fn ($i) => $favKeys->has($i['key']))->take($recentLimit)->values(),
        ];
    }

    /** Fetch every referenced model in one query per type, keyed "type:id" => presented item. */
    private static function load(Collection $rows, User $user): array
    {
        $out = [];
        foreach ($rows->groupBy('item_type') as $type => $group) {
            if (in_array($type, self::PLACES, true)) {
                foreach ($group->pluck('item_id')->unique() as $id) {
                    if ($item = self::presentPlace($user, $type, (int) $id)) {
                        $out[$type.':'.$id] = $item;
                    }
                }

                continue;
            }
            $class = self::TYPES[$type] ?? null;
            if (! $class) {
                continue;
            }
            $query = $class::whereIn('id', $group->pluck('item_id')->unique());
            if ($class === Account::class) {
                $query->with('brand');
            }
            foreach ($query->get() as $model) {
                $out[$type.':'.$model->id] = self::present($model);
            }
        }

        return $out;
    }

    /** A starred page or tool category, or null when it's gone or this person can't open it. */
    public static function presentPlace(User $user, string $type, int $id): ?array
    {
        if (! self::placeExists($user, $type, $id)) {
            return null;
        }
        $base = ['key' => $type.':'.$id, 'type' => $type, 'id' => $id, 'x' => false, 'ic' => null];
        if ($type === 'page') {
            [$t, $s, $route, $hash] = self::PAGES[$id];

            return $base + ['t' => $t, 's' => $s, 'u' => route($route).($hash ? '#'.$hash : ''), 'ini' => strtoupper(mb_substr($t, 0, 2)), 'c' => 1];
        }
        $name = self::categories()[$id];
        $slot = array_search($name, config('omniven.categories'), true);

        return $base + [
            't' => $name, 's' => 'Tool category', 'u' => route('dashboard', ['cat' => self::categorySlug($name)]).'#tools',
            'ini' => strtoupper(mb_substr($name, 0, 2)), 'c' => ($slot === false ? 0 : $slot) % 8 + 1,
        ];
    }

    /** Name, link and look of one item. Never includes logins or notes. */
    public static function present(Model $m): array
    {
        $type = self::typeOf($m);
        $base = ['key' => $type.':'.$m->id, 'type' => $type, 'id' => $m->id];

        return $base + match ($type) {
            'tool' => [
                't' => $m->name, 's' => $m->category ?? 'Tool', 'u' => $m->url ?: route('dashboard').'#tools',
                'x' => (bool) $m->url, 'ini' => $m->initials(), 'c' => $m->categorySlot(), 'ic' => BrandIcon::forTool($m),
            ],
            'account' => [
                't' => $m->title(), 's' => $m->platformLabel(),
                'u' => $m->openUrl() ?: ($m->brand ? route('brands.show', $m->brand) : route('dashboard').'#access'),
                'x' => (bool) $m->openUrl(), 'ini' => $m->platformInfo()['short'] ?? '·', 'c' => $m->brand?->colorSlot() ?? 1,
                'ic' => BrandIcon::forPlatform($m->platform),
            ],
            'brand' => [
                't' => $m->name, 's' => 'Brand hub', 'u' => route('brands.show', $m), 'x' => false,
                'ini' => strtoupper(mb_substr($m->name, 0, 2)), 'c' => $m->colorSlot(), 'ic' => null,
            ],
        };
    }
}
