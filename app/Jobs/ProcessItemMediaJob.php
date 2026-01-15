<?php

namespace App\Jobs;

use App\Models\Item;
use App\Models\ItemImages;
use App\Models\ItemCustomFieldValue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Imagick;

class ProcessItemMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $itemId;
    protected array $payload;

    public int $tries = 3;
    public int $timeout = 1200;
    public $backoff = [30, 120, 300];

    public function __construct(int $itemId, array $payload)
    {
        $this->itemId = $itemId;
        $this->payload = $payload;
        $this->onQueue('media');
    }

    public function handle()
    {
        $tag = "[ItemMediaJob:{$this->itemId}]";

        try {
            $item = Item::find($this->itemId);
            if (!$item) {
                \Log::warning("$tag Item not found");
                $this->cleanupTempFiles();
                return;
            }

            $anyUploaded = false;

            // ===== Main Image =====
            if (!empty($this->payload['image'])) {
                $rawPath = $this->payload['image'];
                $fullPath = $this->resolveFullPath($rawPath);

                if ($fullPath && is_readable($fullPath)) {
                    $avifPath = $this->convertToAvif($fullPath, 'items');
                    if ($avifPath) {
                        $item->update(['image' => $avifPath]);
                        $anyUploaded = true;
                    }
                    $this->deleteTempFile($rawPath, 'main_image');
                }
            }

            // ===== Gallery Images =====
            if (!empty($this->payload['gallery_images'])) {
                $inserts = [];
                foreach ($this->payload['gallery_images'] as $index => $rawPath) {
                    $fullPath = $this->resolveFullPath($rawPath);
                    if ($fullPath && is_readable($fullPath)) {
                        $avifPath = $this->convertToAvif($fullPath, 'items');
                        if ($avifPath) {
                            $inserts[] = [
                                'image' => $avifPath,
                                'item_id' => $item->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                            $anyUploaded = true;
                        }
                        $this->deleteTempFile($rawPath, "gallery_$index");
                    }
                }
                if ($inserts) {
                    ItemImages::insert($inserts);
                }
            }

            // ===== Custom Field Images =====
            if (!empty($this->payload['custom_field_files'])) {
                $fields = [];
                foreach ($this->payload['custom_field_files'] as $fieldId => $rawPath) {
                    $fullPath = $this->resolveFullPath($rawPath);
                    if ($fullPath && is_readable($fullPath)) {
                        $avifPath = $this->convertToAvif($fullPath, 'custom_fields_files');
                        if ($avifPath) {
                            $fields[] = [
                                'item_id' => $item->id,
                                'custom_field_id' => $fieldId,
                                'value' => $avifPath,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                            $anyUploaded = true;
                        }
                        $this->deleteTempFile($rawPath, "custom_$fieldId");
                    }
                }
                if ($fields) {
                    ItemCustomFieldValue::insert($fields);
                }
            }

            if ($anyUploaded) {
                $item->update(['active' => 'active']);
            }

        } catch (Throwable $e) {
            \Log::error("$tag Job failed", ['error' => $e->getMessage()]);
            $this->cleanupTempFiles();
            throw $e;
        }
    }

    // ===== AVIF Conversion =====
    protected function convertToAvif(string $sourcePath, string $folder): ?string
    {
        try {
            $imagick = new Imagick($sourcePath);
            $imagick->setImageFormat('avif');
            $imagick->setImageCompressionQuality(80);

            $name = uniqid('avif_', true) . '.avif';
            $path = "$folder/" . date('Y/m/d') . "/$name";
            $full = storage_path("app/public/$path");

            Storage::disk('public')->makeDirectory(dirname($path));
            $imagick->writeImage($full);

            $imagick->clear();
            $imagick->destroy();

            return file_exists($full) ? $path : null;

        } catch (Throwable $e) {
            \Log::error('AVIF error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ===== Helpers =====
    protected function resolveFullPath(string $relativePath): ?string
    {
        $relativePath = ltrim($relativePath, '/');

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->path($relativePath);
        }

        $shared = '/var/www/app/shared/storage/app/public/' . $relativePath;
        return is_readable($shared) ? $shared : null;
    }

    protected function deleteTempFile(string $path, string $type = ''): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function cleanupTempFiles(): void
    {
        foreach (['image', 'gallery_images', 'custom_field_files'] as $key) {
            if (empty($this->payload[$key])) continue;
            foreach ((array) $this->payload[$key] as $path) {
                $this->deleteTempFile($path, "cleanup_$key");
            }
        }
    }

    public function failed(Throwable $e): void
    {
        \Log::error('Job permanently failed', ['error' => $e->getMessage()]);
        $this->cleanupTempFiles();
    }
}
