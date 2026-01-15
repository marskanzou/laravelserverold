@extends('layouts.main')
@section('title', 'Banners')
@section('page-title')
<div class="page-title">
  <div class="row">
    <div class="col-12 col-md-6 order-md-1 order-last">
      <h4>@yield('title')</h4>
    </div>
  </div>
</div>
@endsection
@section('content')
<section class="section">
  <div class="row">
    @can('banner-create')
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          {!! Form::open(['url' => route('banner.store'), 'files' => true, 'id' => 'banner-form']) !!}
            <div class="form-group mb-2">
              {{ Form::label('title', 'Title') }}
              {{ Form::text('title', null, ['class' => 'form-control', 'placeholder' => 'Banner Title', 'required' => true]) }}
            </div>
            <div class="form-group mb-2">
              {{ Form::label('image', 'Image') }}
              {{ Form::file('image', ['class' => 'form-control', 'accept' => 'image/*', 'required' => true]) }}
            </div>
            <div class="form-group mb-2">
              {{ Form::label('item', 'Item') }}
              <select name="item" class="form-select select2">
                <option value="">Select Advertisement</option>
                @foreach($items as $item)
                  <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="text-center my-2"><strong>OR</strong></div>
            <div class="form-group mb-2">
              {{ Form::label('category_id', 'Category') }}
              <select name="category_id" class="form-select">
                <option value="">Select a Category</option>
                @include('category.dropdowntree', ['categories' => $categories])
              </select>
            </div>
            <div class="text-center my-2"><strong>OR</strong></div>
            <div class="form-group mb-2">
              {{ Form::label('link', 'Third Party Link') }}
              {{ Form::text('link', '', ['class' => 'form-control', 'placeholder' => 'Link']) }}
            </div>
            <div class="form-group mb-2">
              {{ Form::label('status', 'Status') }}
              {{ Form::select('status', [1 => 'Active', 0 => 'Inactive'], 1, ['class' => 'form-select']) }}
            </div>
            <div class="d-flex justify-content-end">
              {{ Form::submit('Save', ['class' => 'btn btn-primary']) }}
            </div>
          {!! Form::close() !!}
        </div>
      </div>
    </div>
    @endcan
    <div class="{{ auth()->user()->can('banner-create') ? 'col-md-8' : 'col-md-12' }}">
      <div class="card">
        <div class="card-body">
          <table class="table table-bordered" id="table_list"
                 data-toggle="table"
                 data-url="{{ route('banner.list') }}"
                 data-pagination="true"
                 data-search="true"
                 data-side-pagination="server"
                 data-page-list="[5,10,20,50,100]"
                 data-sort-name="id"
                 data-sort-order="desc"
                 data-show-refresh="true"
                 data-show-columns="true"
                 data-query-params="queryParams"
                 data-id-field="id"
                 data-escape="false">
            <thead>
              <tr>
                <th data-field="id" data-align="center" data-sortable="true">ID</th>
                <th data-field="title" data-align="center">Title</th>
                <th data-field="image" data-align="center" data-formatter="imageFormatter">Image</th>
                <th data-field="model.name" data-align="center">Item / Category</th>
                <th data-field="third_party_link" data-align="center">Third Party Link</th>
                @canany(['banner-edit','banner-delete'])
                <th data-field="operate" data-align="center" data-formatter="operateFormatter" data-escape="false">Actions</th>
                @endcanany
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@section('script')
<script>
const baseUrl = "{{ url('banner') }}";

function queryParams(params) {
  return {
    limit: params.limit,
    offset: params.offset,
    sort: params.sort,
    order: params.order,
    search: params.search
  };
}

function imageFormatter(value) {
  return value ? `<img src="${value}" style="width:100px;border-radius:6px;">` : '';
}

function operateFormatter(value, row) {
  //let editBtn = `<a href="${baseUrl}/${row.id}/edit" class="btn btn-sm btn-primary me-1"><i class="bi bi-pencil"></i></a>`;
  let deleteBtn = `<button class="btn btn-sm btn-danger delete-banner" data-id="${row.id}"><i class="bi bi-trash"></i></button>`;
  return deleteBtn;
}

$(document).ready(function() {
  console.log('Banner scripts loaded');
  $(document).on('click', '.delete-banner', function() {
    let id = $(this).data('id');
    if (!id) return;
    if (!confirm('هل أنت متأكد من حذف هذا البانر؟')) return;
    $.ajax({
      url: `${baseUrl}/${id}`,
      type: 'DELETE',
      data: { _token: '{{ csrf_token() }}' },
      success: function(res) {
        if (res && (res.success === true || res.status === 'success')) {
          $('#table_list').bootstrapTable('refresh');
        } else {
          alert(res.message || 'Failed to delete banner.');
        }
      },
      error: function(xhr) {
        console.error(xhr);
        alert('حدث خطأ ما أثناء الحذف!');
      }
    });
  });
});
</script>
@endsection
