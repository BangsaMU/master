<script>
    @if (env('APP_ENV') != 'production')
        console.log('load js:{{ dirname(__FILE__) }}/master-data.blade');
    @endif

    $(document).ready(function() {
        console.log('spb js loaded::' + Date.now());

    });

    function processTabelString(tabel) {
        console.log("processTabelString::",tabel);
        // Cek apakah ada koma dalam string
        if (tabel.includes(',')) {
            // Ubah string menjadi array menggunakan split
            const tabelArray = tabel.split(',');

            // Loop melalui setiap elemen di array
            tabelArray.forEach(function(loc, index) {
                console.log(`Lokasi ${index + 1}: ${loc.trim()}`);
                getMasterFn(loc);
            });
        } else {
            // Jika tidak ada koma, anggap hanya satu lokasi
            console.log(`Hanya satu lokasi: ${tabel.trim()}`);
            getMasterFn(tabel);
        }
    }

    function getMasterFn(tabel) {
        $.LoadingOverlay('show');
        $.ajax({
            url: "{{ url('api/master-') }}" + tabel,
            type: "get",
            dataType: "json",
            data: {
                "_token": "{{ \Bangsamu\LibraryClay\Controllers\LibraryClayController::api_token(null) }}",
            },
            success: function(data) {
                console.log('samu data:', data);
                console.log('samu typeof:', typeof data.success);
                if (data.code == 200) {
                    console.log("reload::" + tableName);
                    $(tableName).DataTable().ajax.reload();
                    Swal.fire("Done!", data.success, "success");
                    $.LoadingOverlay('hide', true);
                } else {
                    Swal.fire("Error!", data.message, "error");
                    $.LoadingOverlay('hide', true);
                }
            },
            error: function() {
                    Swal.fire("Error!", data.message, "warning");
                $.LoadingOverlay('hide', true);
            }
        });
    }

    function syncFn(tabel) {
        tableName = "#{{ $data['page']['slug'] }}_tabel";
        Swal.fire({
                title: "Sync",
                text: "Apa ingin melakukan sync ke database master " + tabel + "?",
                // input: "textarea",
                // inputLabel: "Remarks",
                // inputPlaceholder: "Type your message here...",
                // inputAttributes: {
                //     "name":"remarks",
                //     "aria-label": "Type your message here"
                // },
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: '#3d9970',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya',
                showLoaderOnConfirm: true,
            })
            .then((result) => {
                if (result.isConfirmed) {
                    $.LoadingOverlay('show');
                    processTabelString(tabel);
                } else if (result.isDenied) {
                    Swal.fire('Changes are not saved', '', 'info')
                    $.LoadingOverlay('hide', true);
                }
            });
    }
</script>
