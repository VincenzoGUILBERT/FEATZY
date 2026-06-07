<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreServiceScheduleRequest;
use App\Http\Requests\Schedule\UpdateServiceScheduleRequest;
use App\Http\Resources\ServiceScheduleResource;
use App\Models\Restaurant;
use App\Models\ServiceSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ServiceScheduleController extends Controller
{
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        return ServiceScheduleResource::collection(
            $restaurant->serviceSchedules()
                ->orderBy('day_of_week')
                ->orderBy('service_type')
                ->get(),
        );
    }

    public function store(StoreServiceScheduleRequest $request, Restaurant $restaurant): JsonResponse
    {
        $schedule = $restaurant->serviceSchedules()->create($request->validated());

        return ServiceScheduleResource::make($schedule->refresh())
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function update(UpdateServiceScheduleRequest $request, ServiceSchedule $serviceSchedule): ServiceScheduleResource
    {
        $serviceSchedule->update($request->validated());

        return ServiceScheduleResource::make($serviceSchedule);
    }

    public function destroy(ServiceSchedule $serviceSchedule): Response
    {
        $serviceSchedule->delete();

        return response()->noContent();
    }
}
