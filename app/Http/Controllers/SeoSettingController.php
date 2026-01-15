<?php

namespace App\Http\Controllers;
use App\Models\SeoSetting;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SeoSettingController extends Controller
{
    private string $uploadFolder;

    public function __construct() {
        $this->uploadFolder = "seo-setting";
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page'            => 'required',
            'title'           => 'required',
            'description'     => 'required',
            'keywords'        => 'nullable',
            'image'           => 'required|mimes:jpeg,png,jpg,svg|max:7168',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $data = $request->all();
            if ($request->hasFile('image')) {
                $data['image'] = FileService::upload($request->file('image'), $this->uploadFolder);
            }
            SeoSetting::create($data);
            ResponseService::successResponse('Setting Successfully Added');
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th, "SeoSetting Controller -> Store");
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';

        $sql = SeoSetting::orderBy($sort, $order);

        if (!empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('code', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%");
        }
        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        foreach ($result as $key => $row) {
            $tempRow = $row->toArray();
            $operate = '';
            if ($row->code != "en") {
                $operate .= BootstrapTableService::editButton(route('seo-setting.update', $row->id), true);
                $operate .= BootstrapTableService::deleteButton(route('seo-setting.destroy', $row->id));
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'page'            => 'nullable',
            'title'           => 'nullable',
            'description'     => 'nullable',
            'keywords'        => 'nullable',
            'image'           => 'nullable|mimes:jpeg,png,jpg,svg|max:7168',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $seo_setting = SeoSetting::findOrFail($id);
            $data = $request->all();
            if ($request->hasFile('image')) {
                $data['image'] = FileService::replace($request->file('image'), $this->uploadFolder, $seo_setting->getRawOriginal('image'));
            }
            $seo_setting->update($data);
            ResponseService::successResponse('Seo Setting Updated successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "Seo Controller Controller --> update");
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
                $seo_setting = SeoSetting::findOrFail($id);
                $seo_setting->delete();
                FileService::delete($seo_setting->getRawOriginal('image'));
                ResponseService::successResponse('Seo Setting Deleted successfully');
            } catch (Throwable $th) {
                ResponseService::logErrorRedirect($th, "Language Controller --> Destroy");
                ResponseService::errorResponse('Something Went Wrong');
            }
    }
}
