<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Review\RecalculateRestaurantRatingAction;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReviewModerationController extends Controller
{
    /**
     * Moderation board of every review, filterable by status / restaurant.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $reviews = QueryBuilder::for(Review::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('restaurant_id'),
                AllowedFilter::exact('rating'),
            )
            ->allowedSorts('created_at', 'rating')
            ->defaultSort('-created_at')
            ->with('user')
            ->paginate()
            ->appends($request->query());

        return ReviewResource::collection($reviews);
    }

    /**
     * Publish a review, making it count towards the restaurant's rating.
     */
    public function publish(Review $review, RecalculateRestaurantRatingAction $recalculate): ReviewResource
    {
        $wasPublished = $review->status === ReviewStatus::Published;

        $review->update(['status' => ReviewStatus::Published->value]);

        // Skip the (locked) recompute when nothing changed — already published.
        if (! $wasPublished) {
            $recalculate->handle($review->restaurant()->withTrashed()->first());
        }

        return ReviewResource::make($review->load('user'));
    }

    /**
     * Hide a review. The aggregate is only re-synced when it was published
     * before (otherwise it never contributed to the rating).
     */
    public function hide(Review $review, RecalculateRestaurantRatingAction $recalculate): ReviewResource
    {
        $wasPublished = $review->status === ReviewStatus::Published;

        $review->update(['status' => ReviewStatus::Hidden->value]);

        if ($wasPublished) {
            $recalculate->handle($review->restaurant()->withTrashed()->first());
        }

        return ReviewResource::make($review->load('user'));
    }
}
