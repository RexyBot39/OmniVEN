<form method="POST" action="{{ $action }}" class="danger-zone" data-confirm>
    @csrf @method('DELETE')
    <button class="btn danger" type="submit" data-label="{{ $label }}">{{ $label }}</button>
</form>
