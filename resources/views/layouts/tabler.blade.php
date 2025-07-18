<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel')) }}</title>

    @php
        $sidebarEnabled = Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('appearance.sidebar', false);
    @endphp

    <link rel="stylesheet" href="{{ asset('tabler.min.css') }}" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <link rel="stylesheet" href="{{ asset('datatables/dataTables.bootstrap5.css') }}">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css" />
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>

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
</head>
<body class="{{ $sidebarEnabled ? 'has-sidebar' : '' }}">
    <div class="page">
        @if($sidebarEnabled)
            @include('master::layouts.sidebar')
        @endif

        <div class="page-wrapper">
            @include('master::layouts.navbar-top')
            @if(!$sidebarEnabled)
                @include('master::layouts.navbar-menu')
            @endif

            @hasSection('header')
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row align-items-center">
                        <div class="col">
                            @yield('header')
                        </div>
                        @hasSection('header-actions')
                        <div class="col-auto ms-auto d-print-none">
                            <div class="btn-list">
                                @yield('header-actions')
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
            <div class="page-body">
                <div class="container-xl">
                    @yield('content')
                </div>
            </div>
            @include('master::layouts.footer')
        </div>
    </div>

    @php
        // We prepare the variables for the toast first.
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
            // Build an HTML list of all errors
            $errorList = '<ul>';
            foreach ($errors->all() as $error) {
                $errorList .= '<li>' . e($error) . '</li>';
            }
            $errorList .= '</ul>';

            $toastMessage = $errorList; // The message is now HTML
            $toastType = 'error';
            $toastTitle = 'Validation Error';
        }
    @endphp

    {{-- Only show the toast if there is a message to display --}}
    @if($toastMessage)
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1056">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
                <div class="toast-header {{ $toastType == 'success' ? 'bg-success text-white' : 'bg-danger text-white' }}">
                    <strong class="me-auto">{{ $toastTitle }}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
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
                    <!-- Preloader atau iframe akan dimasukkan di sini -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3">Memuat PDF...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('tabler.min.js') }}"></script>
    <script src="{{ asset('jquery.js') }}"></script>
    <script src="{{ asset('datatables/dataTables.js') }}"></script>
    <script src="{{ asset('datatables/dataTables.bootstrap5.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    @stack('scripts')
    @stack('js')

    <script>
        $(function() {
            // Auto-hide toast notifications after 5 seconds
            setTimeout(() => {
                $('.toast').fadeOut('slow');
            }, 5000);
        });

        function loadPdfViewer(fileUrl) {
            const modalContent = document.getElementById('pdfViewerContent');
            modalContent.innerHTML = `
                <iframe
                    src="{{asset('pdfjs/viewer.html')}}?file=${encodeURIComponent(fileUrl)}"
                    style="width:100%; height:100vh; border:none;">
                </iframe>
            `;
        }

        function downloadFile(fileUrl, filename) {
            const btn = event.currentTarget;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Downloading...';

            // Setup progress bar
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
                        return reader.read().then(({ done, value }) => {
                            if (done) {
                                const blob = new Blob(chunks, { type: response.headers.get('content-type') || 'application/octet-stream' });

                                // 👉 Tambahkan pengecekan msSaveOrOpenBlob
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

                                // Hide progress bar and show toast
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
                    showToast("Gagal mendownload file "+filename,"error");
                    console.error(error);
                    progressBar.classList.remove('bg-info');
                    progressBar.classList.add('bg-danger');
                    progressBar.textContent = 'Error';
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-download" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                        <path d="M7 11l5 5l5 -5" />
                        <path d="M12 4l0 12" />
                        </svg>
                    `;
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
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            document.body.appendChild(toast);

            const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
            bsToast.show();

            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }


    </script>
    </body>
</html>
