<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Catalog\CatalogEntryInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAllergenRequest;
use App\Http\Requests\Admin\UpdateAllergenRequest;
use App\Http\Resources\AllergenResource;
use App\Models\Allergen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AllergenController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $allergens = Allergen::query()
            ->withCount('menuItems')
            ->orderBy('position')
            ->orderBy('name')
            ->paginate();

        return AllergenResource::collection($allergens);
    }

    public function store(StoreAllergenRequest $request): JsonResponse
    {
        $allergen = Allergen::create($request->validated());

        // Reflect DB-side defaults (position) not present on the fresh instance.
        return AllergenResource::make($allergen->refresh())
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function update(UpdateAllergenRequest $request, Allergen $allergen): AllergenResource
    {
        $allergen->update($request->validated());

        return AllergenResource::make($allergen);
    }

    /**
     * Hard-delete an allergen, refused while menu items still reference it
     * (the pivot would otherwise silently cascade-detach them).
     */
    public function destroy(Allergen $allergen): Response
    {
        if ($allergen->menuItems()->exists()) {
            throw CatalogEntryInUseException::allergen();
        }

        $allergen->delete();

        return response()->noContent();
    }
}
