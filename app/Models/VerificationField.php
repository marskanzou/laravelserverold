<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Throwable;

class VerificationField extends Model
{
    use HasFactory ,  SoftDeletes;
    protected $fillable = [
        'name',
        'type',
        'status',
        'values',
        'min_length',
        'max_length',
        'is_required',
        'deleted_at'
    ];
    protected $hidden = ['created_at', 'updated_at'];

    public function getValuesAttribute($value) {
        try {
            return array_values(json_decode($value, true, 512, JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            return $value;
        }
    }

    public function scopeSearch($query, $search) {
        $search = "%" . $search . "%";
        return $query->where(function ($q) use ($search) {
            $q->orWhere('name', 'LIKE', $search)
                ->orWhere('type', 'LIKE', $search)
                ->orWhere('values', 'LIKE', $search)
                ->orWhere('status', 'LIKE', $search);
        });
    }
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function values()
    {
        return $this->hasMany(VerificationFieldValue::class);
    }
    public function verification_field_values()
    {
        return $this->hasMany(VerificationFieldValue::class, 'verification_field_id');
    }
}
