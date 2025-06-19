<script>
    @if (env('APP_ENV') != 'production')
        console.log('load js:{{ dirname(__FILE__) }}/hrd-detail.blade');
    @endif

    var tableName = "#{{ $data['page']['tabel'] }}_tabel";

    $(document).ready(function() {
        console.log('spb detail js loaded::' + Date.now());

        window.isFormChanged = false;
        $(".select2-department_id").on("change", function() {
            window.isFormChanged = true;
        });

        // Check for unsaved changes before leaving the page
        $(window).on('beforeunload', function(event) {
            if (window.isFormChanged == true) {
                var message = "You have unsaved changes. Are you sure you want to leave?";
                event.returnValue = message;
                return message;
            }
        });



    });


    function actionDelete(event, el) {
        event.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete!'
        }).then((result) => {
            if (result.value == true) {
                $("#delete-form").attr('action', $(el).attr('href'));
                $("#delete-form").submit();
            }
        });

    }


    function notificationBeforeDelete(event, id) {
        Swal.fire({
                title: "Hapus Item",
                text: "Menghapus item akan ubah Qty ke 0.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Hapus',
            })
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('module.procurement.detail.delete') }}",
                        type: "delete",
                        dataType: "json",
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "detail_id": id
                        },
                        success: function(data) {
                            console.log('samu data:', data.success);
                            console.log('samu typeof:', typeof data.success);
                            if (data.success === true) {
                                Swal.fire("Done!", data.success, "success");
                                {{-- setInterval('window.location.reload()', 1000); --}}
                                $(tableName).DataTable().ajax.reload();

                            } else {
                                Swal.fire("Error!", "", "Delete tidak berhasil!");
                            }
                        },
                        error: function() {
                            Swal.fire("Error!", "", "Delete tidak berhasil!");
                        }
                    });
                } else if (result.isDenied) {
                    Swal.fire('Changes are not saved', '', 'info')
                }
            });
    }

    $(".select2-item_code_id").on("select2:select", function(e) {
        var data = e.params.data;
        console.log('select2-item_code_id::', data);
        $("#input{{ @$formdata->id }}_label_desciption").val(data.item_name);

        clear_storage_callback();
        callbackSelect2_input{{ @$formdata->id }}_item_code_id(data);
    });

    function clear_storage_callback() {
        localStorage.removeItem("input{{ @$formdata->id }}_unit_id");
        localStorage.removeItem("input{{ @$formdata->id }}_category_id");
        localStorage.removeItem("input{{ @$formdata->id }}_group_id");
        localStorage.removeItem("input{{ @$formdata->id }}_pca_id");
        localStorage.removeItem("input{{ @$formdata->id }}_department_id");
    }

    function callbackSelect2_input{{ @$formdata->id }}_item_code_id(data) {
        console.log('callbackSelect2_input{{ @$formdata->id }}_item_code_id::', data);
        $("#input_{{ @$formdata->id }}label_desciption").val(data.item_name);
        $("#input_{{ @$formdata->id }}unit_id_hidden").val(data.uom_id);
        /*set default nilai uom*/
        getSelect2_input{{ @$formdata->id }}_unit_id(data.uom_id);
        getSelect2_input{{ @$formdata->id }}_category_id(data.category_id);
        getSelect2_input{{ @$formdata->id }}_group_id(data.group_id);
        getSelect2_input{{ @$formdata->id }}_pca_id(data.pca_id);
    }

    function callbackSelect2_input{{ @$formdata->id }}_unit_id(data) {
        {{-- console.log('callbackSelect2_input{{ @$formdata->id }}_unit_id::',data);
        $("#input_{{@$formdata->id}}label_desciption").val(data.item_name);
        $("#input_{{@$formdata->id}}unit_id_hidden").val(data.uom_id);

        getSelect2_input{{@$formdata->id}}_unit_id(data.uom_id);
        $("#input_{{@$formdata->id}}unit_id").val(data.uom_id).trigger('change'); --}}
    }

    function callbackSelect2_input{{ @$formdata->id }}_department_id(data) {
        {{-- console.log('callbackSelect2_input{{ @$formdata->id }}_department_id::',data);

        getSelect2_input{{@$formdata->id}}_department_id(data.uom_id);
        $("#input_{{@$formdata->id}}_department_id").val(data.uom_id).trigger('change'); --}}
    }
</script>
