<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel')) }}</title>

    @php
        $sidebarEnabled = Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('appearance.sidebar', false);
    @endphp

    <link rel="stylesheet" href="{{ asset('tabler.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('tabler-icons.min.css') }}">
    <link href="{{ asset('assets/vendor/select2.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2-bootstrap-5-theme.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('datatables/dataTables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fancybox.css') }}" />

    {{-- ✅ Global Warehouse Design System — loads after Tabler so overrides apply --}}
    <link rel="stylesheet" href="{{ asset('assets/css/warehouse-theme.css') }}" />

    <script src="{{ asset('assets/js/fancybox.umd.js') }}"></script>

    @stack('styles')
    @stack('css')

    <style>
        content: 'ec8f';

        body.has-sidebar .page-wrapper {
            margin-left: 16rem;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 991.98px) {
            body.has-sidebar .page-wrapper {
                margin-left: 0;
            }
        }
    </style>

    <!-- Theme initialization (prevents flash) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>

<body class="{{ $sidebarEnabled ? 'has-sidebar' : '' }}">
    {{-- Preloader for Session Location khsus wharehouse --}}
    @if (!session('WH_SELECTED_LOCATION_ID'))
        {{-- <div id="location-preloader"
            style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.9); z-index: 9999; display: flex; justify-content: center; align-items: center; flex-direction: column;">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h3 class="mt-3 text-muted">Please wait, checking location...</h3>
        </div> --}}
    @endif

    <div class="page">
        @if ($sidebarEnabled)
            @include('master::layouts.sidebar')
        @endif

        <div class="page-wrapper">

            @if (!$sidebarEnabled)
                @include('master::layouts.navbar-top')
                @include('master::layouts.navbar-menu')
            @endif

            <div class="container">
                @hasSection('header')
                    <div class="page-header d-print-none">
                        <div class="row align-items-center">
                            <div class="col-12 col-md mb-2">
                                @yield('header')
                            </div>
                            @hasSection('header-actions')
                                <div class="col-12 col-md-auto mb-2 ms-auto d-print-none">
                                    <div class="btn-list">
                                        @yield('header-actions')
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="page-body">
                    @isset($content_nav)
                        <div class="card mb-4">
                            <div class="card-body p-2">
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <nav class="nav nav-segmented">
                                        @yield('contentnav')
                                    </nav>
                                    <div class="ms-auto d-flex gap-2 align-items-center">
                                        {{-- konten kanan --}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endisset

                    @yield('content')
                </div>

                @include('master::layouts.footer')
            </div>
        </div>
    </div>

    @php
        $toastMessage = null;
        $toastType = null;
        $toastTitle = '';

        if (session('success')) {
            $toastMessage = session('success');
            $toastType = 'success';
            $toastTitle = 'Success';
        } elseif (session('error')) {
            $toastMessage = session('error');
            $toastType = 'error';
            $toastTitle = 'Error';
        } elseif ($errors->any()) {
            $errorList = '<ul>';
            foreach ($errors->all() as $error) {
                $errorList .= '<li>' . e($error) . '</li>';
            }
            $errorList .= '</ul>';
            $toastMessage = $errorList;
            $toastType = 'error';
            $toastTitle = 'Validation Error';
        }
    @endphp

    @if ($toastMessage)
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1056">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true"
                data-bs-delay="5000">
                <div
                    class="toast-header {{ $toastType == 'success' ? 'bg-success text-white' : 'bg-danger text-white' }}">
                    <strong class="me-auto">{{ $toastTitle }}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    {!! $toastMessage !!}
                </div>
            </div>
        </div>
    @endif

    <!-- Modal PDF VIEWER -->
    <div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-labelledby="pdfViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PDF Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-0" id="pdfViewerContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3">Memuat PDF...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 🌐 Global Reusable Confirmation Modal --}}
    <div class="modal modal-blur fade" id="global-action-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-primary"></div>
                <div class="modal-body text-center py-4">
                    <svg xmlns="https://www.w3.org/2000/svg" class="icon mb-2 text-secondary icon-lg" width="24"
                        height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M9 12l2 2l4 -4" />
                    </svg>
                    <h3 id="global-modal-title">Confirm Action</h3>
                    <div class="text-muted" id="global-modal-body-text">
                        Are you sure you want to proceed with this action?
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <a href="#" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</a>
                            </div>
                            <div class="col">
                                <button type="button" class="btn btn-primary w-100"
                                    id="global-confirm-action-btn">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('jquery.js') }}"></script>
    <script src="{{ asset('tabler.min.js') }}"></script>

    <script src="{{ asset('datatables/dataTables.js') }}"></script>
    <script src="{{ asset('datatables/dataTables.bootstrap5.js') }}"></script>
    <script>
        if ($.fn.dataTable) {
            $.fn.dataTable.ext.errMode = 'none';
            $(document).on('error.dt', function(e, settings, techNote, message) {
                console.error('DataTables error:', message);
                if (settings && settings.jqXHR) {
                    const status = settings.jqXHR.status;
                    if (status === 401 || status === 419) {
                        window.location.href = "{{ route('login') }}";
                        return;
                    }
                }
            });
        }
    </script>
    <script src="{{ asset('assets/vendor/select2.min.js') }}"></script>

    @stack('scripts')
    @stack('js')

    <script>
        $(function() {
            setTimeout(() => {
                $('.toast').fadeOut('slow');
            }, 5000);

            function updateThemeIcons(theme) {
                const lightIcon = document.getElementById('theme-icon-light');
                const darkIcon = document.getElementById('theme-icon-dark');
                if (lightIcon && darkIcon) {
                    if (theme === 'dark') {
                        lightIcon.classList.add('d-none');
                        darkIcon.classList.remove('d-none');
                    } else {
                        lightIcon.classList.remove('d-none');
                        darkIcon.classList.add('d-none');
                    }
                }
            }

            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            updateThemeIcons(currentTheme);

            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const html = document.documentElement;
                    const newTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                    html.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    updateThemeIcons(newTheme);
                });
            }
        });

        function loadPdfViewer(fileUrl) {
            const modalContent = document.getElementById('pdfViewerContent');
            modalContent.innerHTML = `
                <iframe
                    src="{{ asset('pdfjs/viewer.html') }}?file=${encodeURIComponent(fileUrl)}"
                    style="width:100%; height:100vh; border:none;">
                </iframe>
            `;
        }

        function downloadFile(fileUrl, filename) {
            const btn = event.currentTarget;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Downloading...';

            let progressContainer = document.getElementById('progressContainer');
            let progressBar = document.getElementById('progressBar');

            if (!progressContainer) {
                progressContainer = document.createElement('div');
                progressContainer.id = 'progressContainer';
                progressContainer.innerHTML = `
                    <div class="progress mt-2" style="height: 20px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped bg-info" role="progressbar" style="width: 0%">0%</div>
                    </div>
                `;
                btn.parentNode.insertBefore(progressContainer, btn.nextSibling);
                progressBar = progressContainer.querySelector('#progressBar');
            }

            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';

            fetch(fileUrl)
                .then(response => {
                    if (!response.ok) throw new Error("Download failed");
                    const contentLength = response.headers.get('content-length');
                    if (!contentLength) throw new Error("Content-Length header not found");

                    const total = parseInt(contentLength, 10);
                    let loaded = 0;
                    const reader = response.body.getReader();
                    const chunks = [];

                    function read() {
                        return reader.read().then(({
                            done,
                            value
                        }) => {
                            if (done) {
                                const blob = new Blob(chunks, {
                                    type: response.headers.get('content-type') ||
                                        'application/octet-stream'
                                });
                                if (window.navigator && window.navigator.msSaveOrOpenBlob) {
                                    window.navigator.msSaveOrOpenBlob(blob, filename);
                                } else {
                                    const downloadUrl = URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    a.href = downloadUrl;
                                    a.download = filename || 'download.pdf';
                                    document.body.appendChild(a);
                                    a.click();
                                    document.body.removeChild(a);
                                    URL.revokeObjectURL(downloadUrl);
                                }
                                setTimeout(() => {
                                    progressContainer.style.display = 'none';
                                    showToast('Download complete!');
                                }, 500);
                                return;
                            }
                            chunks.push(value);
                            loaded += value.length;
                            const percent = Math.round((loaded / total) * 100);
                            progressBar.style.width = percent + '%';
                            progressBar.textContent = percent + '%';
                            return read();
                        });
                    }
                    return read();
                })
                .catch(error => {
                    progressContainer.style.display = 'none';
                    showToast("Gagal mendownload file " + filename, "error");
                    console.error(error);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML =
                        `<svg xmlns="https://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-download" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>`;
                });
        }

        function showToast(message, status) {
            const bgClass = status === 'error' ? 'bg-danger' : 'bg-success';
            const toast = document.createElement('div');
            toast.className = `toast position-fixed bottom-0 end-0 m-3 ${bgClass} text-white`;
            toast.role = 'alert';
            toast.style.zIndex = 9999;
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, {
                delay: 3000
            });
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        }
    </script>

    <script>
        $(document).ready(function() {
            let modalTargetForm = null;
            let modalAction = null;

            const actionColors = {
                approve: 'success',
                rejected: 'danger',
                revision: 'yellow',
                close: 'secondary',
                submit: 'primary',
            };

            $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
                if (jqxhr.status === 401 || jqxhr.status === 419) {
                    window.location.href = "{{ route('login') }}";
                }
            });

            $(document).on('click', '[data-bs-toggle="global-action-modal"]', function() {
                const $btn = $(this);
                modalAction = $btn.data('action');
                modalTargetForm = $($btn.data('form'));
                const title = $btn.data('title') || 'Confirm Action';
                const message = $btn.data('message') || 'Are you sure you want to continue?';
                const color = actionColors[modalAction] || 'primary';

                $('#global-modal-title').text(title);
                $('#global-modal-body-text').text(message);
                $('#global-confirm-action-btn')
                    .removeClass('btn-primary btn-success btn-danger btn-yellow btn-secondary')
                    .addClass('btn-' + color);
                $('.modal-status')
                    .removeClass('bg-primary bg-success bg-danger bg-yellow bg-secondary')
                    .addClass('bg-' + color);

                const modal = new bootstrap.Modal(document.getElementById('global-action-modal'));
                modal.show();
            });

            $(document).on('click', '#global-confirm-action-btn', function() {
                if (!modalTargetForm) return;
                const actionInput = modalTargetForm.find('input[name="action_type"]');
                if (actionInput.length) actionInput.val(modalAction);

                const isSignOff = $(this).data('sign-off');
                const commentInput = modalTargetForm.find('textarea[name="comment"]');
                const comment = commentInput.val()?.trim();

                if ((modalAction === 'rejected' || modalAction === 'revision') && (isSignOff == true) && !
                    comment) {
                    $('#global-action-modal').modal('hide');
                    if (typeof showToast === 'function') {
                        showToast('A comment is required to Reject or Revision this requisition.', 'error');
                    } else {
                        alert('A comment is required for this action.');
                    }
                    return;
                }
                modalTargetForm.submit();
            });
        });

        window.confirmModal = function(title, message, callback, danger = false) {
            const color = danger ? 'danger' : 'primary';
            $('#global-modal-title').text(title);
            $('#global-modal-body-text').text(message);
            $('#global-confirm-action-btn')
                .removeClass('btn-primary btn-success btn-danger btn-yellow btn-secondary')
                .addClass('btn-' + color);
            $('.modal-status')
                .removeClass('bg-primary bg-success bg-danger bg-yellow bg-secondary')
                .addClass('bg-' + color);

            const modalEl = document.getElementById('global-action-modal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            $('#global-confirm-action-btn').off('click.confirm').on('click.confirm', function() {
                modal.hide();
                $('#global-confirm-action-btn').off('click.confirm');
                if (typeof callback === 'function') callback();
            });

            $(modalEl).off('hidden.bs.modal.confirm').on('hidden.bs.modal.confirm', function() {
                $('#global-confirm-action-btn').off('click.confirm');
                $(modalEl).off('hidden.bs.modal.confirm');
            });
        };

        window._loadedAssets = window._loadedAssets || {};

        function isFileLoaded(url) {
            return !!document.querySelector(`script[src="${url}"], link[href="${url}"]`);
        }

        function loadFile(url) {
            return new Promise((resolve, reject) => {
                if (window._loadedAssets[url]) {
                    return resolve();
                }
                if (isFileLoaded(url)) {
                    window._loadedAssets[url] = true;
                    return resolve();
                }

                let el;
                if (url.endsWith('.js')) {
                    el = document.createElement('script');
                    el.src = url;
                    el.async = true;
                    el.onload = () => {
                        window._loadedAssets[url] = true;
                        resolve();
                    };
                    el.onerror = () => reject(new Error(`[Loader] Gagal load JS: ${url}`));
                    document.head.appendChild(el);
                } else if (url.endsWith('.css')) {
                    el = document.createElement('link');
                    el.rel = 'stylesheet';
                    el.href = url;
                    el.onload = () => {
                        window._loadedAssets[url] = true;
                        resolve();
                    };
                    el.onerror = () => reject(new Error(`[Loader] Gagal load CSS: ${url}`));
                    document.head.appendChild(el);
                } else {
                    resolve();
                }
            });
        }

        async function loadAssets(list = []) {
            // OPTIMIZED: Download assets in parallel at the same time using Promise.all
            // instead of slow, sequential one-by-one downloading.
            await Promise.all(list.map(url => loadFile(url)));
        }

        async function waitLibrary(checkFn, name, timeout = 8000) {
            return new Promise((resolve, reject) => {
                const start = Date.now();
                const timer = setInterval(() => {
                    if (checkFn()) {
                        clearInterval(timer);
                        resolve();
                    } else if (Date.now() - start > timeout) {
                        clearInterval(timer);
                        reject(`❌ Library "${name}" timeout`);
                    }
                }, 200);
            });
        }

        window.addEventListener('load', async () => {
            // OPTIMIZED: Prioritize light critical assets (jQuery Toast and SweetAlert2)
            // to show the Select Location popup instantly.
            const criticalLibs = [
                "{{ asset('assets/vendor/cdn-fallback/jquery.toast.min.js') }}",
                "{{ asset('assets/vendor/cdn-fallback/jquery.toast.min.css') }}",
                "{{ asset('assets/vendor/cdn-fallback/sweetalert2.all.min.js') }}",
            ];

            // Heavy, non-critical libraries are deferred to load in the background in parallel.
            const nonCriticalLibs = [
                "{{ asset('assets/vendor/cdn-fallback/suneditor.min.js') }}",
                "{{ asset('assets/vendor/cdn-fallback/suneditor.min.css') }}",
                "{{ asset('assets/vendor/cdn-fallback/jquery.datetimepicker.full.min.js') }}",
                "{{ asset('assets/vendor/cdn-fallback/jquery.datetimepicker.min.css') }}",
                "{{ asset('assets/vendor/cdn-fallback/loadingoverlay.min.js') }}",
                "{{ asset('assets/vendor/cdn-fallback/fancybox.umd.js') }}",
                "{{ asset('assets/vendor/cdn-fallback/fancybox.css') }}",
            ];

            try {
                // Load critical assets first (extremely fast, ~50ms in parallel)
                await loadAssets(criticalLibs);

                // Instantly trigger location session check and display the popup
                (async function() {
                    try {
                        await waitLibrary(() => window.Swal, 'sweetalert2');
                        getLocationSession();
                    } catch (err) {
                        console.error(err);
                    }
                })();

                // Load the heavy assets in the background in parallel without blocking the location popup
                loadAssets(nonCriticalLibs).then(() => {
                    (async function() {
                        try {
                            await waitLibrary(() => $.fn.datetimepicker, 'datetimepicker');
                            $('.datetime').datetimepicker({
                                timepicker: true,
                                timepickerIncrement: 30,
                                format: 'Y-m-d H:i:s'
                            });
                        } catch (err) {
                            console.error(err);
                        }

                        try {
                            await waitLibrary(() => window.Fancybox, 'Fancybox');
                            Fancybox.bind('[data-fancybox]', {});
                        } catch (err) {
                            console.error(err);
                        }
                    })();
                }).catch(err => console.error(err));

            } catch (err) {
                console.error('Error loading critical assets:', err);
            }
        });

        function selectLocation() {
            $('#location-preloader').fadeOut();
            Swal.fire({
                title: "Select Your Location",
                html: '<div><select class="form-control my-4" id="selectLocationSession"></select></div>',
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: function() {
                    $('#selectLocationSession').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Please select Location',
                        dropdownParent: Swal.getHtmlContainer(),
                        ajax: {
                            url: "{{ config('WHSConfig.master.MASTER_URL') . 'api/getmaster_locationbyparams' }}",
                            type: "get",
                            dataType: 'json',
                            delay: 500,
                            data: function(params) {
                                return {
                                    _token: $('meta[name="csrf-token"]').attr('content'),
                                    search: params.term,
                                    'where[group_type]': 'warehouse',
                                    'set[field][]': 'loc_code',
                                    'set[text][]': 'loc_code',
                                    'set[text][|]': 'loc_name',
                                    'order[column]': 'loc_name',
                                    'order[direction]': 'asc'
                                };
                            },
                            processResults: function(response) {
                                return {
                                    results: response
                                };
                            },
                            cache: true
                        }
                    }).on("select2:select", function(e) {
                        const dataSelected = e.params.data;
                        $.ajax({
                            type: 'POST',
                            url: "{{ Route::has('setlocationsession') ? route('setlocationsession') : url('/set-location-fallback') }}",
                            data: {
                                location_id: dataSelected.id,
                                loc_name: dataSelected.text,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(data) {
                                $('.session_selected_location').html(dataSelected.text);
                                Swal.showLoading();
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            }
                        });
                    });
                }
            });
        };

        function getLocationSession() {
            $.ajax({
                type: 'GET',
                url: "{{ Route::has('getlocationsession') ? route('getlocationsession') : url('/set-location-fallback') }}",

                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.loc_name == 'None' || response.loc_name == '') {
                        $('.session_selected_location').html(response.loc_name);
                        selectLocation();
                    } else {
                        $('.session_selected_location').html(response.loc_name);
                    }
                }
            });
        }

        $(".changeLocation").click(function() {
            selectLocation();
        });
    </script>

</body>

</html>
