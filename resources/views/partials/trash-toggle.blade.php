{{--
    Saklar "Tampilkan data terhapus" di atas daftar master (layouts.dashboard.index, opt-in
    $data['datatable']['trash_toggle'] = ['default' => bool, 'label' => string], hanya untuk admin).
    Nilainya dikirim ke server sebagai show_trashed=1|0 (MasterTrash::hideTrashedRequested) dan diingat di browser
    (localStorage per halaman). Skrip: window.masterTrashToggle.value() dipakai fungsi ajax DataTables daftar.
--}}
@php
    $trashToggle = $data['datatable']['trash_toggle'];
    $trashToggleId = 'master-trash-toggle-' . $data['page']['slug'];
@endphp
<div class="form-check form-switch d-inline-block align-middle ms-2 ml-2 mb-3">
    <input class="form-check-input" type="checkbox" role="switch" id="{{ $trashToggleId }}" @checked(! empty($trashToggle['default']))>
    <label class="form-check-label" for="{{ $trashToggleId }}">{{ $trashToggle['label'] ?? 'Tampilkan data terhapus' }}</label>
</div>
@push('js')
    <script>
        (function() {
            var storageKey = 'master.trash-toggle.{{ $data['page']['slug'] }}';
            var input = document.getElementById(@json($trashToggleId));
            var initial = {{ ! empty($trashToggle['default']) ? 'true' : 'false' }};
            try {
                var saved = window.localStorage.getItem(storageKey);
                if (saved !== null) {
                    initial = saved === '1';
                }
            } catch (e) {} // penyimpanan browser boleh gagal (jendela privat): saklar tetap jalan tanpa ingatan
            input.checked = initial;

            window.masterTrashToggle = {
                value: function() {
                    return input.checked;
                }
            };

            input.addEventListener('change', function() {
                try {
                    window.localStorage.setItem(storageKey, input.checked ? '1' : '0');
                } catch (e) {}
                if (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(tableName)) {
                    jQuery(tableName).DataTable().ajax.reload(); // kembali ke halaman 1
                }
            });
        })();
    </script>
@endpush
