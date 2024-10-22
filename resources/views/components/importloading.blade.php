<span id="importloading" class="text-sm text-primary float-right mt-2" style="display: none;">
    <img class="img-responsive" src="https://upload.wikimedia.org/wikipedia/commons/c/c7/Loading_2.gif?20170503175831" alt="loading" height="25" width="25">
    Please wait, Import being processed!!
</span>

@push('js')
<script>
    $(document).ready(function() {
        $("button[type=submit]").click(function(){
            let value = $("input[required]").val();
            if(value) {
                $('#importloading').show();
            }
        }); 
    });
</script>
@endpush