<div class="d-flex justify-content-center">
    <a href="{{ route('banner.edit', $banner->id) }}" class="btn btn-sm btn-primary me-1">{{ __('Edit') }}</a>
    <button class="btn btn-sm btn-danger" onclick="deleteBanner({{ $banner->id }})">{{ __('Delete') }}</button>
</div>

<script>
function deleteBanner(id) {
    if(confirm('Are you sure you want to delete this banner?')) {
        $.ajax({
            url:'/banners/' + id,
            type:'DELETE',
            data:{_token:'{{ csrf_token() }}'},
            success:function(response){
                if(response.success){
                    $('#table_list').bootstrapTable('remove',{field:'id', values:[id]});
                } else alert('Failed to delete banner.');
            },
            error:function(){ alert('Error occurred while deleting banner.'); }
        });
    }
}
</script>

