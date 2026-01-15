<?php
namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use JsonSerializable;
use Throwable;

class ItemCollectionHeavy extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     * @throws Throwable
     */
    public function toArray(Request $request)
    {
        try {
            $response = [];
            $isAuth = Auth::check();
            $authUserId = $isAuth ? Auth::id() : null;

            foreach ($this->collection as $key => $collection) {

                // ✅ حماية ضد null
                if (!$collection) {
                    $response[$key] = [];
                    continue;
                }

                $response[$key] = $collection->toArray();

                /*
                 * Featured Items
                 */
                if ($collection->status == "approved" && $collection->relationLoaded('featured_items')) {
                    $response[$key]['is_feature'] = count($collection->featured_items) > 0;
                } else {
                    $response[$key]['is_feature'] = false;
                }

                /*
                 * Favourites
                 */
                if ($collection->relationLoaded('favourites')) {
                    $response[$key]['total_likes'] = $collection->favourites->count();
                    if ($isAuth) {
                        $response[$key]['is_liked'] =
                            $collection->favourites
                                ->where('item_id', $collection->id)
                                ->where('user_id', $authUserId)
                                ->count() > 0;
                    } else {
                        $response[$key]['is_liked'] = false;
                    }
                }

                /*
                 * User Info
                 */
                if ($collection->relationLoaded('user') && !is_null($collection->user)) {
                    $response[$key]['user'] = $collection->user;
                    if ($collection->user->relationLoaded('sellerReview')) {
                        $reviews = $collection->user->sellerReview;
                        $response[$key]['user']['reviews_count'] = $reviews->count();
                        $response[$key]['user']['average_rating'] = $reviews->avg('ratings');
                    } else {
                        $response[$key]['user']['reviews_count'] = 0;
                        $response[$key]['user']['average_rating'] = 0;
                    }
                    if ($collection->user->show_personal_details == 0) {
                        $response[$key]['user']['mobile'] = '';
                        $response[$key]['user']['country_code'] = '';
                        $response[$key]['user']['email'] = '';
                    }
                }

                /*
                 * Custom Fields
                 */
                if ($collection->relationLoaded('item_custom_field_values')) {
                    $response[$key]['custom_fields'] = [];
                    foreach ($collection->item_custom_field_values as $key2 => $customFieldValue) {
                        $tempRow = [];
                        if ($customFieldValue->relationLoaded('custom_field') && !empty($customFieldValue->custom_field)) {
                            $tempRow = $customFieldValue->custom_field->toArray();

                            // File Input Support
                            if ($customFieldValue->custom_field->type == "fileinput") {
                                $files = (array) $customFieldValue->value;
                                $tempRow['value'] = array_map(
                                    fn($file) => url(Storage::url($file)),
                                    array_filter($files)
);
                            } else {
                                $tempRow['value'] = $customFieldValue->value ?? [];
                            }

                            // ✅ حماية ضد null
                            $tempRow['custom_field_value'] = $customFieldValue ? $customFieldValue->toArray() : [];

                            unset($tempRow['custom_field_value']['custom_field']);
                            $response[$key]['custom_fields'][$key2] = $tempRow;
                        }
                    }
                    unset($response[$key]['item_custom_field_values']);
                }

                /*
                 * Item Offers
                 */
                if ($collection->relationLoaded('item_offers') && $isAuth) {
                    $response[$key]['is_already_offered'] =
                        $collection->item_offers
                            ->where('item_id', $collection->id)
                            ->where('buyer_id', $authUserId)
                            ->count() > 0;
                } else {
                    $response[$key]['is_already_offered'] = false;
                }

                /*
                 * User Reports
                 */
                if ($collection->relationLoaded('user_reports') && $isAuth) {
                    $response[$key]['is_already_reported'] =
                        $collection->user_reports
                            ->where('user_id', $authUserId)
                            ->count() > 0;
                } else {
                    $response[$key]['is_already_reported'] = false;
                }

                /*
                 * Purchase Info
                 */
                if ($isAuth) {
                    $response[$key]['is_purchased'] = $collection->sold_to == $authUserId ? 1 : 0;
                } else {
                    $response[$key]['is_purchased'] = 0;
                }

                /*
                 * Job Applications
                 */
                if ($collection->relationLoaded('job_applications') && $isAuth) {
                    $response[$key]['is_already_job_applied'] =
                        $collection->job_applications
                            ->where('item_id', $collection->id)
                            ->where('user_id', $authUserId)
                            ->count() > 0;
                } else {
                    $response[$key]['is_already_job_applied'] = false;
                }
            }

            /*
             * Sort Featured First
             */
            $featuredRows = [];
            $normalRows   = [];
            foreach ($response as $value) {
                if (!isset($value['is_feature'])) {
                    $value['is_feature'] = false;
                }

                if ($value['is_feature']) {
                    $featuredRows[] = $value;
                } else {
                    $normalRows[] = $value;
                }
            }
            $response = array_merge($featuredRows, $normalRows);
            $totalCount = count($response);

            /*
             * Paginator Handling
             */
            if ($this->resource instanceof AbstractPaginator) {
                return [
                    ...$this->resource->toArray(),
                    'data'            => $response,
                    'total_item_count' => $totalCount,
                ];
            }

            return $response;

        } catch (Throwable $th) {
            throw $th;
        }
    }
}
