<?php

namespace App\Http\Controllers\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllergenResource;
use App\Models\Allergen;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AllergenController extends Controller
{
    /**
     * Full allergen catalogue, used to tag menu items.
     */
    public function index(): AnonymousResourceCollection
    {
        $allergens = Allergen::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return AllergenResource::collection($allergens);
    }
}
