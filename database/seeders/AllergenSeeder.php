<?php

namespace Database\Seeders;

use App\Models\Allergen;
use Illuminate\Database\Seeder;

class AllergenSeeder extends Seeder
{
    /**
     * The 14 EU regulated allergens.
     *
     * @var list<string>
     */
    private array $allergens = [
        'Gluten',
        'Crustacés',
        'Œufs',
        'Poissons',
        'Arachides',
        'Soja',
        'Lait',
        'Fruits à coque',
        'Céleri',
        'Moutarde',
        'Graines de sésame',
        'Anhydride sulfureux et sulfites',
        'Lupin',
        'Mollusques',
    ];

    /**
     * Seed the application's allergens.
     */
    public function run(): void
    {
        $position = 0;

        foreach ($this->allergens as $name) {
            Allergen::firstOrCreate(
                ['name' => $name],
                ['position' => $position++],
            );
        }
    }
}
