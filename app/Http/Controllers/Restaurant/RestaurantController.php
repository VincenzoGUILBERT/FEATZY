<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreRestaurantRequest;
use App\Http\Requests\Restaurant\UpdateRestaurantRequest;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RestaurantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $restaurants = $request->user()->restaurants()
            ->with(['cuisineTypes', 'media'])
            ->latest()
            ->paginate();

        return RestaurantResource::collection($restaurants);
    }

    public function store(StoreRestaurantRequest $request): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->create(
            $request->safe()->except('cuisine_type_ids'),
        );

        if ($request->has('cuisine_type_ids')) {
            $restaurant->cuisineTypes()->sync($request->validated('cuisine_type_ids'));
        }

        $restaurant->refresh()->load(['cuisineTypes', 'media']);

        return RestaurantResource::make($restaurant)
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Restaurant $restaurant): RestaurantResource
    {
        return RestaurantResource::make($restaurant->load(['cuisineTypes', 'media']));
    }

    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): RestaurantResource
    {
        $restaurant->update($request->safe()->except('cuisine_type_ids'));

        if ($request->has('cuisine_type_ids')) {
            $restaurant->cuisineTypes()->sync($request->validated('cuisine_type_ids'));
        }

        return RestaurantResource::make($restaurant->load(['cuisineTypes', 'media']));
    }

    public function destroy(Restaurant $restaurant): Response
    {
        $restaurant->delete();

        return response()->noContent();
    }

    public function publish(Restaurant $restaurant): RestaurantResource
    {
        $restaurant->update(['status' => RestaurantStatus::Published]);

        return RestaurantResource::make($restaurant->load(['cuisineTypes', 'media']));
    }
}
