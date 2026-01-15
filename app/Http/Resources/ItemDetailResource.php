<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ItemDetailResource extends JsonResource
{
    public function toArray($request)
    {
        $isAuth = Auth::check();
        $authUserId = $isAuth ? Auth::id() : null;

        $item = $this->resource->toArray();

        // الإعلان المميز
        $item['is_feature'] = $this->relationLoaded('featured_items')
            ? count($this->featured_items) > 0
            : false;

        // الإعجابات
        $item['total_likes'] = $this->relationLoaded('favourites')
            ? $this->favourites->count()
            : 0;

        $item['is_liked'] = $isAuth && $this->relationLoaded('favourites')
            ? $this->favourites->where('user_id', $authUserId)->count() > 0
            : false;

        // معلومات المستخدم
        if ($this->relationLoaded('user') && $this->user) {
            $item['user'] = $this->user->toArray();

            if ($this->user->relationLoaded('sellerReview')) {
                $reviews = $this->user->sellerReview;
                $item['user']['reviews_count'] = $reviews->count();
                $item['user']['average_rating'] = $reviews->avg('ratings');
            } else {
                $item['user']['reviews_count'] = 0;
                $item['user']['average_rating'] = 0;
            }

            if ($this->user->show_personal_details == 0) {
                $item['user']['mobile'] = '';
                $item['user']['country_code'] = '';
                $item['user']['email'] = '';
            }
        }

        // الحقول المخصصة
        if ($this->relationLoaded('item_custom_field_values')) {
            $item['custom_fields'] = [];
            foreach ($this->item_custom_field_values as $customFieldValue) {
                $tempRow = [];

                if ($customFieldValue->relationLoaded('custom_field') && $customFieldValue->custom_field) {
                    $tempRow = $customFieldValue->custom_field->toArray();

                    if ($customFieldValue->custom_field->type == "fileinput") {
                        $files = (array) $customFieldValue->value;
                        $tempRow['value'] = array_map(fn($file) => url(Storage::url($file)), array_filter($files));
                    } else {
                        $tempRow['value'] = $customFieldValue->value ?? [];
                    }

                    $tempRow['custom_field_value'] = $customFieldValue->toArray();
                    unset($tempRow['custom_field_value']['custom_field']);
                }

                $item['custom_fields'][] = $tempRow;
            }
        }

        // عروض، تقارير، وظائف، شراء
        $item['is_already_offered'] = $isAuth && $this->relationLoaded('item_offers')
            ? $this->item_offers->where('buyer_id', $authUserId)->count() > 0
            : false;

        $item['is_already_reported'] = $isAuth && $this->relationLoaded('user_reports')
            ? $this->user_reports->where('user_id', $authUserId)->count() > 0
            : false;

        $item['is_purchased'] = $isAuth
            ? ($this->sold_to == $authUserId ? 1 : 0)
            : 0;

        $item['is_already_job_applied'] = $isAuth && $this->relationLoaded('job_applications')
            ? $this->job_applications->where('user_id', $authUserId)->count() > 0
            : false;

        return $item;
    }
}
