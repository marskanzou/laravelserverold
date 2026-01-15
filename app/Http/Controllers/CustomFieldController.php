<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldCategory;
use App\Models\CustomFieldTranslation;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CustomFieldController extends Controller
{
    private string $uploadFolder;

    public function __construct()
    {
        $this->uploadFolder = 'custom-fields';
    }

    public function index()
    {
        ResponseService::noAnyPermissionThenRedirect(['custom-field-list', 'custom-field-create', 'custom-field-update', 'custom-field-delete']);
        $categories = Category::get();
        return view('custom-fields.index', compact('categories'));
    }

    public function create(Request $request)
    {
        ResponseService::noPermissionThenRedirect('custom-field-create');
        $languages = CachingService::getLanguages()->where('code', '!=', 'en')->values();
        $cat_id = $request->id ?? 0;
        $categories = HelperService::buildNestedChildSubcategoryObject(Category::get());
        return view('custom-fields.create', compact('categories', 'cat_id', 'languages'));
    }

    public function store(Request $request)
    {
        ResponseService::noPermissionThenSendJson('custom-field-create');

        $validator = Validator::make($request->all(), [
            'name'       => 'required',
            'type'       => 'required|in:number,textbox,fileinput,radio,dropdown,checkbox',
            'image'      => 'required|image',
            'required'   => 'required',
            'status'     => 'required',
            'values'     => 'required_if:type,radio,dropdown,checkbox|array',
            'min_length' => 'required_if:type,number,textbox',
            'max_length' => 'required_if:type,number,textbox',
            'selected_categories' => 'required|array|min:1',
            'translations' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $data = $request->all();

            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndUpload($request->file('image'), $this->uploadFolder);
            }

            if (in_array($request->type, ['dropdown', 'radio', 'checkbox'])) {
                $data['values'] = json_encode($request->values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }

            $customField = CustomField::create($data);

            // حفظ الترجمات
            if (!empty($request->translations) && is_array($request->translations)) {
                foreach ($request->translations as $langId => $translation) {
                    CustomFieldTranslation::create([
                        'custom_field_id' => $customField->id,
                        'language_id'     => $langId,
                        'name'            => $translation['name'] ?? $data['name'],
                        'values'          => !empty($translation['values'])
                            ? json_encode($translation['values'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                            : $data['values'],
                    ]);
                }
            }

            // حفظ التصنيفات
            if (!empty($request->selected_categories)) {
                $customFieldCategories = [];
                foreach ($request->selected_categories as $categoryId) {
                    $customFieldCategories[] = [
                        'category_id'     => $categoryId,
                        'custom_field_id' => $customField->id
                    ];
                }

CustomFieldCategory::upsert($customFieldCategories, ['custom_field_id','category_id']);
            }

            DB::commit();
            ResponseService::successResponse('Custom Field Added Successfully');
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

public function show(Request $request)
{
    try {
        ResponseService::noPermissionThenSendJson('custom-field-list');

        $offset = (int) $request->input('offset', 0);
        $limit  = (int) $request->input('limit', 15);
        $sort   = $request->input('sort', 'id');
        $order  = $request->input('order', 'DESC');

        // اللغة الحالية
        $langId = optional(
            CachingService::getLanguages()->firstWhere('code', App::getLocale())
        )->id;

        // ابني الاستعلام مرة واحدة مع الـ eager loading
        $query = CustomField::query()
            ->with([
                'categories:id,name',
                'translations' => function ($q) use ($langId) {
                    if ($langId) {
                        $q->where('language_id', $langId);
                    }
                },
            ])
            ->orderBy($sort, $order);

        if (!empty($request->filter)) {
            $query->filter(json_decode($request->filter, false, 512, JSON_THROW_ON_ERROR));
        }

        if (!empty($request->search)) {
            $query->search($request->search);
        }

        // احسب الإجمالي قبل التقطيع
        $total = (clone $query)->count();

        // اجلب الصفحة المطلوبة
        $rowsData = (clone $query)->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($rowsData as $row) {
            $operate = '';
            if (Auth::user()->can('custom-field-update')) {
                $operate .= BootstrapTableService::editButton(route('custom-fields.edit', $row->id));
            }
            if (Auth::user()->can('custom-field-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('custom-fields.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['operate'] = $operate;
            $tempRow['category_names'] = array_column($row->categories->toArray(), 'name');

            // الترجمات محمّلة مسبقًا – خذ أول ترجمة (للغة الحالية إن وُجدت)
            $t = $row->translations->first();
            $tempRow['name']   = $t->name   ?? $row->name;
            $tempRow['values'] = $t->values ?? $row->values;

            $rows[] = $tempRow;
        }

        return response()->json([
            'total' => $total,
            'rows'  => $rows,
        ]);
    } catch (Throwable $th) {
        ResponseService::logErrorResponse($th, "CustomFieldController -> show");
        ResponseService::errorResponse('Something Went Wrong');
    }
}
 //   return response()->json(['total' => $total, 'rows' => $rows]);
//}


    public function show2(Request $request)
    {
        try {
            ResponseService::noPermissionThenSendJson('custom-field-list');

            $offset = $request->input('offset', 0);
            $limit  = $request->input('limit', 15);
            $sort   = $request->input('sort', 'id');
            $order  = $request->input('order', 'DESC');

            $query = CustomField::orderBy($sort, $order)->with('categories:id,name');

            if (!empty($request->filter)) {
                $query = $query->filter(json_decode($request->filter, false, 512, JSON_THROW_ON_ERROR));
            }

            if (!empty($request->search)) {
                $query = $query->search($request->search);
            }

            $total = $query->count();
            $rowsData = $query->skip($offset)->take($limit)->get();

            $rows = [];
            foreach ($rowsData as $row) {
                $operate = '';
                if (Auth::user()->can('custom-field-update')) {
                    $operate .= BootstrapTableService::editButton(route('custom-fields.edit', $row->id));
                }
                if (Auth::user()->can('custom-field-delete')) {
                    $operate .= BootstrapTableService::deleteButton(route('custom-fields.destroy', $row->id));
                }

                $tempRow = $row->toArray();
                $tempRow['operate'] = $operate;
                $tempRow['category_names'] = array_column($row->categories->toArray(), 'name');

                // ======= إضافة ترجمة الاسم والقيم مثل CategoryController =======
                $translation = $row->translationByLanguage(App::getLocale());
                $tempRow['name'] = $translation->name ?? $row->name;
                $tempRow['values'] = $translation->values ?? $row->values;

                $rows[] = $tempRow;
            }

            return response()->json([
                'total' => $total,
                'rows'  => $rows,
            ]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "CustomFieldController -> show");
            ResponseService::errorResponse('Something Went Wrong');
        }
    }
public function edit($id)
{
    ResponseService::noPermissionThenRedirect('custom-field-update');

    $custom_field = CustomField::with(['custom_field_category', 'translations'])->findOrFail($id);
    $selected_categories = $custom_field->custom_field_category->pluck('category_id')->toArray();
    $categories = HelperService::buildNestedChildSubcategoryObject(Category::get());
    $languages = CachingService::getLanguages()->where('code', '!=', 'en')->values();

    // ابني المصفوفة مباشرة من الـ collection المحمّلة
    $value_translations = $custom_field->translations->pluck('name', 'language_id')->toArray();

    return view('custom-fields.edit', compact('custom_field','categories','selected_categories','languages','value_translations'));
}








    public function edit2($id)
    {
        ResponseService::noPermissionThenRedirect('custom-field-update');

        $custom_field = CustomField::with('custom_field_category')->findOrFail($id);
        $selected_categories = $custom_field->custom_field_category->pluck('category_id')->toArray();
        $categories = HelperService::buildNestedChildSubcategoryObject(Category::get());
        $languages = CachingService::getLanguages()->where('code', '!=', 'en')->values();

        // ======= جلب الترجمات لكل لغة =======
        $value_translations = [];
        foreach ($languages as $lang) {
            $translation = CustomFieldTranslation::where('custom_field_id', $id)
                ->where('language_id', $lang->id)
                ->first();
            $value_translations[$lang->id] = $translation->name ?? '';
        }

        return view('custom-fields.edit', compact('custom_field', 'categories', 'selected_categories', 'languages', 'value_translations'));
    }

public function update(Request $request, $id)
{
    ResponseService::noPermissionThenSendJson('custom-field-update');

    $validator = Validator::make($request->all(), [
        'name'       => 'required',
        'type'       => 'required|in:number,textbox,fileinput,radio,dropdown,checkbox',
        'image'      => 'nullable|image',
        'required'   => 'required',
        'status'     => 'required',
        'values'     => 'required_if:type,radio,dropdown,checkbox|array',
        'min_length' => 'required_if:type,number,textbox',
        'max_length' => 'required_if:type,number,textbox',
        'selected_categories' => 'required|array|min:1',
        'translations' => 'nullable|array',
    ]);

    if ($validator->fails()) {
        ResponseService::validationError($validator->errors()->first());
    }

    try {
        DB::beginTransaction();

        $customField = CustomField::with('custom_field_category')->findOrFail($id);

        // حدّد الحقول القابلة للتحديث
        $data = $request->only([
            'name','type','required','status','min_length','max_length'
        ]);

        // صورة
        if ($request->hasFile('image')) {
            $data['image'] = FileService::compressAndReplace(
                $request->file('image'),
                $this->uploadFolder,
                $customField->image ?? ''
            );
        }

        // القيم الأساسية للفيلد
        if (in_array($request->type, ['dropdown','radio','checkbox'])) {
            $vals = (array) $request->input('values', []);
            $vals = array_values(array_filter($vals, fn($v) => $v !== null && $v !== ''));
            $data['values'] = $vals
                ? json_encode($vals, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                : null;
        } else {
            $data['values'] = null; // غير مطلوبة للأنواع الأخرى
        }

        // تحديث الفيلد
        $customField->update($data);

        // ====== تحديث التصنيفات ======
        $old = $customField->custom_field_category->pluck('category_id')->toArray();
        $new = (array) $request->input('selected_categories', []);

        // احذف القديمة غير الموجودة في الجديدة
        foreach (array_diff($old, $new) as $catId) {
            $customField->custom_field_category->firstWhere('category_id', $catId)?->delete();
        }

        // أضف الجديدة غير الموجودة سابقًا
        $insertCategories = [];
        foreach (array_diff($new, $old) as $catId) {
            $insertCategories[] = [
                'category_id'     => (int) $catId,
                'custom_field_id' => (int) $customField->id,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }
        if (!empty($insertCategories)) {
            CustomFieldCategory::insert($insertCategories);
        }

        // ====== تحديث الترجمات (name + values) ======
        if ($request->has('translations')) {
            $namesMap  = (array) $request->input('translations.name', []);
            $valuesMap = (array) $request->input('translations.values', []);

            // كل اللغات الموجودة في أي من المصفوفتين
            $langIds = array_unique(array_map('intval',
                array_merge(array_keys($namesMap), array_keys($valuesMap))
            ));

            foreach ($langIds as $langId) {
                $tName = $namesMap[$langId]  ?? null;
                $tVals = $valuesMap[$langId] ?? null;

                // حضّر قيم الترجمة بحسب النوع
                $tValuesJson = null;
                if (in_array($request->type, ['dropdown','radio','checkbox'])) {
                    $tValsArr = is_array($tVals)
                        ? array_values(array_filter($tVals, fn($v) => $v !== null && $v !== ''))
                        : [];
                    $tValuesJson = $tValsArr
                        ? json_encode($tValsArr, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                        : null;
                }

                CustomFieldTranslation::updateOrCreate(
                    [
                        'custom_field_id' => (int) $customField->id,
                        'language_id'     => (int) $langId,
                    ],
                    [
                        'name'   => $tName ?: $data['name'],
                        'values' => in_array($request->type, ['dropdown','radio','checkbox'])
                            ? $tValuesJson
                            : null,
                    ]
                );
            }
        }

        DB::commit();
        ResponseService::successResponse("Custom Fields Updated Successfully");
    } catch (Throwable $th) {
        DB::rollBack();
        ResponseService::logErrorResponse($th, "CustomFieldController -> update");
        ResponseService::errorResponse('Something Went Wrong');
    }
}

    public function destroy($id)
    {
        try {
            ResponseService::noPermissionThenSendJson('custom-field-delete');
            $customField = CustomField::findOrFail($id);
            $customField->delete();
            ResponseService::successResponse('Custom Field deleted successfully');
        } catch (QueryException $th) {
            ResponseService::logErrorResponse($th, "CustomFieldController -> destroy");
            ResponseService::errorResponse('Cannot delete custom field! Remove associated subcategories first.');
        }
    }
}

