<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreScheduleExceptionRequest;
use App\Http\Requests\Schedule\UpdateScheduleExceptionRequest;
use App\Http\Resources\ScheduleExceptionResource;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ScheduleExceptionController extends Controller
{
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        return ScheduleExceptionResource::collection(
            $restaurant->scheduleExceptions()
                ->orderBy('date')
                ->get(),
        );
    }

    public function store(StoreScheduleExceptionRequest $request, Restaurant $restaurant): JsonResponse
    {
        $exception = $restaurant->scheduleExceptions()->create($request->validated());

        return ScheduleExceptionResource::make($exception->refresh())
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function update(UpdateScheduleExceptionRequest $request, ScheduleException $scheduleException): ScheduleExceptionResource
    {
        $scheduleException->update($request->validated());

        return ScheduleExceptionResource::make($scheduleException);
    }

    public function destroy(ScheduleException $scheduleException): Response
    {
        $scheduleException->delete();

        return response()->noContent();
    }
}
