<?php

namespace App\Http\Controllers;

use App\Models\AffiliateAsset;
use App\Models\Brand;
use App\Models\Publisher;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Affiliate assets: approved banners, discount codes, copy and links. Everyone can browse and copy;
 * admins manage them and make share links. A share link is a signed, expiring page for one brand
 * (and optionally one publisher, adding their own codes) that a publisher can open without signing in.
 */
class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::orderBy('sort')->get();
        $brand = $brands->firstWhere('slug', $request->query('brand'));
        $showEnded = $request->boolean('ended');
        $assets = AffiliateAsset::with(['brand', 'publisher'])
            ->when($brand, fn ($q) => $q->where(fn ($w) => $w->where('brand_id', $brand->id)->orWhereNull('brand_id')))
            ->orderByRaw('ends_on is null')->orderBy('ends_on')->orderByDesc('id')->get()
            ->filter(fn ($a) => $showEnded || $a->state() !== 'ended');

        return view('assets.index', [
            'groups' => collect(AffiliateAsset::KINDS)->map(fn ($label, $kind) => $assets->where('kind', $kind)->values()),
            'brands' => $brands,
            'brand' => $brand,
            'showEnded' => $showEnded,
            'endedCount' => AffiliateAsset::whereNotNull('ends_on')->where('ends_on', '<', now(config('app.timezone'))->format('Y-m-d'))->count(),
            'publishers' => Publisher::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('assets.form', ['asset' => new AffiliateAsset(['kind' => array_key_exists((string) $request->query('kind'), AffiliateAsset::KINDS) ? $request->query('kind') : 'image',
            'publisher_id' => $request->query('publisher')])] + $this->choices());
    }

    public function store(Request $request): RedirectResponse
    {
        $asset = new AffiliateAsset(['user_id' => $request->user()->id]);
        $this->save($request, $asset);
        Activity::log('asset.added', "Added the affiliate asset “{$asset->title}”", $asset);

        return redirect()->route('assets.index', ['brand' => $asset->brand?->slug])->with('status', 'Added.');
    }

    public function edit(AffiliateAsset $asset): View
    {
        return view('assets.form', ['asset' => $asset] + $this->choices());
    }

    public function update(Request $request, AffiliateAsset $asset): RedirectResponse
    {
        $this->save($request, $asset);

        return redirect()->route('assets.index', ['brand' => $asset->brand?->slug])->with('status', 'Saved.');
    }

    public function destroy(AffiliateAsset $asset): RedirectResponse
    {
        Activity::log('asset.deleted', "Removed the affiliate asset “{$asset->title}”");
        $asset->delete();

        return redirect()->route('assets.index')->with('status', 'Removed.');
    }

    /** The image itself, for signed-in staff. ?download=1 saves it with a tidy name. */
    public function file(Request $request, AffiliateAsset $asset): Response
    {
        return $this->serve($request, $asset);
    }

    /** Make a share link for publishers. */
    public function share(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'publisher_id' => ['nullable', Rule::exists('publishers', 'id')],
            'days' => ['required', Rule::in([7, 30, 90])],
        ]);
        $url = URL::temporarySignedRoute('kit', now()->addDays((int) $data['days']), array_filter(['b' => $data['brand_id'] ?? null, 'p' => $data['publisher_id'] ?? null]));
        $for = collect([Brand::find($data['brand_id'] ?? null)?->name, Publisher::find($data['publisher_id'] ?? null)?->name])->filter()->implode(' for ');
        Activity::log('asset.shared', 'Made an asset kit link'.($for ? " ({$for})" : '')." for {$data['days']} days");

        return back()->with(['status' => 'Share link ready. It works for '.$data['days'].' days.', 'kit_link' => $url]);
    }

    /** The publisher's kit: no sign-in, signed and expiring. */
    public function kit(Request $request): View
    {
        [$brand, $publisher] = $this->kitScope($request);
        $assets = AffiliateAsset::forKit($brand?->id, $publisher?->id)->orderByDesc('id')->get();
        $expires = (int) $request->query('expires');

        return view('assets.kit', [
            'brand' => $brand,
            'publisher' => $publisher,
            'groups' => collect(AffiliateAsset::KINDS)->map(fn ($label, $kind) => $assets->where('kind', $kind)->values()),
            'fileUrl' => fn (AffiliateAsset $a, bool $download = false) => URL::temporarySignedRoute('kit.file', $expires,
                array_filter(['asset' => $a->id, 'b' => $brand?->id, 'p' => $publisher?->id, 'download' => $download ? 1 : null])),
            'expires' => $expires,
        ]);
    }

    public function kitFile(Request $request, AffiliateAsset $asset): Response
    {
        [$brand, $publisher] = $this->kitScope($request);
        abort_unless(AffiliateAsset::forKit($brand?->id, $publisher?->id)->whereKey($asset->id)->exists(), 404);

        return $this->serve($request, $asset);
    }

    private function kitScope(Request $request): array
    {
        return [Brand::find($request->query('b')), Publisher::find($request->query('p'))];
    }

    private function serve(Request $request, AffiliateAsset $asset): Response
    {
        abort_unless($asset->kind === 'image' && isset(AffiliateAsset::MIMES[$asset->file_mime]), 404);
        $bytes = $asset->fileBytes();
        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => $asset->file_mime,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$asset->downloadName().'"',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'ETag' => '"'.$asset->sha1.'"',
        ]);
    }

    private function save(Request $request, AffiliateAsset $asset): void
    {
        $kind = $request->input('kind', $asset->kind);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(AffiliateAsset::KINDS))],
            'title' => ['required', 'string', 'max:160'],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'publisher_id' => ['nullable', Rule::exists('publishers', 'id')],
            'body' => [Rule::requiredIf(in_array($kind, ['code', 'copy'], true)), 'nullable', 'string', 'max:5000'],
            'url' => [Rule::requiredIf($kind === 'link'), 'nullable', 'url:http,https', 'max:1024'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'file' => [Rule::requiredIf($kind === 'image' && ! $asset->sha1), 'nullable', 'file', 'max:5120', 'mimetypes:'.implode(',', array_keys(AffiliateAsset::MIMES))],
        ], [
            'body.required' => $kind === 'code' ? 'Type the code.' : 'Type the copy.',
            'url.required' => 'Paste the link.',
            'file.required' => 'Choose an image (PNG, JPG, GIF or WebP).',
            'file.mimetypes' => 'Images must be PNG, JPG, GIF or WebP.',
            'file.max' => 'Images can be up to 5 MB.',
        ]);
        $asset->fill([
            'kind' => $data['kind'], 'title' => $data['title'], 'brand_id' => $data['brand_id'] ?? null,
            'publisher_id' => $data['kind'] === 'code' ? ($data['publisher_id'] ?? null) : null,
            'body' => $data['kind'] === 'code' ? mb_strtoupper(trim((string) $data['body'])) : ($data['body'] ?? null),
            'url' => $data['url'] ?? null, 'internal' => $request->boolean('internal'),
            'starts_on' => $data['starts_on'] ?? null, 'ends_on' => $data['ends_on'] ?? null,
        ]);
        $file = $request->file('file');
        $bytes = null;
        if ($file) {
            $bytes = (string) file_get_contents($file->getRealPath());
            $info = @getimagesizefromstring($bytes);
            $mime = $info['mime'] ?? null;
            if (! $info || ! isset(AffiliateAsset::MIMES[$mime])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'That file isn’t a readable PNG, JPG, GIF or WebP image.']);
            }
            $asset->fill(['file_name' => mb_substr($file->getClientOriginalName(), 0, 200), 'file_mime' => $mime, 'file_size' => strlen($bytes),
                'width' => min(65535, $info[0]), 'height' => min(65535, $info[1]), 'sha1' => sha1($bytes)]);
        }
        $asset->save();
        if ($bytes !== null) {
            $asset->saveFile($bytes);
        }
    }

    private function choices(): array
    {
        return ['kinds' => AffiliateAsset::KINDS, 'brands' => Brand::orderBy('sort')->get(), 'publishers' => Publisher::orderBy('name')->get(['id', 'name'])];
    }
}
