<?php

namespace App\Http\Resources;

use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Disponibilités d'un service à une date : le service et la liste de ses créneaux
 * réservables. `date` est la date calendaire interrogée, à renvoyer telle quelle lors
 * de la réservation (elle porte la résolution des horaires, y compris après minuit).
 *
 * @property array{service: Service, slots: list<CarbonImmutable>} $resource
 */
class AvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Service $service */
        $service = $this->resource['service'];
        /** @var list<CarbonImmutable> $slots */
        $slots = $this->resource['slots'];

        return [
            'service' => ServiceResource::make($service),
            'date' => $request->input('date'),
            'slots' => array_map(fn (CarbonImmutable $slot): array => [
                'reserved_at' => $slot->format('Y-m-d H:i:s'),
                'time' => $slot->format('H:i'),
            ], $slots),
        ];
    }
}
