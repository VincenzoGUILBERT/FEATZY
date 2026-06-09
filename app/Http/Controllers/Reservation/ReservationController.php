<?php

namespace App\Http\Controllers\Reservation;

use App\Actions\Reservation\CancelReservationAction;
use App\Actions\Reservation\CreateReservationAction;
use App\Data\Reservation\CreateReservationData;
use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\CancelReservationRequest;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReservationController extends Controller
{
    /**
     * List the reservations the authenticated user organizes.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $reservations = $request->user()->reservations()
            ->with(['restaurant', 'service'])
            ->orderByDesc('reserved_at')
            ->paginate();

        return ReservationResource::collection($reservations);
    }

    /**
     * Book a published restaurant's service slot for the authenticated client.
     */
    public function store(StoreReservationRequest $request, Restaurant $restaurant, CreateReservationAction $action): JsonResponse
    {
        abort_unless($restaurant->status === RestaurantStatus::Published, HttpResponse::HTTP_NOT_FOUND);

        $reservation = $action->handle(
            $restaurant,
            CreateReservationData::from($request->validated()),
            $request->user(),
        );

        return ReservationResource::make($reservation)
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Reservation $reservation): ReservationResource
    {
        return ReservationResource::make(
            $reservation->load(['restaurant', 'service', 'participants.user']),
        );
    }

    public function cancel(CancelReservationRequest $request, Reservation $reservation, CancelReservationAction $action): ReservationResource
    {
        $reservation = $action->handle(
            $reservation,
            $request->user(),
            $request->validated('cancellation_reason'),
        );

        return ReservationResource::make($reservation);
    }
}
