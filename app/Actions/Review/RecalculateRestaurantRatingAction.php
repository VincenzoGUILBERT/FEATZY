<?php

namespace App\Actions\Review;

use App\Enums\ReviewStatus;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;

class RecalculateRestaurantRatingAction
{
    /**
     * Recompute a restaurant's rating aggregates from its published reviews only.
     *
     * The whole recompute is a "set to the current truth" (not an increment), so
     * it is serialized per restaurant under a row lock: concurrent recalcs (an
     * author editing one review while another is moderated) can never overwrite
     * each other with a stale snapshot. Soft-deleted and pending/hidden reviews
     * are excluded; with no published review the average resets to null (the
     * CHECK allows NULL or 0..5). Rounded to two decimals to fit decimal(3,2).
     */
    public function handle(Restaurant $restaurant): void
    {
        DB::transaction(function () use ($restaurant): void {
            $restaurant->newQuery()->whereKey($restaurant->getKey())->lockForUpdate()->first();

            $stats = $restaurant->reviews()
                ->where('status', ReviewStatus::Published->value)
                ->selectRaw('COUNT(*) as reviews_count, AVG(rating) as average_rating')
                ->first();

            $count = (int) $stats->reviews_count;

            $restaurant->update([
                'reviews_count' => $count,
                'average_rating' => $count > 0 ? round((float) $stats->average_rating, 2) : null,
            ]);
        });
    }
}
