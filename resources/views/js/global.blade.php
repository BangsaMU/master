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

        var i = 0;
        if (typeof data === 'object' && data !== null) {
            $.each(data, function(index, item) {
                i++;
                console.log('data item', item);
                data_body += i + '. ' + item + '<br>';
            });
        } else {
            i++;
            data_body += i + '.' + data + '<br>';
        }

        if (typeof $.fn.Toasts === 'function') {
            $(document).Toasts('create', {
                class: class_code,
                title: judul,
                subtitle: ' {{ now() }}',
                delay: 3000,
                autohide: true,
                fade: true,
                body: data_body
            });
        } else {
            alert(judul + '\n' + data_body.replace(/<br>/g, '\n'));
        }
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

    function importFn() {
        const importEl = document.getElementById('importItemDiv');
        if (importEl) {
            const isCurrentlyClosed = importEl.classList.contains('hidden') || 
                                      importEl.classList.contains('d-none') || 
                                      importEl.getAttribute('data-state') === 'closed' || 
                                      !importEl.open;

            if (isCurrentlyClosed) {
                importEl.classList.remove('hidden', 'd-none');
                importEl.setAttribute('data-state', 'open');
                importEl.setAttribute('open', '');

                if (typeof importEl.showModal === 'function' && importEl.tagName.toLowerCase() === 'dialog') {
                    try {
                        if (!importEl.open) {
                            importEl.showModal();
                        }
                    } catch (e) {
                        console.warn('showModal:', e);
                    }
                }
            } else {
                importFnClose();
            }
        }
        return false;
    }

    function importFnClose() {
        const importEl = document.getElementById('importItemDiv');
        if (importEl) {
            if (typeof importEl.close === 'function' && importEl.tagName.toLowerCase() === 'dialog') {
                try {
                    if (importEl.open) {
                        importEl.close();
                    }
                } catch (e) {
                    console.warn('close:', e);
                }
            }
            importEl.classList.add('hidden');
            importEl.setAttribute('data-state', 'closed');
            importEl.removeAttribute('open');
        }
        return false;
    }

    $(document).on('click', '[data-dismiss="alert"], .alert-dismissible .close, .alert .close', function(e) {
        e.preventDefault();
        $(this).closest('.alert').fadeOut(150, function() {
            $(this).remove();
        });
    });
</script>
