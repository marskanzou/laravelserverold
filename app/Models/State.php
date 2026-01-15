<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model {
    use HasFactory;

    protected $fillable = [
        "id",
        "name",      // للحفاظ على التوافق مع الكود القديم
        "name_en",
        "name_ar",
        "state_code",
        "latitude",
        "longitude",
        "type",
        "country_id",
        "country_code",
        "fips_code",
        "iso2",
        "created_at",
        "updated_at",
        "flag",
        "wikiDataId",
    ];

    // Append virtual attribute 'name' to JSON output (سيتم تعبئته عن طريق accessor)
    protected $appends = ['name'];

    // أخفي الأعمدة الحقيقية إن رغبت (لتقليل الضوضاء في الـ API)
    protected $hidden = ['name_en', 'name_ar'];

    // Accessor لاسم الولاية حسب لغة التطبيق
    public function getNameAttribute() {
        $locale = app()->getLocale() ?? 'en';

        // إذا اللغة عربية واسم عربي موجود - أرجع العربي
        if ($locale === 'ar' && !empty($this->attributes['name_ar'])) {
            return $this->attributes['name_ar'];
        }

        // fallback إلى name_en أو name
        return $this->attributes['name_en'] ?? $this->attributes['name'] ?? null;
    }

    public function country() {
        return $this->belongsTo(Country::class);
    }

    public function cities() {
        return $this->hasMany(City::class);
    }

    // تحديث scopeSearch: نبحث دائماً في name_en و name_ar و اسم البلد باللغتين
    public function scopeSearch($query, $search) {
        $search = "%" . $search . "%";
        $query = $query->where(function ($q) use ($search) {
            $q->orWhere('id', 'LIKE', $search)
                ->orWhere('name_en', 'LIKE', $search)
                ->orWhere('name_ar', 'LIKE', $search)
                ->orWhere('name', 'LIKE', $search)
                ->orWhere('country_id', 'LIKE', $search)
                ->orWhere('state_code', 'LIKE', $search)
                ->orWhereHas('country', function ($q) use ($search) {
                    $q->where('name_en', 'LIKE', $search)
                      ->orWhere('name_ar', 'LIKE', $search)
                      ->orWhere('name', 'LIKE', $search);
                });
        });
        return $query;
    }

    // الاحتفاظ بباقي scopes (sort/filter) كما هي، لكن لاحقًا سنضبط الـ ordering في الدالة controller عندما يتعلق الأمر باسم البلد
    public function scopeSort($query, $column, $order) {
        if ($column == "country_name") {
            $query = $query->leftJoin('countries', 'countries.id', '=', 'states.country_id')
                           // نترك اختيار حقل الترتيب في controller حسب اللغة
                           ->orderBy('countries.name', $order);
        } else {
            $query = $query->orderBy($column, $order);
        }

        return $query->select('states.*');
    }

    public function scopeFilter($query, $filterObject) {
        if (!empty($filterObject)) {
            foreach ($filterObject as $column => $value) {
                if($column == "country_name") {
                    $query->whereHas('country', function ($query) use ($value) {
                        $query->where('country_id', $value);
                    });
                } else {
                    $query->where((string)$column, (string)$value);
                }
            }
        }
        return $query;
    }
}

