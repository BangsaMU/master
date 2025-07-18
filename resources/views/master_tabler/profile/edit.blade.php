<!-- resources/views/profile/edit.blade.php -->
@extends('master::layouts.tabler')

@section('header')
<h2 class="page-title">
    Profile Settings
</h2>
<div class="text-muted mt-1">
    Manage your account information and preferences
</div>
@endsection

@section('content')
<div class="row row-cards">
    <div class="col-12 col-md-5 col-xl-4">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Profile Information</h3>
                <div class="card-subtitle mb-3 text-muted">
                    Update your account's profile information and email address.
                </div>

                <div class="mt-3">
                    <div class="d-flex mb-3">
                        <div class="avatar avatar-xl me-3"
                            style="background-image: url(https://www.gravatar.com/avatar/{{ md5(auth()->user()->email) }}?d=mp)">
                        </div>
                        <div>
                            <div class="font-weight-medium">{{ auth()->user()->name }}</div>
                            <div class="text-muted">{{ auth()->user()->email }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-7 col-xl-8">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Update Profile</h3>

                <form id="form-profile" method="post" action="{{ route('profile.update') }}" class="mt-3">
                    @csrf
                    @method('patch')

                    <div class="mb-3">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', auth()->user()->name) }}" required autocomplete="name">
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Email address</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email', auth()->user()->email) }}" required autocomplete="email">
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 row">
                        {{-- Signature Section --}}
                        <div class="col-6"> {{-- Changed to col-6 for side-by-side with Paraf --}}
                            <label class="form-label">Signature Preview</label>
                            <img id="signature-image-preview" src="{{ auth()->user()->signature ?? 'https://via.placeholder.com/400x200.png/f0f2f7/99a1b7?text=No+Signature' }}" alt="{{ auth()->user()->signature ?? 'No Signature' }}" class="img-fluid border rounded mb-3" style="max-height: 200px;">
                        </div>

                        <div class="col-6">
                            <label class="form-label">Signature</label>
                            <div class="card">
                                <div class="card-header">
                                    <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
                                        <li class="nav-item">
                                            <a href="#tabs-signature-draw" class="nav-link active" data-bs-toggle="tab">Draw Signature</a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#tabs-signature-upload" class="nav-link" data-bs-toggle="tab">Upload Signature</a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content">
                                        {{-- Draw Tab --}}
                                        <div class="tab-pane active" id="tabs-signature-draw">
                                            <input type="hidden" name="signature" id="signature-data" autocomplete="off">
                                            <div class="signature position-relative">
                                                <div class="position-absolute top-0 end-0 p-2 z-10">
                                                    <div class="btn btn-icon" id="signature-clear-btn" data-bs-toggle="tooltip" title="Clear signature">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash">
                                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <canvas id="signature-canvas" class="w-100 h-100 border rounded" style="touch-action: none; user-select: none;"></canvas>
                                            </div>
                                        </div>

                                        {{-- Upload Tab --}}
                                        <div class="tab-pane" id="tabs-signature-upload">
                                            <p class="text-muted">Upload a PNG or JPG file of your signature.</p>
                                            <input type="file" class="form-control" id="signature-file-input" accept="image/png, image/jpeg">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 row">
                        {{-- Initials/Paraffe Section --}}
                        <div class="col-6"> {{-- New column for Initials/Paraffe --}}
                            <label class="form-label">Initials/Paraffe Preview</label>
                            <img id="paraf-image-preview" src="{{ auth()->user()->paraf ?? 'data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAABkBAMAAACWddTDAAAAG1BMVEXv7++qqqrd3d27u7vm5uaysrLMzMzDw8PV1dWB9FXoAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABGElEQVRYhe3TsW6DMBSF4YMLgRGME1ZQ1b2RGrJe1D4AbUSUkTRiz1DEWpQ+eO0iunWwYnXJ/SQjT7+4lg0wxhhjjDHGGLtZlcPWs8PW9eT6CUIvM6Ps3+5T8vuUsNhVhIve2Fi+b1CUGzItvT88nMP9pcY4rClKu9qqlaGiAXetaWUoPkUd5L7CHkcKzjhYtSQ8kogef2bUXT8WEAoKBekpB6uWgvcSQ8SmpfTyY5xelf5WVCRJemUrzL5My6OxaXa2rd8Zp5ZHMDPq/yKr0tSaz35qVRQplPrsvRZb69Z8J6ZWUPYrFENCi+wkrVvzXZ1aovnoEDUjoUtzq9YfcodPfYveWeuYrJy1wmXrrMUYY4wxxtj/+gb7wytaOvUBxgAAAABJRU5ErkJggg==' }}" alt="{{ auth()->user()->paraf ?? 'No Initials' }}" class="img-fluid border rounded mb-3" style="max-height: 200px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Initials/Paraffe</label>
                            <div class="card">
                                <div class="card-header">
                                    <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
                                        <li class="nav-item">
                                            <a href="#tabs-paraf-draw" class="nav-link active" data-bs-toggle="tab">Draw Initials</a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#tabs-paraf-upload" class="nav-link" data-bs-toggle="tab">Upload Initials</a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content">
                                        {{-- Draw Tab --}}
                                        <div class="tab-pane active" id="tabs-paraf-draw">
                                            <input type="hidden" name="paraf" id="paraf-data" autocomplete="off">
                                            <div class="paraf position-relative">
                                                <div class="position-absolute top-0 end-0 p-2 z-10">
                                                    <div class="btn btn-icon" id="paraf-clear-btn" data-bs-toggle="tooltip" title="Clear initials">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash">
                                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <canvas id="paraf-canvas" class="w-100 h-100 border rounded" style="touch-action: none; user-select: none;"></canvas>
                                            </div>
                                        </div>

                                        {{-- Upload Tab --}}
                                        <div class="tab-pane" id="tabs-paraf-upload">
                                            <p class="text-muted">Upload a PNG or JPG file of your initials.</p>
                                            <input type="file" class="form-control" id="paraf-file-input" accept="image/png, image/jpeg">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-footer">
                        <button id="save-form" type="button" class="btn btn-primary ms-auto">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h3 class="card-title">Update Password</h3>
                <div class="card-subtitle text-muted mb-3">
                    Ensure your account is using a strong password for security.
                </div>

                <form method="post" action="{{ route('password.update') }}" class="mt-3">
                    @csrf
                    @method('put')

                    <div class="mb-3">
                        <label class="form-label required">Current Password</label>
                        <input type="password" name="current_password"
                            class="form-control @error('current_password') is-invalid @enderror"
                            autocomplete="current-password">
                        @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">New Password</label>
                        <input type="password" name="password"
                            class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                        @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control"
                            autocomplete="new-password">
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">Update Password{{ config('SsoConfig.main.ACTIVE') ? ' SSO' : null }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- <div class="card mt-3">
            <div class="card-body">
                <h3 class="card-title text-danger">Delete Account</h3>
                <div class="card-subtitle text-muted mb-3">
                    Permanently delete your account and all of its data.
                </div>

                <p>
                    Once your account is deleted, all of its resources and data will be permanently deleted. Before
                    deleting your account, please download any data or information that you wish to retain.
                </p>

                <div class="mt-4">
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#delete-account-modal">
                        Delete Account
                    </button>
                </div>

                <!-- Delete Account Modal -->
                <div class="modal fade" id="delete-account-modal" tabindex="-1"
                    aria-labelledby="delete-account-modal-label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="post" action="{{ route('profile.destroy') }}">
                                @csrf
                                @method('delete')

                                <div class="modal-header">
                                    <h5 class="modal-title" id="delete-account-modal-label">Delete Account</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>

                                <div class="modal-body">
                                    <p>Are you sure you want to delete your account? Once your account is deleted, all
                                        of its resources and data will be permanently deleted.</p>

                                    <div class="mb-3">
                                        <label class="form-label required">Password</label>
                                        <input type="password" name="password"
                                            class="form-control @error('password') is-invalid @enderror"
                                            placeholder="Enter your password to confirm"
                                            autocomplete="current-password">
                                        @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Delete Account</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div> --}}
    </div>
