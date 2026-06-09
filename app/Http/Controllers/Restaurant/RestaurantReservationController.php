<?php

namespace App\Http\Controllers\Restaurant;

use App\Actions\Reservation\TransitionReservationStatusAction;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RestaurantReservationController extends Controller
{
    /**
     * Owner board of the restaurant's reservations, filterable by date/status.
     */
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        $reservations = QueryBuilder::for($restaurant->reservations())
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('service_id'),
                AllowedFilter::callback('date', fn ($query, $value) => $query->whereDate('reserved_at', $value)),
            )
            ->allowedSorts('reserved_at', 'created_at')
            ->defaultSort('-reserved_at')
            ->with('service')
            ->paginate()
            ->appends($request->query());

        return ReservationResource::collection($reservations);
    }

    public function seat(Reservation $reservation, TransitionReservationStatusAction $action): ReservationResource
    {
        return ReservationResource::make($action->handle($reservation, ReservationStatus::Seated));
    }

    public function complete(Reservation $reservation, TransitionReservationStatusAction $action): ReservationResource
    {
        return ReservationResource::make($action->handle($reservation, ReservationStatus::Completed));
    }

    public function noShow(Reservation $reservation, TransitionReservationStatusAction $action): ReservationResource
    {
        return ReservationResource::make($action->handle($reservation, ReservationStatus::NoShow));
    }
}
