<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\CustomField;
use App\Models\CustomFieldCategory;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use function compact;
use function view;

class CategoryController extends Controller {
    private string $uploadFolder;

    public function __construct() {
        $this->uploadFolder = "category";
    }

    public function index() {
        ResponseService::noAnyPermissionThenRedirect(['category-list', 'category-create', 'category-update', 'category-delete']);
        return view('category.index');
    }

    public function create(Request $request) {
        $languages = CachingService::getLanguages()->where('code', '!=', 'en')->values();
        ResponseService::noPermissionThenRedirect('category-create');
        $categories = Category::with('subcategories')->get();
        return view('category.create', compact('categories', 'languages'));
    }

    public function store(Request $request) {
        ResponseService::noPermissionThenSendJson('category-create');
        $request->validate([
            'name'               => 'required',
            'image'              => 'nullable|mimes:jpg,jpeg,png|max:7168',
            'parent_category_id' => 'nullable|integer',
            'description'        => 'nullable',
            'slug'               => 'required',
            'status'             => 'required|boolean',
            'translations'       => 'nullable|array',
            'translations.*'     => 'nullable|string',
        ]);

        try {
            $data = $request->all();
            $data['slug'] = HelperService::generateUniqueSlug(new Category(), $request->slug);

            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndUpload($request->file('image'), $this->uploadFolder);
            }

            $category = Category::create($data);

            if (!empty($request->translations)) {
                foreach ($request->translations as $key => $value) {
                    if (!empty($value)) {
                        $category->translations()->create([
                            'name'        => $value,
                            'language_id' => $key,
                        ]);
                    }
                }
            }

            ResponseService::successRedirectResponse("Category Added Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorRedirectResponse();
        }
    }


