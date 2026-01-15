<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model {
    use HasFactory;

    protected $fillable = [
        "id",
        "name",      // للحفاظ على التوافق مع الكود القديم
        "name_en",
        "name_ar",
        "state_id",
        "state_code",
        "country_id",
        "country_code",
        "latitude",
        "longitude",
        "created_at",
        "updated_at",
        "flag",
        "wikiDataId",
    ];

    // Append virtual attribute 'name' to JSON output
    //protected $appends = ['name'];

    // إخفاء الأعمدة الحقيقية إن رغبت
    //protected $hidden = ['name_en', 'name_ar'];

    public function state() {
        return $this->belongsTo(State::class);
    }

    public function country() {
        return $this->belongsTo(Country::class);
    }

    public function areas(): HasMany {
        return $this->hasMany(Area::class);
    }

    /*
    // Accessor لاسم المدينة حسب اللغة
    public function getNameAttribute() {
        $locale = app()->getLocale() ?? 'en';

        if ($locale === 'ar' && !empty($this->name_ar)) {
            return $this->name_ar;
        }

        return $this->name_en ?? $this->attributes['name'] ?? null;
    }
     */
    // تحديث scopeSearch: نبحث باللغتين داخل المدينة والولاية والبلد
    public function scopeSearch($query, $search) {
        $search = "%" . $search . "%";

        $query = $query->where(function ($q) use ($search) {
            $q->orWhere('id', 'LIKE', $search)
              ->orWhere('name_en', 'LIKE', $search)
              ->orWhere('name_ar', 'LIKE', $search)
              ->orWhere('name', 'LIKE', $search)
              ->orWhere('state_id', 'LIKE', $search)
              ->orWhere('state_code', 'LIKE', $search)
              ->orWhere('country_id', 'LIKE', $search)
              ->orWhere('country_code', 'LIKE', $search)
              ->orWhereHas('state', function ($q) use ($search) {
                  $q->where('name_en', 'LIKE', $search)
                    ->orWhere('name_ar', 'LIKE', $search)
                    ->orWhere('name', 'LIKE', $search);
              })
              ->orWhereHas('country', function ($q) use ($search) {
                  $q->where('name_en', 'LIKE', $search)
                    ->orWhere('name_ar', 'LIKE', $search)
                    ->orWhere('name', 'LIKE', $search);
              });
        });

        return $query;
    }

    public function scopeSort($query, $column, $order) {
        if ($column == "country_name") {
            $query = $query->leftJoin('countries', 'countries.id', '=', 'cities.country_id')
                           ->orderBy('countries.name', $order);
        } else if ($column == "state_name") {
            $query = $query->leftJoin('states', 'states.id', '=', 'cities.state_id')
                           ->orderBy('states.name', $order);
        } else {
            $query = $query->orderBy($column, $order);
        }

        return $query->select('cities.*');
    }

    public function scopeFilter($query, $filterObject) {
        if (!empty($filterObject)) {
            foreach ($filterObject as $column => $value) {
                if($column == "state_name") {
                    $query->whereHas('state', function ($query) use ($value) {
                        $query->where('state_id', $value);
                    });
                } elseif($column == "country_name") {
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

