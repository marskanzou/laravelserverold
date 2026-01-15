<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver; // أو Imagick\Driver
use RuntimeException;

class ChatFileService {

    protected static function getImageManager(): ImageManager
    {
        // للإصدار 3.x
        return new ImageManager(new Driver());
        
        // إذا كنت تستخدم Imagick بدلاً من GD:
        // return new ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());
    }

    public static function compressAndUpload($requestFile, $folder) {
        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();

        if (in_array($requestFile->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
            $manager = self::getImageManager();
            $image = $manager->read($requestFile->getPathname()); // read بدلاً من make
            
            $image = $image->scale(width: $image->width()); // للحفاظ على التوجيه
            
            $encoded = $image->toJpeg(quality: 60); // أو toPng() أو toWebp()

            Storage::disk(config('filesystems.default'))->put($folder . '/' . $file_name, (string)$encoded);
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
        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();

        try {
            if (in_array($requestFile->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
                $manager = self::getImageManager();

                $watermarkPath = Setting::where('name', 'watermark_image')->value('value');
                $fullWatermarkPath = storage_path('app/public/' . $watermarkPath);

                $imagePath = $requestFile->getPathname();
                if (!file_exists($imagePath) || !is_readable($imagePath)) {
                    throw new RuntimeException("Uploaded image file is not readable at path: " . $imagePath);
                }

                $image = $manager->read($imagePath);
                
                if (!empty($watermarkPath) && file_exists($fullWatermarkPath)) {
                    $watermark = $manager->read($fullWatermarkPath);
                    
                    // تغيير حجم العلامة المائية
                    $watermark = $watermark->scale(
                        width: $image->width(),
                        height: $image->height()
                    );
                    
                    // إضافة الشفافية (opacity)
                    $watermark = $watermark->reduceColors(255)->brightness(-90);
                    
                    // دمج العلامة المائية
                    $image->place($watermark, 'center');
                }

                $encoded = $image->toJpeg(quality: 60);
                Storage::disk(config('filesystems.default'))->put($folder . '/' . $file_name, (string)$encoded);
            } else {
                $requestFile->storeAs($folder, $file_name, 'public');
            }

            return $folder . '/' . $file_name;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    public static function compressAndReplaceWithWatermark($requestFile, $folder, $deleteRawOriginalImage = null) {
        if (!empty($deleteRawOriginalImage)) {
            self::delete($deleteRawOriginalImage);
        }
        return self::compressAndUploadWithWatermark($requestFile, $folder);
    }
}
