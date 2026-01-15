<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;

class ItemCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * This version is intentionally very lightweight:
     * - no heavy relations
     * - uses featured_items_count (withCount) to determine is_feature cheaply
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        try {
            $response = [];

            foreach ($this->collection as $key => $item) {
                // determine main image: only use $item->image (no gallery lookup)
                $mainImage = $item->image ?? null;

                // determine if featured: prefer counted value if present (cheaper)
                $isFeature = false;
                if (isset($item->featured_items_count)) {
                    $isFeature = (int)$item->featured_items_count > 0;
                } elseif ($item->relationLoaded('featured_items')) {
                    $isFeature = ($item->featured_items->count() > 0);
                }

                $response[$key] = [
                    'id' => $item->id,
                    'name' => $item->name ?? $item->title ?? null,
                    'slug' => $item->slug ?? null,
                    'price' => $item->price,
                    'country' => $item->country,
                    'state' => $item->state,
                    'city' => $item->city,
                    'image' => $mainImage,
                    'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
                    // lightweight flags
                    'is_feature' => $isFeature,
                    // keep a minimal likes count if preloaded, else 0
                    'total_likes' => isset($item->favourites_count) ? (int)$item->favourites_count : ( ($item->relationLoaded('favourites') ? $item->favourites->count() : 0) ),
                ];
            }

            /*** ترتيب العناصر: المميزة أولاً ***/
            $featuredRows = [];
            $normalRows = [];

            foreach ($response as $key => $value) {
                if (!empty($value['is_feature'])) {
                    $featuredRows[] = $value;
                } else {
                    $normalRows[] = $value;
                }
            }

            $response = array_merge($featuredRows, $normalRows);
            $totalCount = count($response);

            if ($this->resource instanceof AbstractPaginator) {
                return [
                    ...$this->resource->toArray(),
                    'data' => $response,
                    'total_item_count' => $totalCount,
                ];
            }

            return $response;

        } catch (Throwable $e) {
            // Fallback safe response so API doesn't break
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Keep pagination meta as in Laravel default behavior
     * (this method is optional as we already included meta inside toArray when paginator is present)
     */
    public function with($request)
    {
        if ($this->resource instanceof AbstractPaginator) {
            return [
                'meta' => $this->resource->toArray(),
            ];
        }

        return [];
    }
}

