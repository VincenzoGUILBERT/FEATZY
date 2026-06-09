<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreServiceScheduleRequest;
use App\Http\Requests\Schedule\UpdateServiceScheduleRequest;
use App\Http\Resources\ServiceScheduleResource;
use App\Models\Service;
use App\Models\ServiceSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ServiceScheduleController extends Controller
{
    public function index(Service $service): AnonymousResourceCollection
    {
        return ServiceScheduleResource::collection(
            $service->schedules()
                ->orderBy('day_of_week')
                ->orderBy('opens_at')
                ->get(),
        );
    }

    public function store(StoreServiceScheduleRequest $request, Service $service): JsonResponse
    {
        $schedule = $service->schedules()->create($request->validated());

        return ServiceScheduleResource::make($schedule->refresh())
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function update(UpdateServiceScheduleRequest $request, ServiceSchedule $serviceSchedule): ServiceScheduleResource
    {
        $serviceSchedule->update($request->validated());

        return ServiceScheduleResource::make($serviceSchedule->refresh());
    }

    public function destroy(ServiceSchedule $serviceSchedule): Response
    {
        $serviceSchedule->delete();

        return response()->noContent();
    }
}