</div>

<!-- Display success message if there is one -->
@if (session('status') === 'profile-updated')
<div class="toast show position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true"
    id="profile-updated-toast">
    <div class="toast-header bg-success text-white">
        <strong class="me-auto">Success</strong>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
        Profile information updated successfully.
    </div>
</div>
@endif

@if (session('status') === 'password-updated')
<div class="toast show position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true"
    id="password-updated-toast">
    <div class="toast-header bg-success text-white">
        <strong class="me-auto">Success</strong>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
        Password updated successfully.
    </div>
</div>
@endif

@if ($errors->updatePassword->any())
<div class="toast show position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true"
    id="validation-failed-toast">
    <div class="toast-header bg-danger text-white">
        <strong class="me-auto">Validation Error</strong>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
        Please check the errors on the form.
        <ul class="mt-2 mb-0">
            @foreach ($errors->updatePassword->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- BASIC INITIALIZATIONS ---
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    setTimeout(() => {
        const toast = document.querySelector('.toast');
        if (toast) {
            new bootstrap.Toast(toast).hide();
        }
    }, 3000);

    // --- SHARED UTILITY FUNCTION FOR SIGNATURE/PARAF HANDLING ---
    function setupSignaturePad(options) {
        const {
            canvasId,
            clearBtnId,
            fileInputId,
            dataInputId,
            imagePreviewId,
            drawTabPaneId,
            existingData
        } = options;

        const canvas = document.getElementById(canvasId);
        const clearButton = document.getElementById(clearBtnId);
        const fileInput = document.getElementById(fileInputId);
        const dataInput = document.getElementById(dataInputId);
        const imagePreview = document.getElementById(imagePreviewId);
        const drawTabPane = document.getElementById(drawTabPaneId);

        if (!canvas) {
            console.warn(`Canvas element with ID '${canvasId}' not found. Signature/Paraf functionality might be limited.`);
            return null; // Return null if canvas is not found
        }

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
                    imagePreview.src = 'https://via.placeholder.com/400x200.png/f0f2f7/99a1b7?text=Error+Loading';
                }
            } else {
                imagePreview.src = existingData;
                signaturePadInstance.clear(); // Clear pad if it's a URL, not a drawable Data URL
            }
        } else {
            imagePreview.src = imagePreview.getAttribute('src'); // Keep default placeholder if no existing data
        }


        clearButton.addEventListener('click', function() {
            signaturePadInstance.clear();
            fileInput.value = '';
            dataInput.value = '';
            imagePreview.src = 'https://via.placeholder.com/400x200.png/f0f2f7/99a1b7?text=No+Data';
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
            } else {
                imagePreview.src = 'https://via.placeholder.com/400x200.png/f0f2f7/99a1b7?text=No+Data';
                dataInput.value = '';
            }
        });

        // Event listener for tab changes (re-initialization of pad or clearing)
        const navTabs = document.querySelectorAll(`[href="#${drawTabPaneId}"], [href="#${drawTabPaneId.replace('draw', 'upload')}"]`);
        navTabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (event) {
                const activeTabPaneId = event.target.getAttribute('href');

                if (activeTabPaneId === `#${drawTabPaneId}`) {
                    fileInput.value = '';
                    if (dataInput.value && dataInput.value.startsWith('data:image')) {
                        signaturePadInstance.fromDataURL(dataInput.value, {
                            width: canvas.width / ratio,
                            height: canvas.height / ratio
                        });
                    } else {
                        signaturePadInstance.clear();
                    }
                } else { // Upload tab active
                    signaturePadInstance.clear();
                }
            });
        });

        return {
            signaturePadInstance: signaturePadInstance,
            dataInput: dataInput,
            drawTabPane: drawTabPane
        };
    }

    // --- INITIALIZE SIGNATURE ---
    const signatureHandler = setupSignaturePad({
        canvasId: 'signature-canvas',
        clearBtnId: 'signature-clear-btn',
        fileInputId: 'signature-file-input',
        dataInputId: 'signature-data',
        imagePreviewId: 'signature-image-preview',
        drawTabPaneId: 'tabs-signature-draw',
        existingData: @json(auth()->user()->signature)
    });

    // --- INITIALIZE PARAF ---
    const parafHandler = setupSignaturePad({
        canvasId: 'paraf-canvas',
        clearBtnId: 'paraf-clear-btn',
        fileInputId: 'paraf-file-input',
        dataInputId: 'paraf-data',
        imagePreviewId: 'paraf-image-preview',
        drawTabPaneId: 'tabs-paraf-draw',
        existingData: @json(auth()->user()->paraf) // Assuming auth()->user()->paraf holds the existing paraf data
    });

    // --- FORM SUBMISSION LOGIC ---
    const mainForm = document.getElementById('form-profile'); // Get the form by its ID
    const saveButton = document.getElementById('save-form'); // Get the save button

    saveButton.addEventListener('click', function(event) {
        // Handle Signature data before submitting
        if (signatureHandler && signatureHandler.drawTabPane.classList.contains('active')) {
            if (!signatureHandler.signaturePadInstance.isEmpty()) {
                const dataURL = signatureHandler.signaturePadInstance.toDataURL('image/png');
                signatureHandler.dataInput.value = dataURL;
            } else {
                signatureHandler.dataInput.value = '';
            }
        }

        // Handle Paraf data before submitting
        if (parafHandler && parafHandler.drawTabPane.classList.contains('active')) {
            if (!parafHandler.signaturePadInstance.isEmpty()) {
                const dataURL = parafHandler.signaturePadInstance.toDataURL('image/png');
                parafHandler.dataInput.value = dataURL;
            } else {
                parafHandler.dataInput.value = '';
            }
        }

        // Now submit the form
        mainForm.submit();
    });
});
</script>
@endpush
