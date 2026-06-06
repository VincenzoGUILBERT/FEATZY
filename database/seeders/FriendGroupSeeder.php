<?php

namespace Database\Seeders;

use App\Models\FriendGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FriendGroupSeeder extends Seeder
{
    /**
     * Curated French friend-group names.
     *
     * @var list<string>
     */
    private array $groupNames = [
        'Les copains',
        'Famille',
        'Collègues',
        'Brunch crew',
        'La bande',
        'Les voisins',
        'Apéro du vendredi',
        'Team resto',
        'Les anciens du lycée',
        'Cousinade',
        'Les gourmands',
        'Soirée filles',
    ];

    /**
     * ~12 clients each create 1-2 friend groups with 2-5 distinct members.
     */
    public function run(): void
    {
        $faker = fake('fr_FR');

        /** @var Collection<int, User> $clients */
        $clients = User::role('client')->get(['id']);

        if ($clients->count() < 3) {
            return;
        }

        $owners = $clients->count() <= 12
            ? $clients
            : $clients->random(12);

        foreach ($owners as $owner) {
            $groupCount = $faker->numberBetween(1, 2);
            $names = $faker->randomElements($this->groupNames, $groupCount);

            foreach ($names as $name) {
                $potentialMembers = $clients->where('id', '!=', $owner->id)->values();

                if ($potentialMembers->isEmpty()) {
                    continue;
                }

                $memberCount = min(
                    $faker->numberBetween(2, 5),
                    $potentialMembers->count(),
                );

                $members = $potentialMembers->random($memberCount);
                $members = $members instanceof User ? collect([$members]) : $members;

                DB::transaction(function () use ($owner, $name, $members): void {
                    $group = FriendGroup::firstOrCreate([
                        'owner_id' => $owner->id,
                        'name' => $name,
                    ]);

                    $group->members()->syncWithoutDetaching(
                        $members->pluck('id')->all(),
                    );
                });
            }
        }
    }
}
