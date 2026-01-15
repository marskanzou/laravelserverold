@extends('layouts.main')
@section('title')
    {{__("Edit Categories")}}
@endsection

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
        <div class="buttons">
            <a class="btn btn-primary" href="{{ route('category.index') }}">< {{__("Back to All Categories")}} </a>
        </div>
        <div class="row">
            <form action="{{ route('category.update', $category_data->id) }}" method="POST" data-parsley-validate enctype="multipart/form-data">
                @method('PUT')
                @csrf
                <input type="hidden" name="edit_data" value={{ $category_data->id }}>
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">{{__("Edit Categories")}}</div>
                        <div class="card-body mt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="name" class="mandatory form-label">{{ __('Name') }}</label>
                                        <input type="text" name="name" id="name" class="form-control" data-parsley-required="true" value="{{ $category_data->name }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="p_category" class="mandatory form-label">{{ __('Parent Category') }}</label>
                                        <select name="parent_category_id" class="form-select form-control select2" id="p_category" data-placeholder="{{ __('Select Category') }}">
                                            @if(isset($parent_category_data) && $parent_category_data->id)
                                                <option value="{{ $parent_category_data->id }}" id="default_opt" selected>
                                                    {{ $parent_category == '' ? 'Root' : $parent_category }}
                                                </option>
                                            @else
                                                <option value="">{{ __('Select Category') }}</option>
                                            @endif

                                            @include('category.dropdowntree', ['categories' => $categories])
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="slug" class="form-label">{{ __('Slug') }} <small>(English Only)</small></label>
                                        <input type="text" name="slug" id="slug" class="form-control" data-parsley-required="true" value="{{ $category_data->slug }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="description" class="mandatory form-label">{{ __('Description') }}</label>
                                    <textarea name="description" id="description" class="form-control" cols="10" rows="5">{{ $category_data->description }}</textarea>
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="status" id="status" value="{{ $category_data->status}}">
                                                <input class="form-check-input status-switch" type="checkbox" role="switch" aria-label="status" name="active" id="required" {{ $category_data->status == 1 ? 'checked' : '' }}>{{ __('Active') }}
                                                <label class="form-check-label" for="status"></label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="is_job_category" value="0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    name="is_job_category"
                                                    id="job_category_switch"
                                                    value="1"
                                                    {{ $category_data->is_job_category == 1 ? 'checked' : '' }}
                                                >
                                                <label class="form-check-label" for="job_category_switch">{{ __('Job Category') }}</label>

                                            </div>
                                        </div>
                                         <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="price_optional" value="0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    name="price_optional"
                                                    id="price_optional_switch"
                                                    value="1"
                                                    {{ $category_data->price_optional == 1 ? 'checked' : '' }}
                                                >
                                                <label class="form-check-label" for="job_category_switch">{{ __('Price Optional') }}</label>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="Field Name" class="mandatory form-label">{{ __('Image') }}</label>
                                        <div class="cs_field_img">
                                            <input type="file" name="image" class="image" style="display: none" accept=" .jpg, .jpeg, .png, .svg">
                                            <img src="{{ empty($category_data->image) ? asset('assets/img_placeholder.jpeg') : $category_data->image }}" alt="" class="img preview-image" id="">
                                            <div class='img_input'>{{__("Browse File")}}</div>
                                        </div>
                                        <div class="input_hint"> {{__("Icon (use 256 x 256 size for better view)")}}</div>
                                        <div class="img_error" style="color:#DC3545;"></div>
                                    </div>
                                </div>

                            </div>
                            @if($languages->isNotEmpty())
                            <hr>
                            <h5>{{ __("Translation") }}</h5>
                            <div class="row">
                                @foreach($languages as $key => $language)
                                    <div class="col-md-6 form-group">
                                        <label for="name_{{$language->id}}" class="form-label">
                                            {{ ($key + 1) . ". " . $language->name }}:
                                        </label>
                                        <input name="translations[{{$language->id}}]" id="name_{{$language->id}}" class="form-control" value="{{ $translations[$language->id] ?? '' }}">
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        </div>
                    </div>
                    <div class="col-md-12 text-end">
                        <input type="submit" class="btn btn-primary" value="{{__("Save and Back")}}">
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
