@extends('layouts.main')

@section('title', __('Custom Fields'))

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
  <div class="buttons mb-3">
    <a class="btn btn-primary" href="{{ url('custom-fields') }}">
      &lt; {{ __('Back to Custom Fields') }}
    </a>

    @if(in_array($custom_field->type, ['radio','checkbox','dropdown']))
      <a class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        + {{ __('Add Value') }}
      </a>
    @endif
  </div>

  <form action="{{ route('custom-fields.update', $custom_field->id) }}"
        class="edit-form" data-success-function="afterCustomFieldUpdate"
        method="POST" enctype="multipart/form-data" data-parsley-validate>
    @method('PUT')
    @csrf

    <div class="row">
      {{-- Left Column --}}
      <div class="col-md-6 col-sm-12">
        <div class="card">
          <div class="card-header">{{ __('Edit Custom Field') }}</div>
          <div class="card-body mt-3">
            <div class="row">
              {{-- Field Name --}}
              <div class="col-md-12 form-group mandatory">
                <label for="name" class="mandatory form-label">{{ __('Field Name') }}</label>
                <input type="text" name="name" id="name" class="form-control"
                       data-parsley-required="true"
                       value="{{ $custom_field->name }}">
              </div>

              {{-- Field Type --}}
              <div class="col-md-12 form-group mandatory">
                <label for="type" class="mandatory form-label">{{ __('Field Type') }}</label>
                <select name="type" id="type" class="form-select form-control">
                  <option value="{{ $custom_field->type }}" selected>
                    {{ ucfirst($custom_field->type) }}
                  </option>
                </select>
              </div>

              {{-- Values (for radio/checkbox/dropdown) --}}
              @if(in_array($custom_field->type, ['radio','checkbox','dropdown']))
              <div class="col-md-12">
                <label for="values" class="form-label">{{ __('Field Values') }}</label>
                <div class="form-group">
                  <select id="values" name="values[]"
                          class="select2 col-12 w-100"
                          multiple
                          data-tags="true"
                          data-token-separators="[',']"
                          data-placeholder="{{ __('Select or type and press Enter') }}"
                          data-allow-clear="true">
                    @foreach(($custom_field->translated_values ?? []) as $value)
                      @if(!is_null($value) && $value !== '')
                        <option value="{{ $value }}" selected>{{ $value }}</option>
                      @endif
                    @endforeach
                  </select>

                  <div class="input_hint mt-1">
                    {{ __('This will be applied only for') }}
                    <span class="highlighted_text">{{ __('Checkboxes') }}</span>,
                    <span class="highlighted_text">{{ __('Radio') }}</span>
                    {{ __('and') }}
                    <span class="highlighted_text">{{ __('Dropdown') }}</span>.
                  </div>
                </div>
              </div>
              @endif

              {{-- Min/Max Length --}}
              @if(in_array($custom_field->type, ['textbox','fileinput','number']))
              <div class="col-md-6 form-group">
                <label for="min_length" class="form-label">{{ __('Field Length Min') }}</label>
                <input type="text" name="min_length" id="min_length" class="form-control"
                       value="{{ old('min_length', $custom_field->min_length) }}">
                <div class="input_hint">
                  {{ __('This will be applied only for') }}
                  <span class="highlighted_text">{{ __('text') }}, {{ __('number') }}, {{ __('textarea') }}</span>.
                </div>
              </div>
              <div class="col-md-6 form-group">
                <label for="max_length" class="form-label">{{ __('Field Length Max') }}</label>
                <input type="text" name="max_length" id="max_length" class="form-control"
                       value="{{ old('max_length', $custom_field->max_length) }}">
                <div class="input_hint">
                  {{ __('This will be applied only for') }}
                  <span class="highlighted_text">{{ __('text') }}, {{ __('number') }}, {{ __('textarea') }}</span>.
                </div>
              </div>
              @endif

              {{-- Image --}}
              <div class="col-md-12">
                <div class="form-group">
                  <label for="image" class="form-label">{{ __('Image') }}</label>
                  <input type="file" name="image" id="image" class="form-control">
                  <small>{{ __('(use 256 x 256 size for better view)') }}</small>
                </div>
                <div class="field_img mt-2">
                  <img src="{{ $custom_field->image ?: asset('assets/img_placeholder.png') }}"
                       alt="" id="blah" class="preview-image img w-25">
                </div>
              </div>

              {{-- Required / Active --}}
              <div class="row mt-3">
                <div class="col-md-6 form-group mandatory">
                  <div class="form-check form-switch">
                    <input type="hidden" name="required" value="{{ $custom_field->required ? 1 : 0 }}">
                    <input class="form-check-input status-switch" type="checkbox"
                           id="requiredSwitch"
                           {{ $custom_field->required ? 'checked' : '' }}
                           data-target="#required">
                    <label class="form-check-label" for="requiredSwitch">{{ __('Required') }}</label>
                  </div>
                </div>
                <div class="col-md-6 form-group mandatory">
                  <div class="form-check form-switch">
                    <input type="hidden" name="status" value="{{ $custom_field->status ? 1 : 0 }}">
                    <input class="form-check-input status-switch" type="checkbox"
                           id="statusSwitch"
                           {{ $custom_field->status ? 'checked' : '' }}
                           data-target="#status">
                    <label class="form-check-label" for="statusSwitch">{{ __('Active') }}</label>
                  </div>
                </div>
              </div>

              {{-- Translations --}}
              @if(isset($languages) && $languages->isNotEmpty())
              <hr>
              <h5>{{ __('Translations') }}</h5>
              <div class="row">
                @foreach ($languages as $language)
                <div class="col-12 col-lg-6 mb-3">
                  <div class="card">
                    <div class="card-header">
                      <h5 class="card-title mb-0">{{ $language->name }}</h5>
                    </div>
                    <div class="card-body">
                      {{-- Field Name Translation --}}
                      <div class="form-group mb-3">
                        <label for="name_{{$language->id}}">Name ({{ $language->code }})</label>
                        <input type="text"
                               id="name_{{$language->id}}"
                               name="translations[name][{{$language->id}}]"
                               class="form-control"
                               value="{{ optional($custom_field->translations->firstWhere('language_id', $language->id))->name }}">
                      </div>

                      {{-- Field Values Translation --}}
                      @if(in_array($custom_field->type, ['radio','checkbox','dropdown']))
                        @php
                          $t = $custom_field->translations->firstWhere('language_id', $language->id);
                          $vals = $t?->values;
                          if (is_string($vals)) {
                              $tmp = json_decode($vals, true);
                              $vals = json_last_error() === JSON_ERROR_NONE ? $tmp : ($vals ? [$vals] : []);
                          } elseif (is_null($vals)) {
                              $vals = [];
                          } elseif (!is_array($vals)) {
                              $vals = [$vals];
                          }
                        @endphp
                        <div class="form-group">
                          <label for="values_{{$language->id}}">Values ({{ $language->code }})</label>
                          <select id="values_{{$language->id}}"
                                  name="translations[values][{{$language->id}}][]"
                                  class="select2 col-12 w-100"
                                  multiple
                                  data-tags="true"
                                  data-token-separators="[',']"
                                  data-placeholder="{{ __('Select or type and press Enter') }}"
                                  data-allow-clear="true">
                            @foreach ($vals as $val)
                              @if($val !== '' && $val !== null)
                                <option value="{{ $val }}" selected>{{ $val }}</option>
                              @endif
                            @endforeach
                          </select>
                        </div>
                      @endif
                    </div>
                  </div>
                </div>
                @endforeach
              </div>
              @endif

            </div>
          </div>
        </div>
      </div>

      {{-- Right Column: Categories --}}
      <div class="col-md-6 col-sm-12">
        <div class="card">
          <div class="card-header">{{ __('Category') }}</div>
          <div class="card-body mt-2">
            <div class="sub_category_lit">
              @foreach ($categories as $category)
                <div class="category">
                  <div class="category-header">
                    <label>
                      <input type="checkbox"
                             name="selected_categories[]"
                             value="{{ $category->id }}"
                             {{ in_array($category->id, $selected_categories ?? []) ? 'checked' : '' }}>
                      {{ $category->name }}
                    </label>
                    @if (!empty($category->subcategories))
                      <i class="fas toggle-button" style="font-size:24px">&#x25BC;</i>
                    @endif
                  </div>
                  <div class="subcategories" style="display:none;">
                    @if (!empty($category->subcategories))
                      @include('category.treeview', [
                        'categories' => $category->subcategories,
                        'selected_categories' => $selected_categories
                      ])
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      {{-- Submit --}}
      <div class="col-md-12 text-end mb-3">
        <input type="submit" class="btn btn-primary" value="{{ __('Save and Back') }}">
      </div>
    </div>
  </form>
</section>
@endsection

