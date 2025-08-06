<?php

namespace App\View\Components;

use Illuminate\View\Component;

class PdfViewer extends Component
{
    public function __construct(
        public string $url,
        public string $width = '100%',
        public string $height = '100vh',
        public int $chunkSize = 65536,
    ) {}

    public function render()
    {
        return view('components.pdf-viewer');
    }
}
