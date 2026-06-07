<?php

namespace App\Http\Controllers\Availability;

use App\Actions\Availability\GenerateServiceAvailabilitiesAction;
use App\Data\Availability\GenerateAvailabilitiesData;
use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\GenerateAvailabilitiesRequest;
use App\Http\Resources\ServiceAvailabilityResource;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AvailabilityController extends Controller
{
    /**
     * Generate (owner-only) the restaurant's bookable slots from its schedules
     * and exceptions over the requested range.
     */
    public function generate(GenerateAvailabilitiesRequest $request, Restaurant $restaurant, GenerateServiceAvailabilitiesAction $action): JsonResponse
    {
        $result = $action->handle($restaurant, GenerateAvailabilitiesData::from($request->validated()));

        return response()->json(['data' => $result->toArray()]);
    }

    /**
     * List a published restaurant's upcoming bookable slots (public).
     */
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        abort_unless($restaurant->status === RestaurantStatus::Published, 404);

        $availabilities = QueryBuilder::for($restaurant->serviceAvailabilities())
            ->where('date', '>=', CarbonImmutable::today()->toDateString())
            ->allowedFilters(
                AllowedFilter::exact('date'),
                AllowedFilter::exact('service_type'),
            )
            ->defaultSort('date', 'service_type')
            ->allowedSorts('date', 'service_type')
            ->paginate()
            ->appends($request->query());

        return ServiceAvailabilityResource::collection($availabilities);
    }
}
