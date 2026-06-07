<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\RecalculateRestaurantRatingAction;
use App\Enums\RestaurantStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReviewController extends Controller
{
    /**
     * Public, cursor-paginated list of a published restaurant's published reviews.
     */
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        abort_unless($restaurant->status === RestaurantStatus::Published, HttpResponse::HTTP_NOT_FOUND);

        $reviews = $restaurant->reviews()
            ->where('status', ReviewStatus::Published->value)
            ->with('user')
            ->latest('id')
            ->cursorPaginate();

        return ReviewResource::collection($reviews);
    }

    /**
     * Submit a review for a completed reservation. It starts pending moderation,
     * so the published aggregate is untouched until it is published (Session 13).
     */
    public function store(StoreReviewRequest $request, Restaurant $restaurant): JsonResponse
    {
        $review = $restaurant->reviews()->create([
            'user_id' => $request->user()->id,
            'reservation_id' => $request->integer('reservation_id'),
            'rating' => $request->integer('rating'),
            'comment' => $request->input('comment'),
            'status' => ReviewStatus::Pending->value,
        ]);

        return ReviewResource::make($review->load('user'))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    /**
     * Edit one's own review. A published edit re-syncs the restaurant aggregate
     * (the rating may have changed); a pending one leaves it untouched.
     */
    public function update(UpdateReviewRequest $request, Review $review, RecalculateRestaurantRatingAction $recalculate): ReviewResource
    {
        $review->update($request->validated());

        if ($review->status === ReviewStatus::Published) {
            // withTrashed: the review's restaurant may be soft-deleted; its
            // aggregate must still stay accurate (and never resolve to null here).
            $recalculate->handle($review->restaurant()->withTrashed()->first());
        }

        return ReviewResource::make($review);
    }

    /**
     * Delete one's own review. Removing a published review re-syncs the aggregate.
     */
    public function destroy(Review $review, RecalculateRestaurantRatingAction $recalculate): Response
    {
        $wasPublished = $review->status === ReviewStatus::Published;
        $restaurant = $review->restaurant()->withTrashed()->first();

        $review->delete();

        if ($wasPublished) {
            $recalculate->handle($restaurant);
        }

        return response()->noContent();
    }
}
