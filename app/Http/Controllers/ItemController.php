<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\CustomFieldCategory;
use App\Models\Item;
use App\Models\ItemCustomFieldValue;
use App\Models\ItemImages;
use App\Models\State;
use App\Models\UserFcmToken;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\NotificationService;
use App\Services\ResponseService;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Str;
use Throwable;
use Validator;

class ItemController extends Controller
{
    public function index()
    {
        ResponseService::noAnyPermissionThenRedirect(['advertisement-list', 'advertisement-update', 'advertisement-delete']);
        $countries = Country::all();
        $cities = City::all();
        return view('items.index', compact('countries', 'cities'));
    }


public function update(Request $request, $id)
{
    ResponseService::noPermissionThenSendJson('advertisement-update');

    DB::beginTransaction();

    try {
        $item = Item::with('user')->findOrFail($id);
        $category = Category::findOrFail($request->category_id);

        $isJobCategory = $category->is_job_category;
        $isPriceOptional = $category->price_optional;

        // إعداد القواعد الديناميكية للـ Validation
        $rules = [
            'name'                 => 'required|string|max:255',
            'slug'                 => 'nullable|regex:/^[a-z0-9-]+$/',
            'description'          => 'nullable|string',
            'latitude'             => 'nullable|numeric',
            'longitude'            => 'nullable|numeric',
            'address'              => 'nullable|string|max:500',
            'contact'              => 'nullable|string|max:50',
            'image'                => 'nullable|mimes:jpeg,jpg,png|max:7168',
            'custom_fields'        => 'nullable',
            'custom_field_files'   => 'nullable|array',
            'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:7168',
            'gallery_images'       => 'nullable|array',
            'admin_edit_reason'    => 'required|string|max:1000',
            'city'                 => 'nullable|exists:cities,id',
            'state'                => 'nullable|exists:states,id',
            'country'              => 'nullable|exists:countries,id',
            'area'                 => 'nullable|exists:areas,id',
        ];

        // شرط السعر أو الراتب
        if (!$isJobCategory && !$isPriceOptional) {
            $rules['price'] = 'required|numeric|min:0';
        } else {
            $rules['min_salary'] = 'nullable|numeric|min:0';
            $rules['max_salary'] = 'nullable|numeric|gte:min_salary';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // تحديث الحقول الأساسية
        $fieldsToUpdate = ['name', 'slug', 'description', 'price', 'min_salary', 'max_salary', 'contact', 'admin_edit_reason'];
        foreach ($fieldsToUpdate as $field) {
            if ($request->filled($field)) {
                $item->$field = $request->$field;
            }
        }

        // العنوان والإحداثيات
        if ($request->filled('manual_address')) {
            $item->address   = $request->manual_address;
            $item->city      = $request->city ? City::find($request->city)->name : $item->city;
            $item->state     = $request->state ? State::find($request->state)->name : $item->state;
            $item->country   = $request->country ? Country::find($request->country)->name : $item->country;
            $item->latitude  = $request->latitude ?? $item->latitude;
            $item->longitude = $request->longitude ?? $item->longitude;
        } elseif ($request->filled('address_input')) {
            $item->address   = $request->address_input;
            $item->city      = $request->city_input ?? $item->city;
            $item->state     = $request->state_input ?? $item->state;
            $item->country   = $request->country_input ?? $item->country;
            $item->latitude  = $request->latitude ?? $item->latitude;
            $item->longitude = $request->longitude ?? $item->longitude;
        }

        // علامة التعديل من الأدمن
        $item->is_edited_by_admin = 1;

        // تحديث الصورة الرئيسية إذا تم رفعها
        if ($request->hasFile('image')) {
            $item->image = FileService::compressAndReplace($request->file('image'), 'uploads/items', $item->getRawOriginal('image'));
        }

        $item->save();

        // تحديث الحقول المخصصة
        if ($request->custom_fields) {
            $itemCustomFieldValues = [];
            foreach ($request->custom_fields as $fieldId => $value) {
                $valueToStore = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
                $itemCustomFieldValues[] = [
                    'item_id'         => $item->id,
                    'custom_field_id' => $fieldId,
                    'value'           => $valueToStore,
                    'updated_at'      => now(),
                ];
            }
            if (!empty($itemCustomFieldValues)) {
                ItemCustomFieldValue::upsert($itemCustomFieldValues, ['item_id', 'custom_field_id'], ['value', 'updated_at']);
            }
        }

        // رفع ملفات الحقول المخصصة
        if ($request->hasFile('custom_field_files')) {
            foreach ($request->file('custom_field_files') as $fieldId => $file) {
                $existing = ItemCustomFieldValue::where([
                    'item_id' => $item->id,
                    'custom_field_id' => $fieldId
                ])->first();

                $path = $existing
                    ? FileService::replace($file, 'custom_fields_files', $existing->getRawOriginal('value'))
                    : FileService::upload($file, 'custom_fields_files');

                ItemCustomFieldValue::updateOrCreate(
                    ['item_id' => $item->id, 'custom_field_id' => $fieldId],
                    ['value' => $path, 'updated_at' => now()]
                );
            }
        }

        // رفع صور gallery جديدة
        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $file) {
                ItemImages::create([
                    'image'   => FileService::compressAndUpload($file, 'uploads/items'),
                    'item_id' => $item->id,
                ]);
            }
        }

        // حذف صور gallery محددة
        if (!empty($request->delete_item_image_id)) {
            foreach ($request->delete_item_image_id as $imageId) {
                $img = ItemImages::find($imageId);
                if ($img) {
                    FileService::delete($img->getRawOriginal('image'));
                    $img->delete();
                }
            }
        }

        DB::commit();

        // إرسال إشعار FCM للمستخدم
        $user_tokens = UserFcmToken::where('user_id', $item->user->id)->pluck('fcm_token')->toArray();
       if (!empty($user_tokens)) {
           NotificationService::sendFcmNotification(
                $user_tokens,
               'ول اعلانك ' . $item->name,
            "تم تعديل اعلانك من قبل الادمن",
         "item-edit",
       ['id' => $item->id]
        );
        }




        $route = ($item->status === 'approved' && (is_null($item->expired_at) || $item->expired_at > now()) && is_null($item->deleted_at))
                    ? route('advertisement.index')
                    : route('advertisement.requested.index');

        return ResponseService::successRedirectResponse("Advertisement Updated Successfully", $route);

    } catch (\Throwable $th) {
        DB::rollBack();
        report($th);
        return redirect()->back()->with('error', 'An error occurred while updating the item.');
    }
}

    public function show($status, Request $request)
    {
        try {
            ResponseService::noPermissionThenSendJson('advertisement-list');
            $offset = $request->input('offset', 0);
            $limit = $request->input('limit', 10);
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'ASC');

            $sql = Item::with(['custom_fields', 'category:id,name', 'user:id,name', 'gallery_images', 'featured_items'])->withTrashed();

            if (!empty($request->search)) {
                $sql = $sql->search($request->search);
            }

            if ($status == 'approved') {
                $sql->where('status', 'approved')->getNonExpiredItems()->whereNull('items.deleted_at');
            } elseif ($status == 'requested') {
                $sql->where('status', '!=', 'approved')
                    ->orWhere(function ($query) {
                        $query->where('status', 'approved')
                              ->whereNotNull('expiry_date')
                              ->where('expiry_date', '<', Carbon::now())
                              ->orWhereNotNull('items.deleted_at');
                    });
            }

            if (!empty($request->filter)) {
                $sql = $sql->filter(json_decode($request->filter, false, 512, JSON_THROW_ON_ERROR));
            }

            $total = $sql->count();
            $sql = $sql->sort($sort, $order)->skip($offset)->take($limit);
            $result = $sql->get();

            $bulkData = ['total' => $total];
            $rows = [];

            $itemCustomFieldValues = ItemCustomFieldValue::whereIn('item_id', $result->pluck('id'))->get()
                ->groupBy('item_id')
                ->map(fn($group) => $group->keyBy('custom_field_id'));

            foreach ($result as $row) {
                $itemValuesMap = $itemCustomFieldValues->get($row->id, collect());
                $featured_status = $row->featured_items->isNotEmpty() ? 'Featured' : 'Premium';

                $row->custom_fields = collect($row->custom_fields)->map(function ($customField) use ($itemValuesMap) {
                    $fieldValueModel = $itemValuesMap->get($customField->id);
                    $value = $fieldValueModel?->value;

                    if ($customField->type === "fileinput" && !empty($value)) {
                        $customField['value'] = [url(Storage::url($value))];
                    } else {
                        $customField['value'] = $value;
                    }
                    return $customField;
                });

                $tempRow = $row->toArray();
                $operate = '';

                if (count($row->custom_fields) > 0 && Auth::user()->can('advertisement-list')) {
                    $operate .= BootstrapTableService::button('fa fa-eye', '#', ['editdata', 'btn-light-danger  '], ['title' => __("View"), "data-bs-target" => "#editModal", "data-bs-toggle" => "modal"]);
                }

                if ($row->status !== 'sold out' && Auth::user()->can('advertisement-update')) {
                    $operate .= BootstrapTableService::editButton(route('advertisement.approval', $row->id), true, '#editStatusModal', 'edit-status', $row->id);
                }

                if (Auth::user()->can('advertisement-update')) {
                    $operate .= BootstrapTableService::button('fa fa-wrench', route('advertisement.edit', $row->id), ['btn', 'btn-light-warning'], ['title' => __('Advertisement Update')]);
                }

                if (Auth::user()->can('advertisement-delete')) {
                    $operate .= BootstrapTableService::deleteButton(route('advertisement.destroy', $row->id));
                }

                $tempRow['active_status'] = empty($row->deleted_at);
                $tempRow['featured_status'] = $featured_status;
                $tempRow['operate'] = $operate;

                $rows[] = $tempRow;
            }

            $bulkData['rows'] = $rows;
            return response()->json($bulkData);

        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "ItemController --> show");
            ResponseService::errorResponse();
        }
    }

    public function updateItemApproval(Request $request, $id)
    {
        try {
            ResponseService::noPermissionThenSendJson('advertisement-update');
            $item = Item::with('user')->withTrashed()->findOrFail($id);
            $item->update([
                ...$request->all(),
                'rejected_reason' => in_array($request->status, ["soft rejected", "permanent rejected"]) ? $request->rejected_reason : ''
            ]);

            // إرسال إشعارات متعددة اللغات
            if ($item->user) {
                $user_token = UserFcmToken::where('user_id', $item->user->id)->pluck('fcm_token')->toArray();
                if (!empty($user_token)) {
                    $title = ['en' => 'About ' . $item->name, 'ar' => 'عن ' . $item->name];
                    $message = ['en' => 'Your Advertisement is ' . ucfirst($request->status), 'ar' => 'إعلانك ' . $this->translateStatus($request->status)];
                    NotificationService::sendFcmNotification($user_token, $title, $message, "item-update", ['id' => $item->id]);
                }
            }

            ResponseService::successResponse('Advertisement Status Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'ItemController ->updateItemApproval');
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    protected function translateStatus($status)
    {
        return match($status) {
            'approved' => 'موافق عليه',
            'soft rejected' => 'مرفوض مؤقتاً',
            'permanent rejected' => 'مرفوض نهائياً',
            'sold out' => 'تم البيع',
            default => $status,
        };
    }

    public function destroy($id)
    {
        ResponseService::noPermissionThenSendJson('advertisement-delete');
        try {
            $item = Item::with('gallery_images')->withTrashed()->findOrFail($id);
            foreach ($item->gallery_images as $gallery_image) {
                FileService::delete($gallery_image->getRawOriginal('image'));
            }
            FileService::delete($item->getRawOriginal('image'));

            $item->forceDelete();
            ResponseService::successResponse('Advertisement deleted successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse('Something went wrong');
        }
    }

    public function requestedItem()
    {
        ResponseService::noAnyPermissionThenRedirect(['advertisement-list', 'advertisement-update', 'advertisement-delete']);
        $countries = Country::all();
        $cities = City::all();
        return view('items.requested_item', compact('countries', 'cities'));
    }

    public function searchCities(Request $request)
    {
        $countryName = trim($request->query('country_name'));
        if ($countryName === 'All') {
            return response()->json(['message' => 'Success', 'data' => []]);
        }
        $country = Country::where('name', $countryName)->first();
        if (!$country) return response()->json(['message' => 'Success', 'data' => []]);
        $cities = City::where('country_id', $country->id)->get();
        return response()->json(['message' => 'Success', 'data' => $cities]);
    }

    // دوال editForm و update مع إشعارات متعددة اللغات
    public function editForm($id)
    {
        $item = Item::with(
            'user:id,name,email,mobile,profile,country_code',
            'category.custom_fields',
            'gallery_images:id,image,item_id',
            'featured_items',
            'favourites',
            'item_custom_field_values.custom_field',
            'area'
        )->findOrFail($id);

        $categories = Category::whereNull('parent_category_id')
            ->with([
                'custom_fields',
                'subcategories.custom_fields',
                'subcategories.subcategories.custom_fields',
                'subcategories.subcategories.subcategories.custom_fields',
                'subcategories.subcategories.subcategories.subcategories.custom_fields',
            ])
            ->get();

        $customFieldCategories = CustomFieldCategory::with('custom_fields')
            ->where('category_id', $item->category_id)
            ->get();

        $savedValues = ItemCustomFieldValue::where('item_id', $item->id)->get()->keyBy('custom_field_id');

        $custom_fields = $customFieldCategories->map(function ($relation) use ($savedValues) {
            $field = $relation->custom_fields;
            if (!$field) return null;

            $value = $savedValues->get($field->id)->value ?? null;

            if ($field->type === 'fileinput') {
                $field->value = $value ? [url(Storage::url($value))] : [];
            } else {
                $field->value = $value;
            }

            return $field;
        })->filter();

        $countries = Country::all();
        $states = State::get();
        $cities = City::get();
        $selected_category = [$item->category_id];

        return view('items.update', compact('item', 'categories', 'custom_fields', 'selected_category', 'countries', 'states', 'cities'));
    }

    public function getCustomFields($categoryId)
    {
        $fields = $this->getFieldsRecursively($categoryId);
        $category = Category::findOrFail($categoryId);

        return response()->json([
            'fields' => $fields,
            'is_job_category' => $category->is_job_category,
            'price_optional' => $category->price_optional,
        ]);
    }

    protected function getFieldsRecursively($categoryId)
    {
        $customFieldCategories = CustomFieldCategory::with('custom_fields')
            ->where('category_id', $categoryId)
            ->get();

        $fields = $customFieldCategories->map(fn($relation) => $relation->custom_fields)->filter()->values();
        if ($fields->isNotEmpty()) return $fields;

        $category = Category::find($categoryId);
        if ($category && $category->parent_category_id) {
            return $this->getFieldsRecursively($category->parent_category_id);
        }
        return collect();
    }
}
