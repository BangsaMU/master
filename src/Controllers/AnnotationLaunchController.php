<?php

namespace Bangsamu\Master\Controllers;

use Bangsamu\Master\Requests\AnnotationOpenRequest;
use Bangsamu\Master\Services\AnnotationLaunchTokenService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AnnotationLaunchController extends Controller
{
    /**
     * Buat token launch untuk user yang sedang login lalu arahkan ke viewer annotation.
     * Navigasi top-level, jadi cookie session app terkirim normal dan user yang belum login
     * diarahkan ke halaman login lalu kembali ke sini (url.intended).
     */
    public function open(AnnotationOpenRequest $request, AnnotationLaunchTokenService $tokens)
    {
        $fileUrl = $request->fileUrl();
        $token = $tokens->issue($request->user(), $fileUrl, $request->session()->getId(), Auth::getDefaultDriver());

        return redirect()->away($tokens->viewerUrl($fileUrl, $token, $request->callbackUrl()));
    }
}
