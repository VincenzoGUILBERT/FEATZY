<?php

namespace App\Http\Controllers\Availability;

use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\AvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Models\Restaurant;
use App\Support\Availability\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AvailabilityController extends Controller
{
    /**
     * Créneaux réservables d'un restaurant publié à une date, pour une taille de groupe
     * (public). Calculés à la volée : couverts simultanés (chevauchement) + pacing par créneau.
     */
    public function index(AvailabilityRequest $request, Restaurant $restaurant, AvailabilityService $availability): AnonymousResourceCollection
    {
        abort_unless($restaurant->status === RestaurantStatus::Published, 404);

        $date = CarbonImmutable::parse($request->validated('date'));
        $partySize = (int) $request->validated('party_size');

        $service = $request->filled('service_id')
            ? $restaurant->services()->active()->findOrFail((int) $request->validated('service_id'))
            : null;

        $availabilities = $availability->availability($restaurant, $date, $partySize, $service);

        return AvailabilityResource::collection($availabilities);
    }
}
