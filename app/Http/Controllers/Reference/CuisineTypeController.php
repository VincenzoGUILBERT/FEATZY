<?php

namespace App\Http\Controllers\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\CuisineTypeResource;
use App\Models\CuisineType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CuisineTypeController extends Controller
{
    /**
     * Active cuisine types, used to populate restaurant settings selectors.
     */
    public function index(): AnonymousResourceCollection
    {
        $cuisineTypes = CuisineType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return CuisineTypeResource::collection($cuisineTypes);
    }
}
