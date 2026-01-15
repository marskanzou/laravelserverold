<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use Throwable;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'image',
        'required',
        'status',
        'values',
        'min_length',
        'max_length',
    ];

    protected $hidden = ['created_at', 'updated_at'];
    // احذف $appends إن ما عندك Accessor مطابق مثل getValueAttribute
    protected $appends = []; 

    /*================== العلاقات ==================*/
    public function custom_field_category()
    {
        return $this->hasMany(CustomFieldCategory::class, 'custom_field_id');
    }

    public function item_custom_field_values()
    {
        return $this->hasMany(ItemCustomFieldValue::class);
    }

    public function categories()
    {
        // اسم جدول الربط وليس الكلاس
        return $this->belongsToMany(
            Category::class,
            'custom_field_categories',
            'custom_field_id',
            'category_id'
        );
    }

    public function translations()
    {
        return $this->hasMany(CustomFieldTranslation::class, 'custom_field_id', 'id');
    }

    /*================== الترجمة ==================*/
    public function translationByLanguage(?string $languageCode = null)
    {
        $languageCode = $languageCode ?: App::getLocale();
        $language = Language::where('code', $languageCode)->first(); // use App\Models\Language
        if (!$language) {
            return null;
        }

        // استخدم العلاقة المحمّلة لتفادي N+1
        if ($this->relationLoaded('translations')) {
            return $this->getRelation('translations')->firstWhere('language_id', $language->id);
        }

        // Fallback عند عدم التحميل المسبق
        return $this->translations()->where('language_id', $language->id)->first();
    }

    /*================== Accessors ==================*/

    // اسم الحقل مترجم إن وُجد
    public function getNameAttribute($value)
    {
        return $this->translationByLanguage()?->name ?? $value;
    }

    /**
     * القيم الأساسية (values) كقيمة نهائية تُراعي الترجمة إن وُجدت
     * وتفك JSON عند الحاجة.
     */
    public function getValuesAttribute($value)
    {
        // فكّ قيمة الجدول الأساسية لو كانت JSON string
        $base = $value;
        try {
            $base = is_string($value) ? (json_decode($value, true, 512, JSON_THROW_ON_ERROR)) : $value;
        } catch (Throwable) {
            // ignore, keep as-is
        }

        $t = $this->translationByLanguage();
        $tv = $t?->values;

        if ($tv === null) {
            return $base;
        }

        // ترجمة القيم (تفك JSON إن كانت نص)
        if (is_string($tv)) {
            $decoded = json_decode($tv, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $tv;
        }

        return $tv;
    }

    /**
     * قيم مترجمة مضمونة كمصفوفة لاستخدامها مباشرة في foreach.
     * (حتى لو كانت null ترجع [] ولا تُسبب أخطاء في Blade)
     */
    public function getTranslatedValuesAttribute()
    {
        $vals = $this->translationByLanguage()?->values ?? $this->getRawOriginal('values');

        if (is_null($vals)) {
            return [];
        }

        if (is_string($vals)) {
            $decoded = json_decode($vals, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded ?? [];
            }
            // نص عادي: رجّعه كمصفوفة بعنصر واحد
            return [$vals];
        }

        return is_array($vals) ? $vals : [$vals];
    }

    public function getImageAttribute($image)
    {
        return !empty($image) ? url(Storage::url($image)) : $image;
    }

    /*================== Scopes ==================*/
    public function scopeSearch($query, $search)
    {
        $search = "%{$search}%";
        return $query->where(function ($q) use ($search) {
            $q->orWhere('name', 'LIKE', $search)
              ->orWhere('type', 'LIKE', $search)
              ->orWhere('values', 'LIKE', $search)
              ->orWhere('status', 'LIKE', $search)
              ->orWhereHas('categories', function ($q) use ($search) {
                  $q->where('name', 'LIKE', $search);
              });
        });
    }

    public function scopeFilter($query, $filterObject)
    {
        if (!empty($filterObject)) {
            foreach ($filterObject as $column => $value) {
                if ($column === "category_names") {
                    $query->whereHas('custom_field_category', function ($q) use ($value) {
                        $q->where('category_id', $value);
                    });
                } elseif ($column === "type") {
                    $query->where('type', $value);
                } else {
                    $query->where((string) $column, (string) $value);
                }
            }
        }
        return $query;
    }
}

