<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Restaurant;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ServiceController extends Controller
{
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        return ServiceResource::collection(
            $restaurant->services()
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
        );
    }

    public function store(StoreServiceRequest $request, Restaurant $restaurant): JsonResponse
    {
        $service = $restaurant->services()->create($request->validated());

        return ServiceResource::make($service->refresh())
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Service $service): ServiceResource
    {
        return ServiceResource::make($service->load('schedules'));
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        $service->update($request->validated());

        return ServiceResource::make($service->refresh());
    }

    public function destroy(Service $service): Response
    {
        $service->delete();

        return response()->noContent();
    }
}
