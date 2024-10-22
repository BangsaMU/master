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
