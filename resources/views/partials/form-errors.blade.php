@if($errors->any())
    <div class="form-errors" role="alert">
        <strong>Please fix the following:</strong>
        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
