<?php

namespace App\Http\Controllers\Reference;

use App\Enums\DietaryPreference;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DietaryPreferenceController extends Controller
{
    /**
     * Read-only catalog of selectable dietary preferences.
     */
    public function index(): JsonResponse
    {
        $preferences = array_map(
            fn (DietaryPreference $preference): array => [
                'value' => $preference->value,
                'label' => $preference->label(),
            ],
            DietaryPreference::cases(),
        );

        return response()->json(['data' => $preferences]);
    }
}
