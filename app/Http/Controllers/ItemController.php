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
