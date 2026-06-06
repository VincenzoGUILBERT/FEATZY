<?php

namespace Database\Seeders;

use App\Models\CuisineType;
use Illuminate\Database\Seeder;

class CuisineTypeSeeder extends Seeder
{
    /**
     * Seed the cuisine types referential.
     *
     * @var list<string>
     */
    private array $cuisines = [
        'Italienne',
        'Française',
        'Japonaise',
        'Chinoise',
        'Indienne',
        'Mexicaine',
        'Libanaise',
        'Thaïlandaise',
        'Américaine',
        'Végétarienne',
        'Coréenne',
        'Espagnole',
    ];

    /**
     * Seed the application's cuisine types.
     */
    public function run(): void
    {
        foreach ($this->cuisines as $name) {
            CuisineType::firstOrCreate(['name' => $name]);
        }
    }
}
