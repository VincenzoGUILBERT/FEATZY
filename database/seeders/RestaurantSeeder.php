<?php

namespace Database\Seeders;

use App\Enums\PriceLevel;
use App\Enums\RestaurantStatus;
use App\Models\CuisineType;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestaurantSeeder extends Seeder
{
    /**
     * Curated list of real / plausible French restaurants with accurate GPS coordinates.
     *
     * Cuisine names are matched (accent-insensitive) against the seeded `cuisine_types`.
     *
     * @var list<array{
     *     name: string,
     *     description: string,
     *     email: string,
     *     phone: string,
     *     street: string,
     *     postal_code: string,
     *     city: string,
     *     lat: float,
     *     lng: float,
     *     price_level: PriceLevel,
     *     accepts_preorders: bool,
     *     accepts_online_payment: bool,
     *     cancellation_deadline_hours: int,
     *     booking_horizon_days: int,
     *     cuisines: list<string>
     * }>
     */
    private array $restaurants = [
        [
            'name' => 'Le Comptoir du Relais',
            'description' => 'Bistrot parisien emblématique du carrefour de l\'Odéon, mené par Yves Camdeborde. Cuisine de marché généreuse, terrines maison et plats canailles servis dans une ambiance conviviale.',
            'email' => 'contact@comptoir-du-relais.fr',
            'phone' => '+33 1 44 27 07 97',
            'street' => '9 Carrefour de l\'Odéon',
            'postal_code' => '75006',
            'city' => 'Paris',
            'lat' => 48.8516700,
            'lng' => 2.3387100,
            'price_level' => PriceLevel::Expensive,
            'accepts_preorders' => true,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 24,
            'booking_horizon_days' => 60,
            'cuisines' => ['Française'],
        ],
        [
            'name' => 'Bouillon Chartier',
            'description' => 'Brasserie historique ouverte en 1896, classée aux Monuments historiques. Cuisine française traditionnelle à prix doux dans un décor Belle Époque, du pot-au-feu au baba au rhum.',
            'email' => 'reservation@bouillon-chartier.com',
            'phone' => '+33 1 47 70 86 29',
            'street' => '7 Rue du Faubourg Montmartre',
            'postal_code' => '75009',
            'city' => 'Paris',
            'lat' => 48.8721400,
            'lng' => 2.3434800,
            'price_level' => PriceLevel::Cheap,
            'accepts_preorders' => false,
            'accepts_online_payment' => false,
            'cancellation_deadline_hours' => 12,
            'booking_horizon_days' => 30,
            'cuisines' => ['Française'],
        ],
        [
            'name' => 'Big Mamma — East Mamma',
            'description' => 'Trattoria napolitaine festive du 11e arrondissement. Pizzas au feu de bois, pâtes fraîches et burrata crémeuse, produits importés directement d\'Italie chaque semaine.',
            'email' => 'hello@eastmamma.fr',
            'phone' => '+33 1 43 41 32 15',
            'street' => '133 Rue du Faubourg Saint-Antoine',
            'postal_code' => '75011',
            'city' => 'Paris',
            'lat' => 48.8513400,
            'lng' => 2.3796900,
            'price_level' => PriceLevel::Moderate,
            'accepts_preorders' => true,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 24,
            'booking_horizon_days' => 60,
            'cuisines' => ['Italienne'],
        ],
        [
            'name' => 'Kodawari Ramen Tsukiji',
            'description' => 'Ramen-ya immersif inspiré du marché de Tsukiji à Tokyo. Bouillons mijotés longuement, nouilles maison et ambiance japonaise soignée jusque dans le moindre détail.',
            'email' => 'contact@kodawari-ramen.fr',
            'phone' => '+33 1 76 21 17 80',
            'street' => '12 Rue de Richelieu',
            'postal_code' => '75001',
            'city' => 'Paris',
            'lat' => 48.8639300,
            'lng' => 2.3360500,
            'price_level' => PriceLevel::Moderate,
            'accepts_preorders' => true,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 12,
            'booking_horizon_days' => 30,
            'cuisines' => ['Japonaise'],
        ],
        [
            'name' => 'Le Cambodge',
            'description' => 'Institution du Canal Saint-Martin pour sa cuisine d\'Asie du Sud-Est. Le célèbre bo bun et les rouleaux de printemps attirent une file fidèle depuis des années.',
            'email' => 'bonjour@lecambodge.fr',
            'phone' => '+33 1 44 84 37 70',
            'street' => '10 Avenue Richerand',
            'postal_code' => '75010',
            'city' => 'Paris',
            'lat' => 48.8716900,
            'lng' => 2.3661200,
            'price_level' => PriceLevel::Cheap,
            'accepts_preorders' => true,
            'accepts_online_payment' => false,
            'cancellation_deadline_hours' => 12,
            'booking_horizon_days' => 30,
            'cuisines' => ['Thaïlandaise', 'Végétarienne'],
        ],
        [
            'name' => 'Le Petit Cler',
            'description' => 'Bistrot de quartier au pied de la rue Cler, à deux pas de la Tour Eiffel. Œuf cocotte, croque-monsieur et tartares servis en terrasse dans une rue piétonne animée.',
            'email' => 'contact@lepetitcler.fr',
            'phone' => '+33 1 45 50 17 50',
            'street' => '29 Rue Cler',
            'postal_code' => '75007',
            'city' => 'Paris',
            'lat' => 48.8567900,
            'lng' => 2.3047700,
            'price_level' => PriceLevel::Moderate,
            'accepts_preorders' => false,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 24,
            'booking_horizon_days' => 30,
            'cuisines' => ['Française'],
        ],
        [
            'name' => 'Gemini Trattoria',
            'description' => 'Trattoria contemporaine du Marais, pâtes artisanales et antipasti de saison. Carte de vins italiens choisis et tiramisu maison réputé dans le quartier.',
            'email' => 'ciao@gemini-trattoria.fr',
            'phone' => '+33 1 42 78 91 23',
            'street' => '4 Rue de Bretagne',
            'postal_code' => '75003',
            'city' => 'Paris',
            'lat' => 48.8624500,
            'lng' => 2.3625300,
            'price_level' => PriceLevel::Moderate,
            'accepts_preorders' => true,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 24,
            'booking_horizon_days' => 60,
            'cuisines' => ['Italienne', 'Végétarienne'],
        ],
        [
            'name' => 'Beirut Street',
            'description' => 'Mezzé libanais généreux et grillades au charbon dans le 17e. Houmous onctueux, falafels croustillants et taboulé frais, idéal à partager entre amis.',
            'email' => 'hello@beirut-street.fr',
            'phone' => '+33 1 47 63 28 44',
            'street' => '21 Rue des Dames',
            'postal_code' => '75017',
            'city' => 'Paris',
            'lat' => 48.8847200,
            'lng' => 2.3206800,
            'price_level' => PriceLevel::Moderate,
            'accepts_preorders' => true,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 24,
            'booking_horizon_days' => 60,
            'cuisines' => ['Libanaise', 'Végétarienne'],
        ],
        [
            'name' => 'Desi Road',
            'description' => 'Cuisine indienne moderne à Saint-Germain-des-Prés. Currys parfumés, naans cuits au tandoor et thalis colorés dans un décor chaleureux aux épices subtiles.',
            'email' => 'contact@desiroad.fr',
            'phone' => '+33 1 42 22 16 14',
            'street' => '14 Rue Dauphine',
            'postal_code' => '75006',
            'city' => 'Paris',
            'lat' => 48.8556200,
            'lng' => 2.3406400,
            'price_level' => PriceLevel::Moderate,
            'accepts_preorders' => false,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 48,
            'booking_horizon_days' => 90,
            'cuisines' => ['Indienne', 'Végétarienne'],
        ],
        [
            'name' => 'Daniel et Denise Saint-Jean',
            'description' => 'Bouchon lyonnais étoilé par les bouchons authentiques, tenu par Joseph Viola, Meilleur Ouvrier de France. Quenelle de brochet, pâté en croûte et tablier de sapeur dans la tradition.',
            'email' => 'reservation@daniel-et-denise.fr',
            'phone' => '+33 4 78 27 86 93',
            'street' => '36 Rue Tramassac',
            'postal_code' => '69005',
            'city' => 'Lyon',
            'lat' => 45.7615800,
            'lng' => 4.8270100,
            'price_level' => PriceLevel::Expensive,
            'accepts_preorders' => true,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 48,
            'booking_horizon_days' => 90,
            'cuisines' => ['Française'],
        ],
        [
            'name' => 'Maison Bocuse — L\'Est',
            'description' => 'Brasserie chic de Paul Bocuse installée dans l\'ancienne gare des Brotteaux à Lyon. Cuisine française généreuse, volaille de Bresse et îles flottantes signature.',
            'email' => 'contact@brasseries-bocuse.fr',
            'phone' => '+33 4 37 24 25 26',
            'street' => '14 Place Jules Ferry',
            'postal_code' => '69006',
            'city' => 'Lyon',
            'lat' => 45.7693500,
            'lng' => 4.8559700,
            'price_level' => PriceLevel::Expensive,
            'accepts_preorders' => false,
            'accepts_online_payment' => true,
            'cancellation_deadline_hours' => 48,
            'booking_horizon_days' => 90,
            'cuisines' => ['Française'],
        ],
        [
            'name' => 'Chez Fonfon',
            'description' => 'Institution marseillaise du vallon des Auffes face au port de pêche. Bouillabaisse réputée, poissons du jour grillés et vue imprenable sur la mer.',
            'email' => 'reservation@chez-fonfon.com',
            'phone' => '+33 4 91 52 14 38',
            'street' => '140 Vallon des Auffes',
            'postal_code' => '13007',
            'city' => 'Marseille',
            'lat' => 43.2849600,
            'lng' => 5.3540700,
            'price_level' => PriceLevel::Expensive,
            'accepts_preorders' => true,
            'accepts_online_payment' => false,
            'cancellation_deadline_hours' => 48,
            'booking_horizon_days' => 90,
            'cuisines' => ['Française', 'Espagnole'],
        ],
    ];

    /**
     * Seed the curated restaurants, distributing ownership across restaurateurs
     * and attaching coherent cuisine types.
     */
    public function run(): void
    {
        $owners = User::role('restaurateur')->orderBy('id')->get();

        if ($owners->isEmpty()) {
            throw new \RuntimeException('RestaurantSeeder requires at least one user with the "restaurateur" role.');
        }

        $cuisineIdsByNormalizedName = $this->cuisineLookup();

        $ownerCount = $owners->count();

        foreach ($this->restaurants as $index => $data) {
            // Round-robin distribution so some restaurateurs own more than one venue.
            $owner = $owners[$index % $ownerCount];

            DB::transaction(function () use ($data, $owner, $cuisineIdsByNormalizedName): void {
                $restaurant = Restaurant::create([
                    'owner_id' => $owner->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'street' => $data['street'],
                    'postal_code' => $data['postal_code'],
                    'city' => $data['city'],
                    'latitude' => $data['lat'],
                    'longitude' => $data['lng'],
                    'price_level' => $data['price_level'],
                    'accepts_preorders' => $data['accepts_preorders'],
                    'accepts_online_payment' => $data['accepts_online_payment'],
                    'cancellation_deadline_hours' => $data['cancellation_deadline_hours'],
                    'booking_horizon_days' => $data['booking_horizon_days'],
                    'status' => RestaurantStatus::Published,
                    'average_rating' => null,
                    'reviews_count' => 0,
                ]);

                $cuisineIds = $this->resolveCuisineIds($data['cuisines'], $cuisineIdsByNormalizedName);

                if ($cuisineIds !== []) {
                    $restaurant->cuisineTypes()->attach($cuisineIds);
                }
            });
        }
    }

    /**
     * Build a normalized (accent-insensitive, lowercased) name => id lookup
     * for the seeded cuisine types.
     *
     * @return array<string, int>
     */
    private function cuisineLookup(): array
    {
        return CuisineType::pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name): array => [$this->normalize($name) => $id])
            ->all();
    }

    /**
     * Resolve curated cuisine names to their seeded ids, ignoring unmatched names.
     *
     * @param  list<string>  $names
     * @param  array<string, int>  $lookup
     * @return list<int>
     */
    private function resolveCuisineIds(array $names, array $lookup): array
    {
        return Collection::make($names)
            ->map(fn (string $name): ?int => $lookup[$this->normalize($name)] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Normalize a cuisine name to an accent-insensitive, lowercased key.
     */
    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }
}
