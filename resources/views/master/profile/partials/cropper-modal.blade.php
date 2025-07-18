<div class="modal fade" id="cropModal{{ $id }}" tabindex="-1" role="dialog" aria-labelledby="cropModalLabel{{ $id }}" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Crop {{ $id }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <img id="image-cropper{{ $id }}" class="img-fluid" />
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="crop{{ $id }}">Crop and Save</button>
      </div>
    </div>
  </div>
</div>
