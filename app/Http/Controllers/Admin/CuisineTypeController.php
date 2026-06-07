<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Catalog\CatalogEntryInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCuisineTypeRequest;
use App\Http\Requests\Admin\UpdateCuisineTypeRequest;
use App\Http\Resources\CuisineTypeResource;
use App\Models\CuisineType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CuisineTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $cuisineTypes = CuisineType::query()
            ->withCount('restaurants')
            ->orderBy('name')
            ->paginate();

        return CuisineTypeResource::collection($cuisineTypes);
    }

    public function store(StoreCuisineTypeRequest $request): JsonResponse
    {
        $cuisineType = CuisineType::create($request->validated());

        // Reflect DB-side defaults (is_active) not present on the fresh instance.
        return CuisineTypeResource::make($cuisineType->refresh())
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function update(UpdateCuisineTypeRequest $request, CuisineType $cuisineType): CuisineTypeResource
    {
        $cuisineType->update($request->validated());

        return CuisineTypeResource::make($cuisineType);
    }

    /**
     * Hard-delete a cuisine type, refused while restaurants still reference it
     * (the pivot would otherwise silently cascade-detach them).
     */
    public function destroy(CuisineType $cuisineType): Response
    {
        if ($cuisineType->restaurants()->exists()) {
            throw CatalogEntryInUseException::cuisineType();
        }

        $cuisineType->delete();

        return response()->noContent();
    }
}