    public function show(Request $request, $id) {
        ResponseService::noPermissionThenSendJson('category-list');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');
        $sql = Category::withCount('subcategories')->withCount('custom_fields')->with('subcategories');
        if ($id == "0") {
            $sql->whereNull('parent_category_id');
        } else {
            $sql->where('parent_category_id', $id);
        }
        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        if ($sort !== 'advertisements_count') {
            $sql->orderBy($sort, $order);
        }
        $result = $sql->get();


        if ($sort === 'advertisements_count') {
            $result = $result->sortBy(function ($category) {
                return $category->all_items_count;
            }, SORT_REGULAR, strtolower($order) === 'desc')->values();

            $result = $result->slice($offset, $limit)->values();
        } else {
            $result = $result->slice($offset, $limit);
        }
        $total = $sql->count();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;

        foreach ($result as $key => $row) {
            $operate = '';
            if (Auth::user()->can('category-update')) {
                $operate .= BootstrapTableService::editButton(route('category.edit', $row->id));
            }

            if (Auth::user()->can('category-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('category.destroy', $row->id));
            }
            if ($row->subcategories_count > 1) {
                $operate .= BootstrapTableService::button('fa fa-list-ol',route('sub.category.order.change', $row->id),['btn-secondary']);
            }
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $tempRow['advertisements_count'] = $row->all_items_count;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id) {
        ResponseService::noPermissionThenRedirect('category-update');
        $category_data = Category::findOrFail($id);
        // Fetch translations for the category
        $translations = $category_data->translations->pluck('name', 'language_id')->toArray();

        $parent_category_data = Category::find($category_data->parent_category_id);
        $parent_category = $parent_category_data->name ?? '';
        $categories = Category::with('subcategories')->get();
        // Fetch all languages
        $languages = CachingService::getLanguages()->where('code', '!=', 'en')->values();

        return view('category.edit', compact('category_data', 'parent_category_data','parent_category', 'translations', 'languages','categories'));
    }

    public function update(Request $request, $id) {
        ResponseService::noPermissionThenSendJson('category-update');
        try {
            $request->validate([
                'name'            => 'nullable',
                'image'           => 'nullable|mimes:jpg,jpeg,png|max:7168',
                'parent_category_id' => 'nullable|integer',
                'description'     => 'nullable',
                'slug'            => 'nullable',
                'status'          => 'required|boolean',
                'translations'    => 'nullable|array',
                'translations.*'  => 'nullable|string',
            ]);
            $category = Category::find($id);
            if ($request->parent_category_id == $category->id) {
                return back()->withErrors(['parent_category' => 'A category cannot be set as its own parent.']);
            }
            $data = $request->all();
            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndReplace($request->file('image'), $this->uploadFolder, $category->getRawOriginal('image'));
            }
            $data['slug'] = HelperService::generateUniqueSlug(new Category(), $request->slug, $category->id);
            $category->update($data);
            if ($request->has('is_job_category')) {
                $category->subcategories()->update([
                    'is_job_category' => $request->is_job_category ? 1 : 0,
                ]);
            }
            $category->update($data);
            if ($request->has('price_optional')) {
                $category->subcategories()->update([
                    'price_optional' => $request->price_optional ? 1 : 0,
                ]);
            }
            if (!empty($request->translations)) {
                $categoryTranslations = [];
                foreach ($request->translations as $key => $value) {
                    $categoryTranslations[] = [
                        'category_id' => $category->id,
                        'language_id' => $key,
                        'name'        => $value,
                    ];
                }

                if (count($categoryTranslations) > 0) {
                    CategoryTranslation::upsert($categoryTranslations, ['category_id', 'language_id'], ['name']);
                }
            }

            ResponseService::successRedirectResponse("Category Updated Successfully", route('category.index'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    public function destroy($id) {
        ResponseService::noPermissionThenSendJson('category-delete');
        try {
            $category = Category::find($id);
            if ($category->items_count > 0) {
                ResponseService::errorResponse('Cannot delete category. It has associated advertisements.');
            }
            if ($category->delete()) {
                ResponseService::successResponse('Category delete successfully');
            }
        } catch (QueryException $th) {
            ResponseService::logErrorResponse($th, 'Failed to delete category', 'Cannot delete category. Remove associated subcategories and custom fields first.');
            ResponseService::errorResponse('Something Went Wrong');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "CategoryController -> delete");
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function getSubCategories($id) {
        ResponseService::noPermissionThenRedirect('category-list');
        $subcategories = Category::where('parent_category_id', $id)
            ->with('subcategories')
            ->withCount('custom_fields')
            ->withCount('subcategories')
            ->withCount('items')
            ->orderBy('sequence')
            ->get()
            ->map(function ($subcategory) {
                $operate = '';
                if (Auth::user()->can('category-update')) {
                    $operate .= BootstrapTableService::editButton(route('category.edit', $subcategory->id));
                }
                if (Auth::user()->can('category-delete')) {
                    $operate .= BootstrapTableService::deleteButton(route('category.destroy', $subcategory->id));
                }
                if ($subcategory->subcategories_count > 1) {
                    $operate .= BootstrapTableService::button('fa fa-list-ol',route('sub.category.order.change',$subcategory->id),['btn-secondary']);
                }
                $subcategory->operate = $operate;
                return $subcategory;
            });

        return response()->json($subcategories);
    }

    public function customFields($id) {
        ResponseService::noPermissionThenRedirect('custom-field-list');
        $category = Category::find($id);
        $p_id = $category->parent_category_id;
        $cat_id = $category->id;
        $category_name = $category->name;

        return view('category.custom-fields', compact('cat_id', 'category_name', 'p_id'));
    }

    public function getCategoryCustomFields(Request $request, $id) {
        ResponseService::noPermissionThenSendJson('custom-field-list');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        $sql = CustomField::whereHas('categories', static function ($q) use ($id) {
            $q->where('category_id', $id);
        })->orderBy($sort, $order);

        if (isset($request->search)) {
            $sql->search($request->search);
        }

        $sql->take($limit);
        $total = $sql->count();
        $res = $sql->skip($offset)->take($limit)->get();
        $bulkData = array();
        $rows = array();
        $tempRow['type'] = '';


        foreach ($res as $row) {
            $tempRow = $row->toArray();
//            $operate = BootstrapTableService::editButton(route('custom-fields.edit', $row->id));
            $operate = BootstrapTableService::deleteButton(route('category.custom-fields.destroy', [$id, $row->id]));
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        $bulkData['total'] = $total;
        return response()->json($bulkData);
    }

    public function destroyCategoryCustomField($categoryID, $customFieldID) {
        try {
            ResponseService::noPermissionThenRedirect('custom-field-delete');
            CustomFieldCategory::where(['category_id' => $categoryID, 'custom_field_id' => $customFieldID])->delete();
            ResponseService::successResponse("Custom Field Deleted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "CategoryController -> destroyCategoryCustomField");
            ResponseService::errorResponse('Something Went Wrong');
        }

    }

    public function categoriesReOrder(Request $request) {
        $categories = Category::whereNull('parent_category_id')->orderBy('sequence')->get();
        return view('category.categories-order', compact('categories'));
    }

    public function subCategoriesReOrder(Request $request ,$id) {
        $categories = Category::with('subcategories')->where('parent_category_id', $id)->orderBy('sequence')->get();
        return view('category.sub-categories-order', compact('categories'));
    }

    public function updateOrder(Request $request) {
        $request->validate([
           'order' => 'required|json'
        ]);
        try {

            $order = json_decode($request->input('order'), true);
            $data = [];
        foreach ($order as $index => $id) {
            $data[] = [
                'id' => $id,
                'sequence' => $index + 1,
            ];
        }
        Category::upsert($data, ['id'], ['sequence']);
        ResponseService::successResponse("Order Updated Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorResponse('Something Went Wrong');
        }
    }
}
