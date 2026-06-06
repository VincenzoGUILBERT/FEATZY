<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Curated French restaurateur identities (first name, last name).
     *
     * @var list<array{0: string, 1: string}>
     */
    private array $restaurateurs = [
        ['Julien', 'Moreau'],
        ['Camille', 'Lefebvre'],
        ['Antoine', 'Garnier'],
        ['Sophie', 'Bernard'],
        ['Nicolas', 'Rousseau'],
        ['Émilie', 'Faure'],
        ['Mathieu', 'Chevalier'],
        ['Clara', 'Marchand'],
    ];

    /**
     * Curated French client identities (first name, last name).
     *
     * @var list<array{0: string, 1: string}>
     */
    private array $clients = [
        ['Lucas', 'Petit'],
        ['Emma', 'Durand'],
        ['Léa', 'Dubois'],
        ['Hugo', 'Lambert'],
        ['Chloé', 'Fontaine'],
        ['Théo', 'Robert'],
        ['Manon', 'Girard'],
        ['Nathan', 'Bonnet'],
        ['Inès', 'Roux'],
        ['Maxime', 'Vincent'],
        ['Sarah', 'Muller'],
        ['Enzo', 'Lefevre'],
        ['Jade', 'Mercier'],
        ['Louis', 'Blanc'],
        ['Alice', 'Guerin'],
        ['Gabriel', 'Boyer'],
        ['Zoé', 'Henry'],
        ['Raphaël', 'Roussel'],
        ['Lina', 'Nicolas'],
        ['Adam', 'Perrin'],
        ['Louise', 'Morel'],
        ['Arthur', 'Gauthier'],
        ['Romane', 'Masson'],
        ['Paul', 'Simon'],
        ['Anaïs', 'Michel'],
        ['Tom', 'Lemaire'],
        ['Juliette', 'Renard'],
        ['Noah', 'Aubert'],
        ['Margaux', 'Colin'],
        ['Quentin', 'Leroy'],
    ];

    /**
     * Seed the application's users: fixed dev accounts plus curated French
     * restaurateurs and clients. Roles are assigned through the spatie
     * permission package using the UserRole enum (guard 'web').
     */
    public function run(): void
    {
        $this->seedFixedAccounts();
        $this->seedRestaurateurs();
        $this->seedClients();
    }

    /**
     * Create the well-known fixed accounts used for development sign-in.
     */
    private function seedFixedAccounts(): void
    {
        $fixed = [
            ['Admin', 'Featzy', 'admin@featzy.fr', '+33611000001', UserRole::Admin],
            ['Olivier', 'Featzy', 'owner@featzy.fr', '+33611000002', UserRole::Restaurateur],
            ['Client', 'Featzy', 'client@featzy.fr', '+33611000003', UserRole::Client],
        ];

        foreach ($fixed as [$firstName, $lastName, $email, $phone, $role]) {
            $this->createUser($firstName, $lastName, $email, $phone, $role);
        }
    }

    /**
     * Create the curated restaurateur accounts.
     */
    private function seedRestaurateurs(): void
    {
        foreach ($this->restaurateurs as $index => [$firstName, $lastName]) {
            $this->createUser(
                $firstName,
                $lastName,
                $this->buildEmail($firstName, $lastName, 'pro', $index),
                $this->frenchMobile(620_000_000 + $index),
                UserRole::Restaurateur,
            );
        }
    }

    /**
     * Create the curated client accounts.
     */
    private function seedClients(): void
    {
        foreach ($this->clients as $index => [$firstName, $lastName]) {
            $this->createUser(
                $firstName,
                $lastName,
                $this->buildEmail($firstName, $lastName, 'featzy', $index),
                $this->frenchMobile(630_000_000 + $index),
                UserRole::Client,
            );
        }
    }

    /**
     * Persist a single user and assign its role inside a transaction.
     */
    private function createUser(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        UserRole $role,
    ): User {
        return DB::transaction(function () use ($firstName, $lastName, $email, $phone, $role): User {
            $user = User::factory()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            $user->assignRole($role->value);

            return $user;
        });
    }

    /**
     * Build a unique, deterministic, ASCII-safe email address.
     */
    private function buildEmail(string $firstName, string $lastName, string $domainKey, int $index): string
    {
        $local = $this->slug($firstName).'.'.$this->slug($lastName);

        $domain = $domainKey === 'pro' ? 'resto-featzy.fr' : 'featzy.fr';

        return sprintf('%s%d@%s', $local, $index + 1, $domain);
    }

    /**
     * Convert an accented French name into a lowercase ASCII slug.
     */
    private function slug(string $value): string
    {
        $ascii = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'À' => 'a', 'Â' => 'a',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e',
            'Î' => 'i', 'Ô' => 'o', 'Û' => 'u', 'Ç' => 'c',
        ]);

        return strtolower(preg_replace('/[^a-zA-Z]/', '', $ascii));
    }

    /**
     * Format a French mobile number in E.164 (+33 6/7 ... 9 digits).
     */
    private function frenchMobile(int $nationalNumber): string
    {
        return '+33'.$nationalNumber;
    }
}
