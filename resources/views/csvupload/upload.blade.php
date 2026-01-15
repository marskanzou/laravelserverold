@extends('layouts.main')

@section('title', __('CSV Upload'))

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
<div class="container mt-4">

    {{-- رسائل النجاح / الخطأ --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('errors_list'))
        <div class="alert alert-warning">
            <strong>{{ __('Rows with errors:') }}</strong>
            <ul class="mb-0">
                @foreach(session('errors_list') as $row => $error)
                    <li>{{ __('Row :row: :error', ['row' => $row, 'error' => $error]) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">

        {{-- إنشاء مستخدم جديد --}}
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5>{{ __('Create New User') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.create.user') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Name') }}</label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Password') }}</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ __('Create User') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- رفع Excel + ZIP الصور --}}
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5>{{ __('Upload Excel & Images ZIP') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.csv.upload.post') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        {{-- إدخال البريد يدوياً --}}
                        <div class="mb-3">
                            <label for="selected_user_email" class="form-label">{{ __('User Email') }}</label>
                            <input type="email" name="selected_user_email" class="form-control"
                                   value="{{ old('selected_user_email') }}" placeholder="{{ __('Enter user email') }}">
                        </div>

                        {{-- اختيار مستخدم من القائمة --}}
                        <div class="mb-3">
                            <label for="user_list" class="form-label">{{ __('Or select existing user') }}</label>
                            <select id="user_list" class="form-select" onchange="fillEmail(this)">
                                <option value="">{{ __('-- Select User --') }}</option>
                                @foreach(\App\Models\User::all() as $user)
                                    <option value="{{ $user->email }}">
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- ملف Excel --}}
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">{{ __('Select Excel File') }}</label>
                            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                            <small class="text-muted">{{ __('Upload Excel file that contains your item data.') }}</small>
                        </div>

                        {{-- ملف ZIP للصور --}}
                        <div class="mb-3">
                            <label for="images_zip" class="form-label">{{ __('Select ZIP file (images)') }}</label>
                            <input type="file" name="images_zip" class="form-control" accept=".zip" required>
                            <small class="text-muted">
                                {{ __('Upload a ZIP file containing all images in folders. The Excel should reference paths like "cars/toyota/camry/main.jpg".') }}
                            </small>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            {{ __('Upload Excel & ZIP Images') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    function fillEmail(select) {
        const emailInput = document.querySelector('input[name="selected_user_email"]');
        emailInput.value = select.value;
    }
</script>
@endsection

