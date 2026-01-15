<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{User, Category, Item, ItemImages, ItemCustomFieldValue, Setting};
use App\Services\{FileService, HelperService};
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Imagick;
use Illuminate\Support\Facades\Storage;

class CsvUploadController extends Controller
{
    public function index()
    {
        return view('csvupload.upload');
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,csv,xls',
            'images_zip' => 'required|file|mimes:zip|max:51200',
            'selected_user_email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $excel = $request->file('excel_file');
        $zipFile = $request->file('images_zip');
        $selectedEmail = $request->input('selected_user_email');

        $user = User::where('email', $selectedEmail)->first();
        if (!$user) {
            return back()->withErrors(['selected_user_email' => "User not found with email: {$selectedEmail}"]);
        }

        $extractPath = storage_path('app/public/tmp_images/' . uniqid('zip_', true));
        File::makeDirectory($extractPath, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipFile->getRealPath()) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            return back()->withErrors(['images_zip' => 'فشل في فك ضغط ملف الصور.']);
        }

        try {
            $spreadsheet = IOFactory::load($excel->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();

            if (empty($rows) || count($rows) < 2) {
                File::deleteDirectory($extractPath);
                return back()->withErrors(['excel_file' => 'Excel file is empty or missing data rows.']);
            }

            $header = array_map('trim', array_shift($rows));
            $success = 0;
            $fail = 0;
            $errors = [];

            $allExtractedFiles = collect(File::allFiles($extractPath))
                ->map(fn($f) => str_replace($extractPath.'/', '', $f->getRealPath()))
                ->toArray();

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                if (empty(array_filter($row))) continue;
                $data = array_combine($header, array_pad($row, count($header), null));

                try {
                    $rowValidator = Validator::make($data, [
                        'name' => 'required',
                        'category_id' => 'required|integer',
                        'description' => 'required',
                        'latitude' => 'required',
                        'longitude' => 'required',
                        'address' => 'required',
                        'country' => 'required',
                        'city' => 'required',
                        'slug' => 'nullable|regex:/^[a-z0-9-]+$/',
                    ]);
                    if ($rowValidator->fails()) {
                        $fail++;
                        $errors[$index + 2] = $rowValidator->errors()->first();
                        continue;
                    }

                    $category = Category::find($data['category_id']);
                    if (!$category) {
                        $fail++;
                        $errors[$index + 2] = "Category not found: {$data['category_id']}";
                        continue;
                    }

                    $isJobCategory   = (bool)$category->is_job_category;
                    $isPriceOptional = (bool)$category->price_optional;

                    if ($isJobCategory || $isPriceOptional) {
                        $priceValidator = Validator::make($data, [
                            'min_salary' => 'nullable|numeric|min:0',
                            'max_salary' => 'nullable|numeric|gte:min_salary',
                        ]);
                    } else {
                        $priceValidator = Validator::make($data, [
                            'price' => 'required|numeric|min:0',
                        ]);
                    }

                    if ($priceValidator->fails()) {
                        $fail++;
                        $errors[$index + 2] = $priceValidator->errors()->first();
                        continue;
                    }

                    $status = Setting::where('name','auto_approve_item')->value('value') == 1 ? 'approved':'review';
                    $slug = $data['slug'] ?? Str::slug($data['name']);
                    $uniqueSlug = HelperService::generateUniqueSlug(new Item(), $slug);

                    $itemData = [
                        'name'=>$data['name'],
                        'category_id'=>$data['category_id'],
                        'description'=>$data['description'],
                        'latitude'=>$data['latitude'],
                        'longitude'=>$data['longitude'],
                        'address'=>$data['address'],
                        'country'=>$data['country'],
                        'state'=>$data['state'] ?? '',
                        'city'=>$data['city'],
                        'slug'=>$uniqueSlug,
                        'status'=>$status,
                        'active'=>'deactive',
                        'user_id'=>$user->id,
                        'contact'=>$data['contact'] ?? null,
                        'show_only_to_premium'=>$data['show_only_to_premium'] ?? 0,
                    ];

                    if ($isJobCategory || $isPriceOptional) {
                        $itemData['min_salary'] = $data['min_salary'] ?? null;
                        $itemData['max_salary'] = $data['max_salary'] ?? null;
                    } else {
                        $itemData['price'] = $data['price'] ?? null;
                    }

                    $item = Item::create($itemData);

                    // ===== الصورة الرئيسية =====
                    if (!empty($data['image'])) {
                        $imageName = str_replace('\\','/', trim($data['image']));
                        $imageName = trim($imageName, "/");
                        $mainPath = $extractPath.'/'.$imageName;

                        if (!file_exists($mainPath)) {
                            $baseName = pathinfo($imageName, PATHINFO_FILENAME);
                            $found = collect(File::allFiles($extractPath))
                                ->first(fn($file) => strpos(basename($file), $baseName) !== false);
                            if ($found) $mainPath = $found->getRealPath();
                        }

                        if (file_exists($mainPath)) {
                            try {
                                $imagick = new Imagick();
                                $imagick->readImage($mainPath);
                                $imagick->setImageFormat('avif');
                                $imagick->setImageCompressionQuality(70);

                                $avifName = uniqid('avif_', true) . '.avif';
                                $yearMonth = date('Y/m/d');
                                $avifPath = "item_images/{$yearMonth}/{$avifName}";
                                $fullPath = storage_path("app/public/{$avifPath}");

                                Storage::disk('public')->makeDirectory(dirname($avifPath));
                                $imagick->writeImage($fullPath);

                                $item->update(['image' => $avifPath]);
                            } catch (\Throwable $e) {
                                \Log::error('AVIF conversion failed (main)', ['error' => $e->getMessage()]);
                                $uploadedFile = new UploadedFile(
                                    $mainPath,
                                    basename($mainPath),
                                    mime_content_type($mainPath),
                                    null,
                                    true
                                );
                                $item->update([
                                    'image'=>FileService::compressAndUpload($uploadedFile,'item_images')
                                ]);
                            }
                        } else {
                            $errors[$index+2] = "❌ لم يتم العثور على الصورة الرئيسية: {$imageName}. الملفات الموجودة: ".implode(', ', $allExtractedFiles);
                        }
                    }

                    // ===== صور المعرض =====
                    if (!empty($data['gallery_images'])) {
                        $paths = array_map('trim', explode(',', $data['gallery_images']));
                        $galleryImages = [];

                        foreach ($paths as $relativePath) {
                            if (!$relativePath) continue;
                            $galleryPath = $extractPath.'/'.str_replace('\\','/',$relativePath);

                            if (!file_exists($galleryPath)) {
                                $baseName = pathinfo($relativePath, PATHINFO_FILENAME);
                                $found = collect(File::allFiles($extractPath))
                                    ->first(fn($file) => strpos(basename($file), $baseName) !== false);
                                if ($found) $galleryPath = $found->getRealPath();
                            }

                            if (file_exists($galleryPath)) {
                                try {
                                    $imagick = new Imagick();
                                    $imagick->readImage($galleryPath);
                                    $imagick->setImageFormat('avif');
                                    $imagick->setImageCompressionQuality(70);

                                    $avifName = uniqid('avif_', true) . '.avif';
                                    $yearMonth = date('Y/m/d');
                                    $avifPath = "item_images/{$yearMonth}/{$avifName}";
                                    $fullPath = storage_path("app/public/{$avifPath}");

                                    Storage::disk('public')->makeDirectory(dirname($avifPath));
                                    $imagick->writeImage($fullPath);

                                    $galleryImages[] = [
                                        'image'=>$avifPath,
                                        'item_id'=>$item->id,
                                        'created_at'=>now(),
                                        'updated_at'=>now(),
                                    ];
                                } catch (\Throwable $e) {
                                    \Log::error('AVIF conversion failed (gallery)', ['error' => $e->getMessage()]);
                                    $uploadedFile = new UploadedFile(
                                        $galleryPath,
                                        basename($galleryPath),
                                        mime_content_type($galleryPath),
                                        null,
                                        true
                                    );
                                    $galleryImages[] = [
                                        'image'=>FileService::compressAndUpload($uploadedFile,'item_images'),
                                        'item_id'=>$item->id,
                                        'created_at'=>now(),
                                        'updated_at'=>now(),
                                    ];
                                }
                            } else {
                                $errors[$index+2] = "⚠️ لم يتم العثور على صورة المعرض: {$relativePath}. الملفات الموجودة: ".implode(', ',$allExtractedFiles);
                            }
                        }

                        if (!empty($galleryImages)) ItemImages::insert($galleryImages);
                    }

                    // ===== الحقول المخصصة =====
                    if (!empty($data['custom_fields'])) {
                        $customFields = json_decode($data['custom_fields'],true);
                        $itemCustomFieldValues = [];
                        if (is_array($customFields)){
                            foreach($customFields as $key=>$value){
                                $itemCustomFieldValues[] = [
                                    'item_id'=>$item->id,
                                    'custom_field_id'=>$key,
                                    'value'=>json_encode($value, JSON_THROW_ON_ERROR),
                                    'created_at'=>now(),
                                    'updated_at'=>now(),
                                ];
                            }
                        }
                        if(!empty($itemCustomFieldValues)) ItemCustomFieldValue::insert($itemCustomFieldValues);
                    }

                    $success++;

                } catch (\Throwable $th){
                    $fail++;
                    $errors[$index+2] = $th->getMessage();
                }
            }

            DB::commit();
            File::deleteDirectory($extractPath);

            return back()
                ->with('success',"Excel uploaded successfully. Success: {$success}, Failed: {$fail}")
                ->with('errors_list',$errors);

        } catch (\Throwable $th){
            DB::rollBack();
            File::deleteDirectory($extractPath);
            return back()->withErrors(['excel_file'=>'Excel processing failed: '.$th->getMessage()]);
        }
    }
}

