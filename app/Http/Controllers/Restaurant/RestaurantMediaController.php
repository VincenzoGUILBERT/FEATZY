<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\UploadRestaurantMediaRequest;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RestaurantMediaController extends Controller
{
    public function store(UploadRestaurantMediaRequest $request, Restaurant $restaurant, string $collection): RestaurantResource
    {
        $restaurant->addMediaFromRequest('file')->toMediaCollection($collection);

        return RestaurantResource::make($restaurant->load(['cuisineTypes', 'media']));
    }

    public function destroy(Restaurant $restaurant, Media $media): Response
    {
        abort_unless(
            $media->model_type === $restaurant->getMorphClass() && (int) $media->model_id === $restaurant->id,
            Response::HTTP_NOT_FOUND,
        );

        $media->delete();

        return response()->noContent();
    }
}
