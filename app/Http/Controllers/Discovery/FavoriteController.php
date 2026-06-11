<?php

namespace App\Http\Controllers\Discovery;

use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FavoriteController extends Controller
{
    /**
     * The authenticated user's favorited (still-published) restaurants.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $restaurants = $user->favoriteRestaurants()
            ->published()
            ->withExists(['favorites as is_favorited' => fn ($query) => $query->where('user_id', $user->id)])
            ->with([
                'cuisineTypes',
                'media',
                'services' => fn ($query) => $query->where('is_active', true)->with('schedules'),
            ])
            ->orderByPivot('created_at', 'desc')
            ->paginate()
            ->appends($request->query());

        return RestaurantResource::collection($restaurants);
    }

    /**
     * Idempotently favorite a published restaurant.
     */
    public function store(Request $request, Restaurant $restaurant): Response
    {
        abort_unless($restaurant->status === RestaurantStatus::Published, 404);

        $request->user()->favoriteRestaurants()->syncWithoutDetaching([$restaurant->id]);

        return response()->noContent();
    }

    /**
     * Idempotently remove a restaurant from the user's favorites.
     */
    public function destroy(Request $request, Restaurant $restaurant): Response
    {
        $request->user()->favoriteRestaurants()->detach($restaurant->id);

        return response()->noContent();
    }
}
