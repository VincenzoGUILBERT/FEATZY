<?php

namespace App\Http\Controllers\Discovery;

use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\IndexRestaurantsRequest;
use App\Http\Resources\MenuCategoryResource;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RestaurantDiscoveryController extends Controller
{
    /**
     * Public, filterable listing of published restaurants.
     */
    public function index(IndexRestaurantsRequest $request): AnonymousResourceCollection
    {
        $base = Restaurant::query()->published();

        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');
        $radius = $request->validated('radius');

        $sorts = ['name', 'average_rating', 'created_at'];
        $defaultSort = '-created_at';

        if ($latitude !== null && $longitude !== null) {
            $base->nearby((float) $latitude, (float) $longitude, $radius !== null ? (float) $radius : null);
            $sorts[] = 'distance';
            $defaultSort = 'distance';
        }

        if ($user = $request->user()) {
            $base->withExists(['favorites as is_favorited' => fn ($query) => $query->where('user_id', $user->id)]);
        }

        $restaurants = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::partial('city'),
                AllowedFilter::exact('price_level'),
                AllowedFilter::exact('accepts_preorders'),
                AllowedFilter::exact('cuisine', 'cuisineTypes.id'),
                AllowedFilter::callback('min_rating', fn ($query, $value) => $query->where('average_rating', '>=', $value)),
                AllowedFilter::callback('search', function ($query, $value): void {
                    if (! filled($value)) {
                        return;
                    }

                    $term = '%'.$value.'%';
                    $query->where(function ($query) use ($term): void {
                        $query->where('name', 'like', $term)
                            ->orWhere('city', 'like', $term)
                            ->orWhere('description', 'like', $term);
                    });
                }),
            )
            ->allowedSorts(...$sorts)
            ->defaultSort($defaultSort)
            ->with(['cuisineTypes', 'media'])
            ->paginate()
            ->appends($request->query());

        return RestaurantResource::collection($restaurants);
    }

    /**
     * Public detail of a single published restaurant.
     */
    public function show(Request $request, Restaurant $restaurant): RestaurantResource
    {
        abort_unless($restaurant->status === RestaurantStatus::Published, 404);

        $restaurant->load(['cuisineTypes', 'media']);

        if ($user = $request->user()) {
            $restaurant->loadExists(['favorites as is_favorited' => fn ($query) => $query->where('user_id', $user->id)]);
        }

        return RestaurantResource::make($restaurant);
    }

    /**
     * Public, active menu tree of a published restaurant.
     */
    public function menu(Restaurant $restaurant): AnonymousResourceCollection
    {
        abort_unless($restaurant->status === RestaurantStatus::Published, 404);

        $categories = $restaurant->menuCategories()
            ->where('is_active', true)
            ->orderBy('position')
            ->with([
                'menuItems' => fn ($query) => $query->where('is_available', true)->orderBy('position'),
                'menuItems.optionGroups' => fn ($query) => $query->orderBy('position'),
                'menuItems.optionGroups.options' => fn ($query) => $query->orderBy('position'),
                'menuItems.allergens',
                'menuItems.media',
            ])
            ->get();

        return MenuCategoryResource::collection($categories);
    }
}
