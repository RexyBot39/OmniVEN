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

/** A person's own launchpad: starred items (in the order starred) and recently opened ones. */
class Launchpad
{
    public const TYPES = ['tool' => Tool::class, 'account' => Account::class, 'brand' => Brand::class];

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

    /** Star or un-star an item. Returns true when it is now starred. */
    public static function toggle(User $user, Model $item): bool
    {
        $where = ['user_id' => $user->id, 'item_type' => self::typeOf($item), 'item_id' => $item->getKey()];
        $existing = Favorite::where($where)->first();
        if ($existing) {
            $existing->delete();

            return false;
        }
        Favorite::create($where);

        return true;
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
        $favs = Favorite::where('user_id', $user->id)->orderBy('id')->get();
        $recents = RecentOpen::where('user_id', $user->id)->orderByDesc('opened_at')->orderByDesc('id')->get();
        $models = self::load($favs->concat($recents));

        $present = fn ($rows) => $rows->map(fn ($r) => $models[$r->item_type.':'.$r->item_id] ?? null)->filter()->values();
        $favorites = $present($favs);
        $favKeys = $favorites->pluck('key')->flip();

        return [
            'favorites' => $favorites,
            'recent' => $present($recents)->reject(fn ($i) => $favKeys->has($i['key']))->take($recentLimit)->values(),
        ];
    }

    /** Fetch every referenced model in one query per type, keyed "type:id" => presented item. */
    private static function load(Collection $rows): array
    {
        $out = [];
        foreach ($rows->groupBy('item_type') as $type => $group) {
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

    /** Name, link and look of one item. Never includes logins or notes. */
    public static function present(Model $m): array
    {
        $type = self::typeOf($m);
        $base = ['key' => $type.':'.$m->id, 'type' => $type, 'id' => $m->id];

        return $base + match ($type) {
            'tool' => [
                't' => $m->name, 's' => $m->category ?? 'Tool', 'u' => $m->url ?: route('dashboard').'#tools',
                'x' => (bool) $m->url, 'ini' => $m->initials(), 'c' => $m->categorySlot(),
            ],
            'account' => [
                't' => $m->title(), 's' => $m->platformLabel(),
                'u' => $m->openUrl() ?: ($m->brand ? route('brands.show', $m->brand) : route('dashboard').'#access'),
                'x' => (bool) $m->openUrl(), 'ini' => $m->platformInfo()['short'] ?? '·', 'c' => $m->brand?->colorSlot() ?? 1,
            ],
            'brand' => [
                't' => $m->name, 's' => 'Brand hub', 'u' => route('brands.show', $m), 'x' => false,
                'ini' => strtoupper(mb_substr($m->name, 0, 2)), 'c' => $m->colorSlot(),
            ],
        };
    }
}
