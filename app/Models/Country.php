<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model {
    use HasFactory;

    protected $fillable = [
        'id',
        'name_en',
        'name_ar',
        'iso3',
        'numeric_code',
        'iso2',
        'phonecode',
        'capital',
        'currency',
        'currency_name',
        'currency_symbol',
        'tld',
        'native',
        'region',
        'region_id',
        'subregion',
        'subregion_id',
        'nationality',
        'timezones',
        'translations',
        'latitude',
        'longitude',
        'emoji',
        'emojiU',
        'created_at',
        'updated_at',
        'flag',
        'wikiDataId'
    ];

    // Append virtual attribute 'name' to JSON output
    protected $appends = ['name'];

    // إذا أردت إخفاء الأعمدة الحقيقية عن الـ API:
    protected $hidden = ['name_en', 'name_ar'];

    // Accessor لاسم البلد حسب اللغة
    public function getNameAttribute() {
        // ✅ نقرأ من الـ header مباشرة
     $locale = app()->getLocale();
        // إن كانت الواجهة عربية واسم عربي موجود - أرجع العربي
        if ($locale === 'ar' && !empty($this->attributes['name_ar'])) {
            return $this->attributes['name_ar'];
        }
        // في كل الحالات أرجع الإنجليزي كـ fallback
        return $this->attributes['name_en'] ?? $this->attributes['name'] ?? null;
    }

    // تعديل scopeSearch ليفحص name_en و name_ar أيضا
    public function scopeSearch($query, $search) {
        $search = "%" . $search . "%";
        $query = $query->where(function ($q) use ($search) {
            $q->orWhere('id', 'LIKE', $search)
                ->orWhere('name_en', 'LIKE', $search)
                ->orWhere('name_ar', 'LIKE', $search)
                ->orWhere('numeric_code', 'LIKE', $search)
                ->orWhere('phonecode', 'LIKE', $search);
        });
        return $query;
    }

    public function states() {
        return $this->hasMany(State::class);
    }
}

