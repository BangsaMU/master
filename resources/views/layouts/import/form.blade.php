@php
    $theme = config('app.themes');
@endphp

@if ($theme == '_meridian')
    <dialog class="dialog hidden" data-stisla-dialog id="importItemDiv" data-state="closed" aria-labelledby="importModalTitle">
        <div class="dialog__backdrop" data-stisla-dialog-close data-dismiss="modal" onclick="importFnClose()"></div>
        <div class="dialog__panel max-w-lg w-full">
            <div class="dialog__content">
                <header class="dialog__header flex items-center justify-between p-4 border-b border-border">
                    <h3 class="dialog__title font-bold text-lg m-0 text-foreground" id="importModalTitle">Import Items</h3>
                    <button type="button" class="button button--ghost button--neutral button--icon-only button--sm" data-stisla-dialog-close data-dismiss="modal" onclick="importFnClose()">
                        <i class="fas fa-times"></i>
                    </button>
                </header>
                <form action="{{ $data['page']['import']['post'] }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="dialog__body p-6 flex flex-col gap-4">
                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground">Choose File (Excel / CSV)</label>
                            <input type="file" name="file"
                                class="input w-full @error('file') border-danger @enderror" required
                                accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                            @error('file')
                                <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                            @enderror
                            @include('master::components.importloading')
                        </div>
                    </div>
                    <footer class="dialog__footer flex items-center justify-between p-4 border-t border-border bg-surface-2">
                        <a href="{{ $data['page']['import']['template'] }}" class="button button--neutral button--outline button--sm" download>
                            <i class="fas fa-download me-1"></i> Template
                        </a>
                        <div class="flex items-center gap-2">
                            <button type="button" class="button button--neutral button--ghost button--sm" data-stisla-dialog-close data-dismiss="modal" onclick="importFnClose()">Cancel</button>
                            <button type="submit" class="button button--primary button--sm font-semibold">Import Items</button>
                        </div>
                    </footer>
                </form>
            </div>
        </div>
    </dialog>
@else
    <div class="card card-outline card-primary d-none" id="importItemDiv">
        <h4 class="card-header">Import</h4>
        <div class="card-body">
            <form action="{{ $data['page']['import']['post'] }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="form-group row">
                    <div class="col">
                        <label>Choose File</label>
                        <input type="file" name="file"
                            class="form-control @error('file') is-invalid @enderror" required
                            accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                        @error('file')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                        @include('master::components.importloading')
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Import Items</button>
                <a href="{{ $data['page']['import']['template'] }}" class="btn btn-primary float-right"
                    download>
                    Template
                </a>
            </form>
        </div>
    </div>
@endif
