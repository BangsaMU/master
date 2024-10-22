{{-- add item comment --}}
<div class="modal fade" id="COMMENT-MODAL" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">List Comment by item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table width="100%" id="{{ $data['page']['slug'] }}_tabel_comment"
                    class="table table-hover table-bordered table-striped" style="width:100%">

                </table>
            </div>
            @if (@$data['page']['global_disable']==false)
                <div class="form-group m-3">
                    <input type="hidden" id="requisition_detail_id" name="requisition_detail_id" />
                    <label class="font-weight-normal" id="requisition_detail_commentTitle">Leave a Comment (<small
                            class="text-danger">*can be empty</small>)</label>
                    <textarea name="comment" class="form-control" id="requisition_detail_input_comment" cols="40"></textarea>
                    <small id="requisition_detail_commentSmallAlert" class="text-danger"
                        style="margin-left: 5px;"></small>
                </div>
            @endif
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                @if (@$data['page']['global_disable']==false)
                    <button type="submit" class="btn btn-primary" id="commentSubmit">Add Comment</button>
                @endif
            </div>
        </div>
    </div>
</div>

