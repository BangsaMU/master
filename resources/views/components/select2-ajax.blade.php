<div class="mb-3">
    <label class="form-label">{{ $label }}</label>

    <select
        class="form-select"
        name="{{ $name }}"
        id="{{ \Illuminate\Support\Str::slug($name) }}"
        {{ $multiple ? 'multiple="multiple"' : '' }}
    >
        {{-- For edit forms, pre-populate with the initial selection --}}
        @foreach($initialSelection as $item)
            <option value="{{ $item->id }}" selected="selected">
                {{ $item->name }} ({{ $item->email }}) {{-- Adjust text format as needed --}}
            </option>
        @endforeach
    </select>

    @error(str_replace('[]', '', $name))
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
$(document).ready(function() {
    $('#{{ \Illuminate\Support\Str::slug($name) }}').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search for a {{ strtolower($model) }}...',
        ajax: {
            url: '{{ route("api.select2.search") }}?model={{ $model }}',
            dataType: 'json',
            delay: 250, // wait 250ms before triggering the request
            processResults: function (data, params) {
                // Tell Select2 that more results exist if our API says so
                params.page = params.page || 1;
                return {
                    results: data.results,
                    pagination: {
                        more: data.pagination.more
                    }
                };
            },
            cache: true
        },
    });
});
</script>
@endpush
