<?php

namespace Database\Seeders;

use App\Enums\ReservationStatus;
use App\Enums\ReviewStatus;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReviewSeeder extends Seeder
{
    /**
     * Curated, realistic French review comments (positive and mixed).
     *
     * @var list<string>
     */
    private array $positiveComments = [
        'Une adresse incontournable, le service était aux petits soins et les plats vraiment savoureux. On reviendra sans hésiter !',
        'Excellent rapport qualité-prix. La cuisine est généreuse et les produits frais se ressentent dans chaque assiette.',
        'Un vrai coup de cœur. Le cadre est chaleureux, l\'accueil parfait et le chef maîtrise son sujet.',
        'Soirée mémorable entre amis. Les portions sont copieuses et la carte des vins bien pensée. Je recommande vivement.',
        'Tout était délicieux, du début à la fin. Mention spéciale pour le dessert, un pur moment de gourmandise.',
        'Service rapide et souriant, plats bien présentés et pleins de saveurs. Parfait pour un dîner en amoureux.',
        'On a réservé pour un anniversaire, le personnel a été aux petits soins. Une expérience à renouveler.',
        'Cuisine raffinée et inventive, les associations de saveurs sont surprenantes et réussies. Bravo à toute l\'équipe.',
        'Très bon moment, ambiance conviviale et assiettes généreuses. Le menu du midi est une excellente affaire.',
        'Produits frais et de saison, cuisson parfaite. On sent le travail du chef. Je le conseille les yeux fermés.',
        'Accueil chaleureux, plats savoureux et addition raisonnable. Exactement ce qu\'on cherchait pour un déjeuner tranquille.',
        'Le meilleur repas que j\'ai fait depuis longtemps. Tout est maîtrisé, du dressage au goût. Un grand merci.',
        'Cadre agréable, terrasse ensoleillée et cuisine au top. Idéal pour un déjeuner en famille le week-end.',
        'Vraiment bluffé par la qualité. Les plats sont fins, le service attentionné et les prix justifiés.',
        'Une valeur sûre du quartier. On y mange toujours très bien et l\'équipe est adorable.',
    ];

    /**
     * Curated, realistic French review comments (mixed / lukewarm).
     *
     * @var list<string>
     */
    private array $mixedComments = [
        'Cuisine correcte mais le service était un peu long ce soir-là. Le cadre reste agréable malgré tout.',
        'Bons produits mais portions un peu justes pour le prix demandé. Dommage car le potentiel est là.',
        'Repas sympathique dans l\'ensemble, mais l\'accueil aurait pu être plus chaleureux. À retenter peut-être.',
        'Plats savoureux mais l\'attente entre les services était trop longue. L\'ambiance était bruyante ce jour-là.',
        'Pas mal sans plus. La carte manque un peu d\'originalité mais la qualité est au rendez-vous.',
    ];

    /**
     * Comments used for unverified (non-reservation) reviews.
     *
     * @var list<string|null>
     */
    private array $unverifiedComments = [
        'J\'y suis allé plusieurs fois et je n\'ai jamais été déçu. Une adresse fiable.',
        'Bonne table, je recommande pour un repas entre collègues.',
        'Cuisine honnête et service efficace. Rien à redire.',
        'Sympa mais un peu cher à mon goût. La qualité est néanmoins au rendez-vous.',
        'Très bonne expérience, je reviendrai avec plaisir.',
        null,
        'Cadre agréable et plats généreux. Une bonne adresse du coin.',
        null,
    ];

    /**
     * Seed verified and unverified reviews, then refresh restaurant aggregates.
     */
    public function run(): void
    {
        $faker = fake('fr_FR');

        $this->seedVerifiedReviews($faker);
        $this->seedUnverifiedReviews($faker);
        $this->refreshRestaurantRatings();
    }

    /**
     * Verified reviews: ~60% of completed reservations get an organizer review.
     */
    private function seedVerifiedReviews(Generator $faker): void
    {
        $reservations = Reservation::query()
            ->where('status', ReservationStatus::Completed->value)
            ->get(['id', 'restaurant_id', 'organizer_id', 'completed_at', 'reservation_date']);

        foreach ($reservations as $reservation) {
            if ($faker->boolean(60) === false) {
                continue;
            }

            $rating = $this->weightedRating($faker);
            $comment = $rating >= 4
                ? $faker->randomElement($this->positiveComments)
                : $faker->randomElement($this->mixedComments);

            $createdAt = ($reservation->completed_at ?? $reservation->reservation_date)
                ->copy()
                ->addHours($faker->numberBetween(1, 72));

            DB::transaction(function () use ($reservation, $rating, $comment, $createdAt): void {
                Review::firstOrCreate(
                    [
                        'user_id' => $reservation->organizer_id,
                        'reservation_id' => $reservation->id,
                    ],
                    [
                        'restaurant_id' => $reservation->restaurant_id,
                        'rating' => $rating,
                        'comment' => $comment,
                        'status' => ReviewStatus::Published->value,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ],
                );
            });
        }
    }

    /**
     * ~30 unverified reviews (no reservation) by random clients, at most one
     * per (restaurant, user) pair.
     */
    private function seedUnverifiedReviews(Generator $faker): void
    {
        $clients = User::role('client')->get(['id']);
        $restaurants = Restaurant::all(['id']);

        if ($clients->isEmpty() || $restaurants->isEmpty()) {
            return;
        }

        $target = 30;
        $created = 0;
        $attempts = 0;
        $maxAttempts = $target * 10;

        while ($created < $target && $attempts < $maxAttempts) {
            $attempts++;

            $client = $clients->random();
            $restaurant = $restaurants->random();

            $alreadyReviewed = Review::query()
                ->where('user_id', $client->id)
                ->where('restaurant_id', $restaurant->id)
                ->whereNull('reservation_id')
                ->exists();

            if ($alreadyReviewed) {
                continue;
            }

            $rating = $this->weightedRating($faker);
            $createdAt = now()->copy()->subDays($faker->numberBetween(1, 180));

            DB::transaction(function () use ($faker, $client, $restaurant, $rating, $createdAt): void {
                Review::create([
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $client->id,
                    'reservation_id' => null,
                    'rating' => $rating,
                    'comment' => $faker->randomElement($this->unverifiedComments),
                    'status' => ReviewStatus::Published->value,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            });

            $created++;
        }
    }

    /**
     * Recompute average_rating and reviews_count from published reviews.
     */
    private function refreshRestaurantRatings(): void
    {
        Restaurant::query()->each(function (Restaurant $restaurant): void {
            $aggregates = Review::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('status', ReviewStatus::Published->value)
                ->selectRaw('COUNT(*) as reviews_count, AVG(rating) as average_rating')
                ->first();

            $count = (int) ($aggregates->reviews_count ?? 0);
            $average = $count > 0 ? round((float) $aggregates->average_rating, 2) : null;

            $restaurant->forceFill([
                'reviews_count' => $count,
                'average_rating' => $average,
            ])->save();
        });
    }

    /**
     * Mostly 4-5 stars, occasionally 2-3.
     */
    private function weightedRating(Generator $faker): int
    {
        return $faker->randomElement([5, 5, 5, 5, 4, 4, 4, 4, 3, 2]);
    }
}
