@php
    $theme = config('app.themes');
    if ($theme == '_meridian') {
        $themeLayout = view()->exists('layouts.meridian')
            ? 'layouts.meridian'
            : 'master::layouts.meridian';
    } elseif ($theme == '_tabler') {
        $themeLayout = view()->exists('layouts.tabler')
            ? 'layouts.tabler'
            : 'master::layouts.tabler';
    } else {
        $themeLayout = 'adminlte::page';
    }
@endphp
@extends($themeLayout)

@section('title', 'Profile Settings')

@section('header')
    <h1 class="page__title font-bold text-2xl m-0">Profile Settings</h1>
    <p class="page__description text-sm text-muted-foreground mt-1">
        Manage your account information and preferences
    </p>
@endsection

@section('content')
<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-4">
        <div class="card">
            <div class="card__header p-4 border-b border-border">
                <h3 class="card__title font-bold text-base m-0">Profile Information</h3>
                <p class="text-xs text-muted-foreground mt-1">
                    Update your account's profile information and email address.
                </p>
            </div>
            <div class="card__body p-6">
                <div class="flex items-center gap-3">
                    <span class="avatar avatar--lg avatar--circle">
                        <span class="avatar__fallback">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                    </span>
                    <div>
                        <div class="font-medium text-foreground text-sm">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-muted-foreground">{{ auth()->user()->email }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-8 flex flex-col gap-4">
        <div class="card">
            <div class="card__header p-4 border-b border-border">
                <h3 class="card__title font-bold text-base m-0">Update Profile</h3>
            </div>
            <div class="card__body p-6">
                <form id="form-profile" method="post" action="{{ route('profile.update') }}" class="flex flex-col gap-4">
                    @csrf
                    @method('patch')

                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground">Name</label>
                        <input type="text" name="name" class="input w-full @error('name') border-danger @enderror"
                            value="{{ old('name', auth()->user()->name) }}" required autocomplete="name">
                        @error('name')
                            <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground">Email address</label>
                        <input type="email" name="email" class="input w-full @error('email') border-danger @enderror"
                            value="{{ old('email', auth()->user()->email) }}" required autocomplete="email">
                        @error('email')
                            <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Signature Section --}}
                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground">Signature Preview</label>
                            <img id="signature-image-preview" src="{{ auth()->user()->signature ?? 'https://placehold.co/400x200/f0f2f7/99a1b7?text=No+Signature' }}" alt="{{ auth()->user()->signature ?? 'No Signature' }}" class="w-full max-h-48 border border-border rounded p-1 object-contain bg-surface-2">
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground">Signature</label>
                            <div class="card">
                                <div class="card__body p-3">
                                    <input type="hidden" name="signature" id="signature-data" autocomplete="off">
                                    <div class="relative">
                                        <div class="absolute top-1 end-1 z-10">
                                            <button type="button" class="button button--danger button--ghost button--icon-only button--sm" id="signature-clear-btn" title="Clear signature">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <canvas id="signature-canvas" class="w-full h-32 border border-border rounded" style="touch-action: none; user-select: none;"></canvas>
                                    </div>
                                    <div class="mt-2">
                                        <label class="text-xs text-muted-foreground block mb-1">Or upload image:</label>
                                        <input type="file" class="input w-full text-xs" id="signature-file-input" accept="image/png, image/jpeg">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Initials/Paraffe Section --}}
                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground">Initials/Paraffe Preview</label>
                            <img id="paraf-image-preview" src="{{ auth()->user()->paraf ?? 'data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAABkBAMAAACWddTDAAAAG1BMVEXv7++qqqrd3d27u7vm5uaysrLMzMzDw8PV1dWB9FXoAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABGElEQVRYhe3TsW6DMBSF4YMLgRGME1ZQ1b2RGrJe1D4AbUSUkTRiz1DEWpQ+eO0iunWwYnXJ/SQjT7+4lg0wxhhjjDHGGLtZlcPWs8PW9eT6CUIvM6Ps3+5T8vuUsNhVhIve2Fi+b1CUGzItvT88nMP9pcY4rClKu9qqlaGiAXetaWUoPkUd5L7CHkcKzjhYtSQ8kogef2bUXT8WEAoKBekpB6uWgvcSQ8SmpfTyY5xelf5WVCRJemUrzL5My6OxaXa2rd8Zp5ZHMDPq/yKr0tSaz35qVRQplPrsvRZb69Z8J6ZWUPYrFENCi+wkrVvzXZ1aovnoEDUjoUtzq9YfcodPfYveWeuYrJy1wmXrrMUYY4wxxtj/+gb7wytaOvUBxgAAAABJRU5ErkJggg==' }}" alt="{{ auth()->user()->paraf ?? 'No Initials' }}" class="w-full max-h-48 border border-border rounded p-1 object-contain bg-surface-2">
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground">Initials/Paraffe</label>
                            <div class="card">
                                <div class="card__body p-3">
                                    <input type="hidden" name="paraf" id="paraf-data" autocomplete="off">
                                    <div class="relative">
                                        <div class="absolute top-1 end-1 z-10">
                                            <button type="button" class="button button--danger button--ghost button--icon-only button--sm" id="paraf-clear-btn" title="Clear initials">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <canvas id="paraf-canvas" class="w-full h-32 border border-border rounded" style="touch-action: none; user-select: none;"></canvas>
                                    </div>
                                    <div class="mt-2">
                                        <label class="text-xs text-muted-foreground block mb-1">Or upload image:</label>
                                        <input type="file" class="input w-full text-xs" id="paraf-file-input" accept="image/png, image/jpeg">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                        <button id="save-form" type="button" class="button button--primary font-semibold ms-auto">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__header p-4 border-b border-border">
                <h3 class="card__title font-bold text-base m-0">Update Password</h3>
                <p class="text-xs text-muted-foreground mt-1">Ensure your account is using a strong password for security.</p>
            </div>
            <div class="card__body p-6">
                <form method="post" action="{{ route('password.update') }}" class="flex flex-col gap-4">
                    @csrf
                    @method('put')

                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground">Current Password</label>
                        <input type="password" name="current_password"
                            class="input w-full @error('current_password') border-danger @enderror"
                            autocomplete="current-password">
                        @error('current_password')
                            <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground">New Password</label>
                        <input type="password" name="password"
                            class="input w-full @error('password') border-danger @enderror" autocomplete="new-password">
                        @error('password')
                            <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="input w-full"
                            autocomplete="new-password">
                    </div>

                    <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                        <button type="submit" class="button button--primary font-semibold">Update Password{{ config('SsoConfig.main.ACTIVE') ? ' SSO' : null }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function setupSignaturePad(options) {
        const {
            canvasId,
            clearBtnId,
            fileInputId,
            dataInputId,
            imagePreviewId,
            existingData
        } = options;

        const canvas = document.getElementById(canvasId);
        const clearButton = document.getElementById(clearBtnId);
        const fileInput = document.getElementById(fileInputId);
        const dataInput = document.getElementById(dataInputId);
        const imagePreview = document.getElementById(imagePreviewId);

        if (!canvas) return null;

        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);

        const signaturePadInstance = new SignaturePad(canvas, {
            penColor: "rgb(0, 0, 0)"
        });

        if (existingData) {
            dataInput.value = existingData;
            if (existingData.startsWith('data:image')) {
                try {
                    signaturePadInstance.fromDataURL(existingData, {
                        width: canvas.width / ratio,
                        height: canvas.height / ratio
                    });
                    imagePreview.src = existingData;
                } catch (e) {
                    console.error(`Error loading existing data for ${canvasId}:`, e);
                }
            } else {
                imagePreview.src = existingData;
                signaturePadInstance.clear();
            }
        }

        clearButton.addEventListener('click', function() {
            signaturePadInstance.clear();
            fileInput.value = '';
            dataInput.value = '';
            imagePreview.src = 'https://placehold.co/400x200/f0f2f7/99a1b7?text=No+Data';
        });

        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const dataURL = e.target.result;
                    imagePreview.src = dataURL;
                    signaturePadInstance.clear();
                    dataInput.value = dataURL;
                };
                reader.readAsDataURL(file);
            }
        });

        return {
            signaturePadInstance: signaturePadInstance,
            dataInput: dataInput
        };
    }

    const signatureHandler = setupSignaturePad({
        canvasId: 'signature-canvas',
        clearBtnId: 'signature-clear-btn',
        fileInputId: 'signature-file-input',
        dataInputId: 'signature-data',
        imagePreviewId: 'signature-image-preview',
        existingData: @json(auth()->user()->signature)
    });

    const parafHandler = setupSignaturePad({
        canvasId: 'paraf-canvas',
        clearBtnId: 'paraf-clear-btn',
        fileInputId: 'paraf-file-input',
        dataInputId: 'paraf-data',
        imagePreviewId: 'paraf-image-preview',
        existingData: @json(auth()->user()->paraf)
    });

    const mainForm = document.getElementById('form-profile');
    const saveButton = document.getElementById('save-form');

    saveButton.addEventListener('click', function(event) {
        if (signatureHandler && !signatureHandler.signaturePadInstance.isEmpty()) {
            signatureHandler.dataInput.value = signatureHandler.signaturePadInstance.toDataURL('image/png');
        }
        if (parafHandler && !parafHandler.signaturePadInstance.isEmpty()) {
            parafHandler.dataInput.value = parafHandler.signaturePadInstance.toDataURL('image/png');
        }
        mainForm.submit();
    });
});
</script>
@endpush
