<script>
    @if (env('APP_ENV') != 'production')
        console.log('load js:{{ dirname(__FILE__) }}/global.blade');
    @endif
    function formatBytes(bytes, decimals = 2) {
        console.log('function formatBytes -> layout');
        if (!+bytes) return '0 Bytes'

        const k = 1024
        const dm = decimals < 0 ? 0 : decimals
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']

        const i = Math.floor(Math.log(bytes) / Math.log(k))

        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`
    }

    function peringatan(data, judul, rawData = '') {
        console.log('data:', data);
        console.log('judul:', judul);
        console.log('rawData::', rawData.responseJSON);

        window.isFormChanged = false;

        let respon_code = 200;
        if (typeof rawData.responseJSON == 'object') {
            respon_code = rawData.responseJSON.code;
        }

        let class_code = respon_code != 200 ? 'bg-warning' : 'bg-success';
        console.log('class_code::', class_code);
        var data_body = '';
        {{-- let myAlert = document.querySelector('.toast');
    var bsAlert = new bootstrap.Toast(myAlert); --}}

        var i = 0;
        if (typeof data === 'object' && data !== null) {
            $.each(data, function(index, item) {
                i++;
                console.log('data item', item);
                data_body += i + '. ' + item + '<br>';
            });
        } else {
            i++;
            // data_body += 'Silahkan Hubungi admin ato pastikan data di input sesuai dan unik <br>';
            data_body += i + '.' + data + '<br>';
        }

        $(document).Toasts('create', {
            class: class_code,
            title: judul,
            subtitle: ' {{ now() }}',
            delay: 3000,
            autohide: true,
            fade: true,
            body: data_body
        })
    }

    Fancybox.bind('[data-fancybox="slip-route"]', {
            // Your custom options for a specific gallery
        });


    Fancybox.bind('[data-fancybox="notif"]', {
            // Your custom options for a specific gallery
        });

    Fancybox.bind('[data-fancybox="route-slip"]', {
        // Your custom options for a specific gallery
    });
</script>
