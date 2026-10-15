<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\CalendarCheck;
use App\Models\CalendarItem;
use App\Models\CalendarWeek;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admins add, edit and delete action-calendar items, either every week or for one week only. */
class CalendarItemController extends Controller
{
    public const OWNERS = ['Social', 'Ops', 'Marc'];

    public function create(Request $request, CalendarWeek $week): View
    {
        $item = new CalendarItem(['day' => in_array($request->input('day'), CalendarWeek::DAY_ORDER, true) ? $request->input('day') : 'Mon', 'owner' => 'Social']);

        return $this->form($week, $item, $request->input('scope') === 'every' ? 'every' : 'week');
    }

    public function store(Request $request, CalendarWeek $week): RedirectResponse
    {
        [$data, $scope] = $this->validated($request);
        $item = CalendarItem::create($data + [
            'key' => 'custom-'.Str::lower(Str::random(10)),
            'calendar_week_id' => $scope === 'every' ? null : $week->id,
            'sort' => (CalendarItem::max('sort') ?? 0) + 1,
        ]);
        Activity::log('calendar.item.created', 'Added calendar item “'.Str::limit($item->title, 80).'” ('.$this->scopeText($item, $week).')', $item);

        return redirect()->to(route('calendar.week', $week).'#item-'.$item->id)->with('status', 'Item added '.$this->scopeText($item, $week).'.');
    }

    public function edit(CalendarWeek $week, CalendarItem $item): View
    {
        $this->assertInWeek($week, $item);

        return $this->form($week, $item, $item->calendar_week_id ? 'week' : 'every');
    }

    public function update(Request $request, CalendarWeek $week, CalendarItem $item): RedirectResponse
    {
        $this->assertInWeek($week, $item);
        [$data, $scope] = $this->validated($request);
        $wasEvery = $item->calendar_week_id === null;
        $item->fill($data);
        $item->calendar_week_id = $scope === 'every' ? null : ($item->calendar_week_id ?? $week->id);
        $item->save();

        // Narrowed from every week to one week: other weeks' checkmarks for it no longer apply.
        if ($wasEvery && $item->calendar_week_id) {
            CalendarCheck::where('calendar_item_id', $item->id)->where('calendar_week_id', '!=', $item->calendar_week_id)->delete();
        }
        Activity::log('calendar.item.updated', 'Edited calendar item “'.Str::limit($item->title, 80).'” ('.$this->scopeText($item, $week).')', $item);

        return redirect()->to(route('calendar.week', $week).'#item-'.$item->id)->with('status', 'Item saved.');
    }

    public function destroy(CalendarWeek $week, CalendarItem $item): RedirectResponse
    {
        $this->assertInWeek($week, $item);
        $scope = $this->scopeText($item, $week);
        Activity::log('calendar.item.deleted', 'Deleted calendar item “'.Str::limit($item->title, 80).'” ('.$scope.')', $item);
        $item->delete(); // its checkmarks go with it

        return redirect()->to(route('calendar.week', $week).'#week')->with('status', 'Item deleted '.$scope.'.');
    }

    private function form(CalendarWeek $week, CalendarItem $item, string $scope): View
    {
        return view('calendar.item-form', [
            'week' => $week, 'item' => $item, 'scope' => $scope,
            'brands' => Brand::orderBy('sort')->orderBy('name')->get(),
            'weekCount' => CalendarWeek::count(),
            'checkCount' => $item->exists ? CalendarCheck::where('calendar_item_id', $item->id)->count() : 0,
        ]);
    }

    /** @return array{0: array, 1: string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'day' => ['required', Rule::in(CalendarWeek::DAY_ORDER)],
            'scope' => ['required', Rule::in(['every', 'week'])],
            'brand_slug' => ['nullable', 'string', Rule::exists('brands', 'slug')],
            'keyword' => ['nullable', 'string', 'max:60'],
            'owner' => ['required', 'string', 'max:40'],
            'done_when' => ['nullable', 'string', 'max:1000'],
        ]);
        $scope = $data['scope'];
        unset($data['scope']);
        $data['brand_slug'] = $data['brand_slug'] ?: null;

        return [$data, $scope];
    }

    private function assertInWeek(CalendarWeek $week, CalendarItem $item): void
    {
        abort_unless($item->calendar_week_id === null || $item->calendar_week_id === $week->id, 404);
    }

    private function scopeText(CalendarItem $item, CalendarWeek $week): string
    {
        return $item->calendar_week_id ? 'week '.($item->week?->number ?? $week->number).' only' : 'every week';
    }
}
