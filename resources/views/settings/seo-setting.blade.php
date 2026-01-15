@extends('layouts.main')

@section('title')
    {{ __('Seo-settings') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <div class="divider">
                            <div class="divider-text">
                                <h4>{{ __('Seo Setting') }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div class="row form-group">
                                <div class="col-sm-12 col-md-12 form-group">
                                    <form action="{{ route('seo-setting.store') }}" method="POST" enctype="multipart/form-data" data-parsley-validate class="create-form">
                                        @csrf
                                        <div class="row">
                                            <div class="col-sm-12 col-md-12 form-group mandatory">
                                                <label for="page" class="form-label text-center">{{ __('Page') }}</label>
{{--                                                <input type="text" name="page" class="form-control" placeholder="{{ __('Page') }}" data-parsley-required="true">--}}
                                                <select class="form-control" name="page" data-parsley-required="true">
                                                    <option value="">Select Page</option>
                                                    <option value="home">Home</option>
                                                    <option value="subscription">Subscription</option>
                                                    <option value="blogs">Blogs</option>
                                                    <option value="faqs">Faqs</option>
                                                    <option value="ad-listing">Ad Listing</option>
                                                    <option value="about-us">About us</option>
                                                    <option value="contact-us">Contact us</option>
                                                    <option value="landing">Landing</option>
                                                    <option value="privacy-policy">Privacy Policy</option>
                                                    <option value="terms-and-conditions">Terms and Conditions</option>
                                                </select>

                                            </div>

                                            <div class="col-sm-12 col-md-12 form-group mandatory">
                                                <label for="meta_title" class="form-label text-center">{{ __('Title') }}</label>
                                                <input type="text" name="title" class="form-control" id="meta_title" placeholder="{{ __('Title') }}" data-parsley-required="true">
                                                <h6 id="meta_title_count"></h6>
                                            </div>

                                            <div class="col-sm-12 col-md-12 form-group mandatory">
                                                <label for="meta_description" class="form-label text-center">{{ __('Description') }}</label>
                                                <textarea name="description" class="form-control" id="meta_description" placeholder="{{ __('Description') }}" data-parsley-required="true"></textarea>
                                                <h6 id="meta_description_count"></h6>
                                            </div>
                                            <div class="col-sm-12 col-md-12 form-group mandatory">
                                                <label for="keywords" class="form-label text-center">{{ __('Keywords') }}</label>
                                                <textarea name="keywords" class="form-control" placeholder="{{ __('Keywords') }}" data-parsley-required="true"></textarea>
                                            </div>

                                            <div class="col-sm-12 col-md-12 form-group mandatory">
                                                <label for="image" class="form-label">{{ __('Image') }}</label>
                                                <input class="filepond" type="file" name="image" id="favicon_icon">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-sm-12 d-flex justify-content-end mt-3">
                                                <button type="submit" class="btn btn-primary me-1 mb-1">{{ __('Save') }}</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <table class="table-light table-striped" aria-describedby="mydesc" id="table_list" data-toggle="table" data-url="{{ route('seo-setting.show',1) }}" data-click-to-select="true" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true" data-fixed-columns="true" data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false" data-responsive="true" data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3" data-escape="true" data-query-params="queryParams" data-mobile-responsive="true">
                                    <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="page" data-sortable="false">{{ __('Page') }}</th>
                                        <th scope="col" data-field="title" data-sortable="false">{{ __('Title')}}</th>
                                        <th scope="col" data-field="description" data-sortable="true">{{ __('Description') }}</th>
                                        <th scope="col" data-field="keywords" data-sortable="true">{{ __('Keywords') }}
                                        <th scope="col" data-field="image" data-sortable="false" data-formatter="imageFormatter">{{ __('Image') }}
                                        <th scope="col" data-field="operate" data-escape="false" data-sortable="false" data-events="SeoSettingEvents">{{ __('Action') }}</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- EDIT MODEL MODEL -->
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="#" class="form-horizontal" id="edit-form" enctype="multipart/form-data" method="POST" data-parsley-validate>
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('Edit Seo Setting') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="col-md-12 col-12">
                                    <div class="form-group mandatory">
                                        <label for="edit_page" class="form-label col-12">{{ __('Page') }}</label>
                                        <input type="text" id="edit_page" class="form-control col-12" placeholder="{{__("Page")}}" name="page" data-parsley-required="true">
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-12">
                                <div class="col-md-12 col-12">
                                    <div class="form-group mandatory">
                                        <label for="edit_title" class="form-label col-12">{{ __('Title') }}</label>
                                        <input type="text" id="edit_title" class="form-control col-12" placeholder="{{__("Title")}}" name="title" data-parsley-required="true">
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="col-md-12 col-12">
                                    <div class="form-group mandatory">
                                        <label for="edit_description" class="form-label col-12">{{ __('Description') }}</label>
                                        <textarea type="text" id="edit_description" class="form-control col-12" placeholder="{{__("Description")}}" name="code" data-parsley-required="true"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="col-md-12 col-12">
                                    <div class="form-group mandatory">
                                        <label for="edit_keywords" class="form-label col-12">{{ __('Keywords') }}</label>
                                        <textarea type="text" id="edit_keywords" class="form-control col-12" placeholder="{{__("Keywords")}}" name="keywords" data-parsley-required="true"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-12 form-group">
                                <label class="col-form-label ">{{ __('Image') }}</label>
                                <div class="">
                                    <input class="filepond" type="file" name="image" id="edit_image">
                                </div>
                            </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
