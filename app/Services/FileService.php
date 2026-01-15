<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image; // ✅ استخدام ImageManagerStatic
use RuntimeException;

class FileService {

    public static function compressAndUpload($requestFile, $folder) {
        Image::configure(['driver' => 'imagick']); // أو 'gd' إذا لم يكن imagick موجود

        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();

        if (in_array($requestFile->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
            $image = Image::make($requestFile)
                          ->orientate()
                          ->encode(null, 60);

            Storage::disk(config('filesystems.default'))->put($folder . '/' . $file_name, (string)$image);
        } else {
            $requestFile->storeAs($folder, $file_name, 'public');
        }

        return $folder . '/' . $file_name;
    }

    public static function upload($requestFile, $folder) {
        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();
        Storage::disk(config('filesystems.default'))->putFileAs($folder, $requestFile, $file_name);
        return $folder . '/' . $file_name;
    }

    public static function replace($requestFile, $folder, $deleteRawOriginalImage) {
        self::delete($deleteRawOriginalImage);
        return self::upload($requestFile, $folder);
    }

    public static function compressAndReplace($requestFile, $folder, $deleteRawOriginalImage) {
        if (!empty($deleteRawOriginalImage)) {
            self::delete($deleteRawOriginalImage);
        }
        return self::compressAndUpload($requestFile, $folder);
    }

    public static function uploadLanguageFile($requestFile, $code) {
        $filename = $code . '.' . $requestFile->getClientOriginalExtension();
        if (file_exists(base_path('resources/lang/') . $filename)) {
            File::delete(base_path('resources/lang/') . $filename);
        }
        $requestFile->move(base_path('resources/lang/'), $filename);
        return $filename;
    }

    public static function deleteLanguageFile($file) {
        if (file_exists(base_path('resources/lang/') . $file)) {
            return File::delete(base_path('resources/lang/') . $file);
        }
        return true;
    }

    public static function delete($image) {
        if (!empty($image) && Storage::disk(config('filesystems.default'))->exists($image)) {
            return Storage::disk(config('filesystems.default'))->delete($image);
        }
        return true;
    }

    public static function compressAndUploadWithWatermark($requestFile, $folder) {
        Image::configure(['driver' => 'imagick']); // أو 'gd'

        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();

        try {
            if (in_array($requestFile->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
                $watermarkPath = Setting::where('name', 'watermark_image')->value('value');
                $fullWatermarkPath = storage_path('app/public/' . $watermarkPath);
                $watermark = null;

                $imagePath = $requestFile->getPathname();
                if (!file_exists($imagePath) || !is_readable($imagePath)) {
                    throw new RuntimeException("Uploaded image file is not readable at path: " . $imagePath);
                }

                $image = Image::make($imagePath)->encode(null, 60);
                $imageWidth = $image->width();
                $imageHeight = $image->height();

                if (!empty($watermarkPath) && file_exists($fullWatermarkPath)) {
                    $watermark = Image::make($fullWatermarkPath)
                        ->resize($imageWidth, $imageHeight, function ($constraint) {
                            $constraint->aspectRatio();
                        })
                        ->opacity(10);
                }

                if ($watermark) {
                    $image->insert($watermark, 'center');
                }

                Storage::disk(config('filesystems.default'))->put($folder . '/' . $file_name, (string)$image->encode());
            } else {
                $requestFile->storeAs($folder, $file_name, 'public');
            }

            return $folder . '/' . $file_name;

        } catch (Exception $e) {
            throw new RuntimeException($e);
        }
    }

    public static function compressAndReplaceWithWatermark($requestFile, $folder, $deleteRawOriginalImage = null) {
        Image::configure(['driver' => 'imagick']); // أو 'gd'

        if (!empty($deleteRawOriginalImage)) {
            self::delete($deleteRawOriginalImage);
        }

        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();

        try {
            if (in_array($requestFile->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
                $watermarkPath = Setting::where('name', 'watermark_image')->value('value');
                $fullWatermarkPath = storage_path('app/public/' . $watermarkPath);
                $watermark = null;

                $imagePath = $requestFile->getPathname();
                if (!file_exists($imagePath) || !is_readable($imagePath)) {
                    throw new RuntimeException("Uploaded image file is not readable at path: " . $imagePath);
                }

                $image = Image::make($imagePath)->encode(null, 60);
                $imageWidth = $image->width();
                $imageHeight = $image->height();

                if (!empty($watermarkPath) && file_exists($fullWatermarkPath)) {
                    $watermark = Image::make($fullWatermarkPath)
                        ->resize($imageWidth, $imageHeight, function ($constraint) {
                            $constraint->aspectRatio();
                        })
                        ->opacity(10);
                }

                if ($watermark) {
                    $image->insert($watermark, 'center');
                }

                Storage::disk(config('filesystems.default'))->put($folder . '/' . $file_name, (string)$image->encode());
            } else {
                $requestFile->storeAs($folder, $file_name, 'public');
            }

            return $folder . '/' . $file_name;

        } catch (Exception $e) {
            throw new RuntimeException($e);
        }
    }
}
