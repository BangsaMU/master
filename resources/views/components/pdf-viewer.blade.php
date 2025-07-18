<!-- resources/views/components/pdf-viewer.blade.php -->
<div class="pdf-viewer-container flex justify-center items-center h-screen bg-gray-100 relative">
    <div id="pdf-loader" class="absolute top-0 left-0 right-0 bottom-0 flex items-center justify-center bg-white z-50">
        <div class="text-center">
            <div class="loader mb-2 border-t-4 border-b-4 border-blue-500 w-12 h-12 rounded-full animate-spin mx-auto"></div>
            <p class="text-gray-600 text-sm">Memuat dokumen PDF...</p>
        </div>
    </div>

    <div id="pdf-container" class="overflow-auto h-full w-full max-w-5xl p-4 space-y-4">
        <!-- Canvas will be added dynamically here -->
    </div>
</div>

<style>
    .loader {
        border-width: 4px;
        border-style: solid;
        border-color: #ccc;
        border-top-color: #007bff;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<script src="/vendor/pdfjs/pdf.js"></script>
<script>
    const url = "{{ $url ?? '/storage/sample.pdf' }}";
    const container = document.getElementById('pdf-container');
    const loader = document.getElementById('pdf-loader');

    const pdfjsLib = window['pdfjs-dist/build/pdf'];
    pdfjsLib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.js';

    let currentPage = 1;
    let totalPage = 0;
    let pdfDoc = null;
    let isRendering = false;
    let renderedPages = new Set();

    function renderPage(num) {
        if (renderedPages.has(num) || isRendering) return;
        isRendering = true;

        pdfDoc.getPage(num).then(page => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const viewport = page.getViewport({ scale: 1.5 });

            canvas.height = viewport.height;
            canvas.width = viewport.width;
            canvas.style.display = 'block';
            canvas.style.margin = '0 auto';

            container.appendChild(canvas);

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };

            page.render(renderContext).promise.then(() => {
                isRendering = false;
                renderedPages.add(num);
                if (num < totalPage) {
                    observePageTrigger(num + 1);
                }
            });
        });
    }

    function observePageTrigger(pageNum) {
        const sentinel = document.createElement('div');
        sentinel.className = 'w-full h-10';
        container.appendChild(sentinel);

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                observer.disconnect();
                renderPage(pageNum);
                sentinel.remove();
            }
        });

        observer.observe(sentinel);
    }

    pdfjsLib.getDocument({ url: url, rangeChunkSize: 65536 }).promise.then(pdf => {
        loader.style.display = 'none';
        pdfDoc = pdf;
        totalPage = pdf.numPages;
        renderPage(currentPage);
    });
</script>
