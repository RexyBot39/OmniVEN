@php($selected = old('brands', $item->all_brands ? ['all'] : $item->brands->pluck('slug')->all()))
<fieldset class="fld">
    <legend class="lab">Brands</legend>
    <div class="checks">
        <label><input type="checkbox" name="brands[]" value="all" @checked(in_array('all', $selected))> All brands</label>
        @foreach($brands as $b)
            <label><input type="checkbox" name="brands[]" value="{{ $b->slug }}" @checked(in_array($b->slug, $selected))> {{ $b->name }}</label>
        @endforeach
    </div>
    <span class="hint">“All brands” overrides individual picks.</span>
</fieldset>
