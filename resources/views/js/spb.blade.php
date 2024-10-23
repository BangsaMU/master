<script>

    var tableName = "#{{ $data['page']['tabel'] }}_tabel";

    @if (env('APP_ENV') != 'production')
        console.log('load js:{{ dirname(__FILE__) }}/spb.blade');
    @endif

    $(document).ready(function() {
        console.log('spb js loaded::' + Date.now());

        window.isFormChanged = false;
        $(".select2-department_id").on("change", function() {
            window.isFormChanged = true;
        });
        {{-- {{dd($formdata->status=='draft')}} --}}
        @if (@$formdata->status == 'draft')
            // Check for unsaved changes before leaving the page
            $(window).on('beforeunload', function(event) {
                if (window.isFormChanged == true) {
                    var message = "You have unsaved changes. Are you sure you want to leave?";
                    event.returnValue = message;
                    return message;
                }
            });
        @endif
    });

    function getSpbruningNumber(type_id, getNumber) {
        console.log('getSpbruningNumber::', type_id, getNumber);
        // var number_label='';
        // data_spb['number_label']='';
        if (getNumber == true && type_id) {
            $.getJSON(
                "/api/getruning_numberbyparams?set[text]=format_number&set[field][]=requisition_id&search[requisition_id]=0&limit=1&order[column]=format_number&order[direction]=asc", {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    'search[type_id]': type_id,
                },
                function(data) {
                    if (data[0]) {
                        let def_id = data[0].id;
                        let def_val = data[0].text;
                        data_spb['number_label'] = def_val;
                        generateSpbNumber(data_spb, false);
                    }
                });
        }

    }

    function generateSpbNumber(data_spb, getNumber) {
        var data = [];
        data['project_id'] = $('.select2-project_id').select2('data');
        let project_label = data['project_id'][0].text.split("|");
        data['type_id'] = $('.select2-type_id').select2('data');
        data['spb_source_id'] = $('.select2-spb_source_id').select2('data');
        let source_label = data['spb_source_id'][0].text.split("|");
        data['department_id'] = $('.select2-department_id').select2('data');
        data['input_code_number'] = $('.input_code_number').val();
        let number_label = data['input_code_number'].split("-");


        console.log('number_label::', number_label);
        data_spb.project_label = project_label[0];
        data_spb.type_label = data['type_id'][0].text;
        // data_spb.source_label = data['spb_source_id'][0].text;
        data_spb.source_label = source_label[0].replace("-", "_");;
        data_spb.department_label = data['department_id'][0].department_code;
        data_spb.number_label = data_spb.number_label ?? number_label[number_label.length - 1];
        console.log("data_spb::", data_spb);
        // alert(data['type_id'][0].text);
        // alert(data['type_id'][0].id);

        //   var x = document.getElementById("input_code_number");
        // 21340-SPB-BLR-PRC-000001
        // data_spb['number_label'] = '000001';
        getSpbruningNumber(data_spb.type_id, getNumber);
        spb_number = data_spb.project_label + '-' + data_spb.type_label + '-' + data_spb.source_label + '-' + data_spb
            .department_label + '-' + data_spb.number_label;
        spb_number.toUpperCase();

        //   var x = document.getElementById("input_code_number");
        //   x.value = x.value(123);

        // $("#input_code_number").val("Glenn Quagmire");
        // let id = getUrlParameter('id');
        // alert(id);
        $(".input_code_number").val(spb_number.toUpperCase());
    }


    var data_spb = [];

    $(".select2-type_id").on("select2:select", function(e) {
        var data = e.params.data;
        console.log(data);
        data_spb['type_id'] = data.id;
        data_spb['type_label'] = data.text;
        generateSpbNumber(data_spb, true);
    });
    $(".select2-department_id").on("select2:select", function(e) {
        var data = e.params.data;
        console.log(data);
        data_spb['department_label'] = data.department_code;
        generateSpbNumber(data_spb, true);
    });
    $(".select2-project_id").on("select2:select", function(e) {
        var data = e.params.data;
        console.log(data);
        data_spb['project_label'] = data.project_code;
        generateSpbNumber(data_spb, true);
    });
    $(".select2-spb_source_id").on("select2:select", function(e) {
        var data = e.params.data;
        console.log(data);
        data_spb['source_label'] = data.loc_code;
        generateSpbNumber(data_spb, true);
    });
    $(".select2-spb_source_id").on("select2:select", function(e) {
        var data = e.params.data;
        console.log(data);
        data_spb['source_label'] = data.loc_code;
        generateSpbNumber(data_spb, true);
    });



    var getUrlParameter = function getUrlParameter(sParam) {
        // Get the query string part of the URL, excluding the "?" character
        var sPageURL = window.location.search.substring(1),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;

        // Iterate over each key-value pair in the query string
        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');

            // Check if the current parameter matches the requested parameter
            if (sParameterName[0] === sParam) {
                // If the parameter value is undefined, return true
                // Otherwise, decode and return the parameter value
                return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
            }
        }

        // Return false if the parameter is not found
        return false;
    };

    function statusRequisition(id, set_status) {
        Swal.fire({
                title: "Revisi",
                text: "Apa ingin melakukan revisi, status requisition akan di set ke draft serta action approval akan di reset?",
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
                    $.ajax({
                        url: "{{ route('module.procurement.updateStatus.json') }}",
                        type: "put",
                        dataType: "json",
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "id": id,
                            "status": set_status,
                            // "remarks": result.value
                        },
                        success: function(data) {
                            console.log('samu data:', data);
                            console.log('samu typeof:', typeof data.success);
                            if (data.code === 200) {
                                Swal.fire("Done!", data.success, "success");

                                    setTimeout(() => {
                                        window.location.href = data.redirect;
                                    }, 1000);
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
</script>
