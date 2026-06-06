<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\MenuItemOptionGroup;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Allergen name => id lookup, resolved once in run().
     *
     * @var array<string, int>
     */
    private array $allergenIds = [];

    /**
     * The four ordered menu categories present on every restaurant.
     *
     * @var array<string, string>
     */
    private array $categories = [
        'Entrees' => 'Pour commencer, à partager ou non.',
        'Plats' => 'Nos plats principaux, faits maison.',
        'Desserts' => 'La touche sucrée de fin de repas.',
        'Boissons' => 'Vins, softs et boissons chaudes.',
    ];

    /**
     * Shared drinks catalogue reused by every menu, keyed by category section.
     *
     * Each item: [name, description, price(cents), prep(min)|null, [allergens], stock|null]
     *
     * @var array<int, array{0:string,1:string,2:int,3:?int,4:list<string>,5:?int}>
     */
    private array $drinks = [
        ['Eau minérale (50cl)', 'Plate ou pétillante.', 350, 2, [], null],
        ['Coca-Cola (33cl)', 'Servi frais.', 400, 2, [], null],
        ['Limonade artisanale', 'Citron pressé maison.', 550, 3, [], null],
        ['Verre de vin rouge', 'Sélection du sommelier, 12cl.', 650, 2, ['Anhydride sulfureux et sulfites'], null],
        ['Verre de vin blanc', 'Sélection du sommelier, 12cl.', 650, 2, ['Anhydride sulfureux et sulfites'], null],
        ['Café expresso', 'Torréfaction italienne.', 250, 3, [], null],
        ['Thé / Infusion', 'Assortiment de thés bio.', 300, 4, [], null],
    ];

    /**
     * Seed coherent menus for every restaurant based on its cuisine types.
     */
    public function run(): void
    {
        $this->allergenIds = Allergen::pluck('id', 'name')->all();

        Restaurant::with('cuisineTypes')->get()->each(function (Restaurant $restaurant): void {
            DB::transaction(function () use ($restaurant): void {
                $this->seedRestaurantMenu($restaurant);
            });
        });
    }

    /**
     * Build the full four-category menu for a single restaurant.
     */
    private function seedRestaurantMenu(Restaurant $restaurant): void
    {
        $catalog = $this->catalogForRestaurant($restaurant);

        $position = 0;

        foreach ($this->categories as $categoryName => $categoryDescription) {
            $category = MenuCategory::create([
                'restaurant_id' => $restaurant->id,
                'name' => $categoryName,
                'description' => $categoryDescription,
                'position' => $position++,
                'is_active' => true,
            ]);

            $items = $catalog[$categoryName] ?? [];

            $itemPosition = 0;

            foreach ($items as $item) {
                $this->createMenuItem($restaurant, $category, $item, $itemPosition++);
            }
        }
    }

    /**
     * Persist one menu item with its allergens and optional option groups.
     *
     * @param  array{0:string,1:string,2:int,3:?int,4:list<string>,5:?int,6?:list<array{name:string,min:int,max:?int,required:bool,options:list<array{name:string,delta:int}>}>}  $item
     */
    private function createMenuItem(Restaurant $restaurant, MenuCategory $category, array $item, int $position): void
    {
        [$name, $description, $price, $prep, $allergens] = $item;
        $stock = $item[5] ?? null;

        $menuItem = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'is_available' => true,
            'position' => $position,
            'stock_quantity' => $stock,
            'preparation_minutes' => $prep,
        ]);

        $allergenIds = $this->resolveAllergenIds($allergens);

        if ($allergenIds !== []) {
            $menuItem->allergens()->attach($allergenIds);
        }

        foreach ($item[6] ?? [] as $groupPosition => $group) {
            $this->createOptionGroup($menuItem, $group, $groupPosition);
        }
    }

    /**
     * Persist an option group and its options for a menu item.
     *
     * @param  array{name:string,min:int,max:?int,required:bool,options:list<array{name:string,delta:int}>}  $group
     */
    private function createOptionGroup(MenuItem $menuItem, array $group, int $position): void
    {
        $optionGroup = MenuItemOptionGroup::create([
            'menu_item_id' => $menuItem->id,
            'name' => $group['name'],
            'min_select' => $group['min'],
            'max_select' => $group['max'],
            'is_required' => $group['required'],
            'position' => $position,
        ]);

        $optionPosition = 0;

        foreach ($group['options'] as $option) {
            MenuItemOption::create([
                'option_group_id' => $optionGroup->id,
                'name' => $option['name'],
                'price_delta' => $option['delta'],
                'stock_quantity' => null,
                'is_available' => true,
                'position' => $optionPosition++,
            ]);
        }
    }

    /**
     * Map allergen names to their ids, ignoring unknown names defensively.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function resolveAllergenIds(array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            if (isset($this->allergenIds[$name])) {
                $ids[] = $this->allergenIds[$name];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resolve the curated catalogue matching the restaurant's primary cuisine.
     *
     * Falls back to a quality French brasserie for any uncovered cuisine.
     *
     * @return array<string, list<array<int, mixed>>>
     */
    private function catalogForRestaurant(Restaurant $restaurant): array
    {
        $cuisineNames = $restaurant->cuisineTypes->pluck('name')->all();

        foreach ($cuisineNames as $cuisine) {
            $catalog = match ($cuisine) {
                'Italienne' => $this->italianCatalog(),
                'Japonaise' => $this->japaneseCatalog(),
                'Française' => $this->frenchCatalog(),
                'Américaine' => $this->americanCatalog(),
                'Libanaise' => $this->lebaneseCatalog(),
                'Indienne' => $this->indianCatalog(),
                'Thaïlandaise' => $this->thaiCatalog(),
                'Espagnole' => $this->spanishCatalog(),
                default => null,
            };

            if ($catalog !== null) {
                return $catalog;
            }
        }

        return $this->brasserieCatalog();
    }

    /**
     * Append the shared drinks section to a cuisine-specific catalogue.
     *
     * @param  array{Entrees:list<array<int,mixed>>,Plats:list<array<int,mixed>>,Desserts:list<array<int,mixed>>}  $catalog
     * @return array<string, list<array<int, mixed>>>
     */
    private function withDrinks(array $catalog): array
    {
        $catalog['Boissons'] = $this->drinks;

        return $catalog;
    }

    /**
     * @return array<string, list<array<int, mixed>>>
     */
    private function italianCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Bruschetta al pomodoro', 'Pain grillé, tomates fraîches, basilic, ail et huile d\'olive.', 850, 10, ['Gluten'], null],
                ['Burrata des Pouilles', 'Burrata crémeuse, tomates cerises confites, roquette et pesto.', 1290, 8, ['Lait', 'Fruits à coque'], 12],
                ['Antipasti misti', 'Assortiment de charcuteries, mozzarella et légumes grillés.', 1450, 12, ['Lait'], null],
                ['Arancini siciliens', 'Boulettes de risotto panées au cœur de mozzarella, sauce tomate.', 950, 15, ['Gluten', 'Lait', 'Œufs'], null],
                ['Vitello tonnato', 'Fines tranches de veau, sauce crémeuse au thon et câpres.', 1190, 12, ['Poissons', 'Œufs'], 8],
            ],
            'Plats' => [
                ['Pizza Margherita', 'Sauce tomate San Marzano, mozzarella fior di latte, basilic.', 1190, 14, ['Gluten', 'Lait'], null, [
                    ['name' => 'Taille', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Senior (30cm)', 'delta' => 0],
                        ['name' => 'Mega (40cm)', 'delta' => 300],
                    ]],
                    ['name' => 'Suppléments', 'min' => 0, 'max' => 3, 'required' => false, 'options' => [
                        ['name' => 'Extra mozzarella', 'delta' => 200],
                        ['name' => 'Jambon de Parme', 'delta' => 350],
                        ['name' => 'Champignons', 'delta' => 150],
                        ['name' => 'Œuf', 'delta' => 100],
                    ]],
                ]],
                ['Pizza Quattro Formaggi', 'Mozzarella, gorgonzola, parmesan et taleggio.', 1390, 14, ['Gluten', 'Lait'], null, [
                    ['name' => 'Taille', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Senior (30cm)', 'delta' => 0],
                        ['name' => 'Mega (40cm)', 'delta' => 300],
                    ]],
                ]],
                ['Spaghetti alla Carbonara', 'Guanciale, jaune d\'œuf, pecorino romano et poivre noir.', 1490, 16, ['Gluten', 'Œufs', 'Lait'], null, [
                    ['name' => 'Suppléments', 'min' => 0, 'max' => 2, 'required' => false, 'options' => [
                        ['name' => 'Extra guanciale', 'delta' => 250],
                        ['name' => 'Extra pecorino', 'delta' => 200],
                    ]],
                ]],
                ['Tagliatelle al ragù', 'Tagliatelles fraîches, ragù de bœuf mijoté 4h à la bolognaise.', 1590, 18, ['Gluten', 'Œufs', 'Céleri'], null],
                ['Risotto ai funghi porcini', 'Risotto carnaroli crémeux aux cèpes et parmesan.', 1690, 22, ['Lait'], 10],
                ['Osso buco alla milanese', 'Jarret de veau braisé, gremolata et risotto safrané.', 2290, 30, ['Lait', 'Céleri'], 6],
                ['Lasagne al forno', 'Lasagnes maison, béchamel et ragù de bœuf gratinées.', 1550, 20, ['Gluten', 'Œufs', 'Lait'], null],
            ],
            'Desserts' => [
                ['Tiramisù classico', 'Mascarpone, café, biscuits savoiardi et cacao.', 750, 5, ['Gluten', 'Œufs', 'Lait'], null],
                ['Panna cotta', 'Crème vanillée et coulis de fruits rouges.', 650, 5, ['Lait'], null],
                ['Cannoli siciliani', 'Cigares croustillants garnis de ricotta sucrée et pistaches.', 790, 8, ['Gluten', 'Lait', 'Fruits à coque'], 10],
                ['Affogato al caffè', 'Glace vanille noyée d\'un expresso brûlant.', 590, 4, ['Lait'], null],
            ],
        ]);
    }

    /**
     * @return array<string, list<array<int, mixed>>>
     */
    private function japaneseCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Edamame', 'Fèves de soja vapeur, fleur de sel.', 550, 6, ['Soja'], null],
                ['Gyoza poulet (6 pièces)', 'Raviolis japonais grillés, sauce ponzu.', 790, 12, ['Gluten', 'Soja', 'Graines de sésame'], null],
                ['Soupe miso', 'Bouillon de miso, tofu, algues wakamé et ciboule.', 490, 8, ['Soja', 'Poissons'], null],
                ['Salade de chou wakamé', 'Algues marinées au sésame.', 590, 5, ['Soja', 'Graines de sésame'], 15],
                ['Tataki de thon', 'Thon rouge mi-cuit, sauce soja et sésame torréfié.', 1290, 12, ['Poissons', 'Soja', 'Graines de sésame'], 10],
            ],
            'Plats' => [
                ['Ramen tonkotsu', 'Bouillon de porc crémeux, nouilles, chashu, œuf mollet et nori.', 1490, 18, ['Gluten', 'Œufs', 'Soja', 'Graines de sésame'], null, [
                    ['name' => 'Suppléments', 'min' => 0, 'max' => 3, 'required' => false, 'options' => [
                        ['name' => 'Œuf mollet en plus', 'delta' => 150],
                        ['name' => 'Chashu supplémentaire', 'delta' => 350],
                        ['name' => 'Maïs', 'delta' => 100],
                    ]],
                ]],
                ['California rolls (8 pièces)', 'Surimi, avocat, concombre et sésame.', 990, 15, ['Crustacés', 'Poissons', 'Graines de sésame', 'Soja'], null],
                ['Sushi saumon (6 pièces)', 'Nigiris de saumon frais sur riz vinaigré.', 1190, 15, ['Poissons', 'Soja'], null],
                ['Chirashi saumon', 'Bol de riz vinaigré et émincé de saumon.', 1590, 16, ['Poissons', 'Soja'], 12],
                ['Yakitori poulet (5 brochettes)', 'Brochettes grillées sauce teriyaki.', 1290, 16, ['Gluten', 'Soja', 'Graines de sésame'], null],
                ['Donburi bœuf', 'Bœuf émincé mijoté, oignons et riz japonais.', 1450, 18, ['Soja', 'Graines de sésame'], null],
            ],
            'Desserts' => [
                ['Mochi glacés (3 pièces)', 'Pâte de riz garnie de glace (vanille, matcha, mangue).', 690, 4, ['Lait', 'Soja'], null],
                ['Cheesecake matcha', 'Cheesecake au thé vert matcha.', 750, 5, ['Gluten', 'Œufs', 'Lait'], 10],
                ['Dorayaki', 'Pancakes fourrés à la pâte de haricot rouge.', 590, 8, ['Gluten', 'Œufs'], null],
            ],
        ]);
    }

    /**
     * @return array<string, list<array<int, mixed>>>
     */
    private function frenchCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Soupe à l\'oignon gratinée', 'Oignons confits, bouillon, croûtons et gruyère gratiné.', 890, 15, ['Gluten', 'Lait', 'Céleri'], null],
                ['Foie gras maison', 'Foie gras mi-cuit, chutney de figues et pain toasté.', 1690, 8, ['Gluten', 'Anhydride sulfureux et sulfites'], 8],
                ['Œufs mimosa', 'Œufs durs, mayonnaise maison et ciboulette.', 650, 10, ['Œufs', 'Moutarde'], null],
                ['Escargots de Bourgogne (6)', 'Beurre persillé et ail.', 1090, 12, ['Lait', 'Mollusques', 'Gluten'], 12],
                ['Velouté de potimarron', 'Crème de potimarron, châtaignes et huile de noisette.', 790, 12, ['Lait', 'Fruits à coque', 'Céleri'], null],
            ],
            'Plats' => [
                ['Entrecôte grillée (300g)', 'Entrecôte du Limousin, frites maison et sauce au poivre.', 2490, 18, ['Lait'], null, [
                    ['name' => 'Cuisson', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Bleu', 'delta' => 0],
                        ['name' => 'Saignant', 'delta' => 0],
                        ['name' => 'À point', 'delta' => 0],
                        ['name' => 'Bien cuit', 'delta' => 0],
                    ]],
                    ['name' => 'Sauce', 'min' => 0, 'max' => 1, 'required' => false, 'options' => [
                        ['name' => 'Au poivre', 'delta' => 0],
                        ['name' => 'Roquefort', 'delta' => 100],
                        ['name' => 'Béarnaise', 'delta' => 100],
                    ]],
                ]],
                ['Magret de canard', 'Magret rôti, sauce au miel et pommes grenailles.', 2190, 22, ['Anhydride sulfureux et sulfites'], 8, [
                    ['name' => 'Cuisson', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Saignant', 'delta' => 0],
                        ['name' => 'Rosé', 'delta' => 0],
                        ['name' => 'À point', 'delta' => 0],
                    ]],
                ]],
                ['Blanquette de veau', 'Veau mijoté à l\'ancienne, riz pilaf.', 1890, 30, ['Lait', 'Céleri', 'Gluten'], null],
                ['Confit de canard', 'Cuisse de canard confite et pommes sarladaises.', 1990, 20, [], null],
                ['Filet de bar', 'Bar rôti, beurre blanc et légumes de saison.', 2290, 20, ['Poissons', 'Lait'], 6],
                ['Risotto aux Saint-Jacques', 'Noix de Saint-Jacques poêlées sur risotto crémeux.', 2390, 22, ['Mollusques', 'Lait'], 6],
            ],
            'Desserts' => [
                ['Crème brûlée à la vanille', 'Crème onctueuse, caramel craquant à la cassonade.', 750, 5, ['Œufs', 'Lait'], null],
                ['Tarte Tatin', 'Pommes caramélisées, pâte feuilletée et crème fraîche.', 790, 8, ['Gluten', 'Lait', 'Œufs'], null],
                ['Mousse au chocolat', 'Chocolat noir 70%, texture aérienne.', 690, 5, ['Œufs', 'Lait', 'Soja'], null],
                ['Profiteroles', 'Choux garnis de glace vanille, sauce chocolat chaud.', 850, 8, ['Gluten', 'Œufs', 'Lait'], 10],
            ],
        ]);
    }

    /**
     * @return array<string, list<array<int, mixed>>>
     */
    private function americanCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Chicken wings (8)', 'Ailes de poulet marinées, sauce BBQ.', 990, 15, ['Gluten', 'Soja', 'Moutarde'], null],
                ['Onion rings', 'Rondelles d\'oignon panées, sauce ranch.', 690, 12, ['Gluten', 'Lait', 'Œufs'], null],
                ['Nachos supreme', 'Tortillas, cheddar fondu, guacamole et jalapeños.', 990, 12, ['Gluten', 'Lait'], null],
                ['Mac & cheese', 'Macaroni gratinés au cheddar.', 850, 14, ['Gluten', 'Lait'], 15],
                ['Caesar salad', 'Salade romaine, poulet grillé, croûtons et parmesan.', 1190, 12, ['Gluten', 'Lait', 'Œufs', 'Poissons', 'Moutarde'], null],
            ],
            'Plats' => [
                ['Cheeseburger maison', 'Steak haché 180g, cheddar, salade, tomate et frites.', 1490, 16, ['Gluten', 'Lait', 'Moutarde', 'Œufs'], null, [
                    ['name' => 'Cuisson', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Saignant', 'delta' => 0],
                        ['name' => 'À point', 'delta' => 0],
                        ['name' => 'Bien cuit', 'delta' => 0],
                    ]],
                    ['name' => 'Suppléments', 'min' => 0, 'max' => 3, 'required' => false, 'options' => [
                        ['name' => 'Bacon', 'delta' => 150],
                        ['name' => 'Œuf', 'delta' => 100],
                        ['name' => 'Extra fromage', 'delta' => 200],
                        ['name' => 'Oignons confits', 'delta' => 100],
                    ]],
                ]],
                ['Double bacon burger', 'Deux steaks, double cheddar, bacon et sauce burger.', 1790, 18, ['Gluten', 'Lait', 'Moutarde', 'Œufs'], null, [
                    ['name' => 'Cuisson', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'À point', 'delta' => 0],
                        ['name' => 'Bien cuit', 'delta' => 0],
                    ]],
                ]],
                ['Ribs BBQ', 'Travers de porc laqués, sauce barbecue, coleslaw.', 1990, 25, ['Gluten', 'Soja', 'Moutarde'], 8],
                ['Hot dog New York', 'Saucisse de bœuf, oignons, moutarde et ketchup.', 1090, 12, ['Gluten', 'Moutarde'], null],
                ['Fish & chips', 'Filet de cabillaud pané, frites et sauce tartare.', 1590, 18, ['Gluten', 'Poissons', 'Œufs', 'Moutarde'], null],
                ['Pulled pork sandwich', 'Effiloché de porc fumé, coleslaw et sauce BBQ.', 1390, 16, ['Gluten', 'Soja', 'Moutarde'], 10],
            ],
            'Desserts' => [
                ['New York cheesecake', 'Cheesecake crémeux, coulis de fruits rouges.', 750, 5, ['Gluten', 'Lait', 'Œufs'], null],
                ['Brownie & glace', 'Brownie chocolat-noix, glace vanille.', 750, 6, ['Gluten', 'Lait', 'Œufs', 'Fruits à coque'], null],
                ['Pancakes & sirop d\'érable', 'Trois pancakes moelleux, sirop d\'érable.', 690, 10, ['Gluten', 'Lait', 'Œufs'], null],
                ['Milkshake Oreo', 'Milkshake épais aux biscuits.', 650, 5, ['Gluten', 'Lait', 'Soja'], null],
            ],
        ]);
    }

    /**
     * @return array<string, list<array<int, mixed>>>
     */
    private function lebaneseCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Houmous', 'Purée de pois chiches, tahini, citron et huile d\'olive.', 690, 8, ['Graines de sésame'], null],
                ['Moutabal', 'Caviar d\'aubergine fumée au tahini.', 690, 10, ['Graines de sésame'], null],
                ['Falafel (6 pièces)', 'Boulettes de pois chiches frites, sauce tahini.', 790, 12, ['Gluten', 'Graines de sésame'], null],
                ['Taboulé libanais', 'Persil, menthe, boulgour, tomate et citron.', 750, 10, ['Gluten'], null],
                ['Fatayer aux épinards', 'Triangles feuilletés aux épinards et sumac.', 690, 12, ['Gluten'], 12],
            ],
            'Plats' => [
                ['Chich taouk', 'Brochettes de poulet mariné, ail et riz libanais.', 1590, 18, ['Lait'], null],
                ['Kafta grillée', 'Brochettes de bœuf haché aux épices et persil.', 1590, 18, [], null],
                ['Mixed grill libanais', 'Assortiment chich taouk, kafta et côtelettes d\'agneau.', 2290, 25, ['Lait'], 8],
                ['Moussaka libanaise', 'Aubergines, pois chiches et sauce tomate épicée.', 1390, 20, [], null],
                ['Chawarma poulet', 'Émincé de poulet mariné, crudités et sauce ail.', 1290, 15, ['Gluten', 'Lait'], null],
                ['Kebbé (4 pièces)', 'Croquettes de boulgour farcies à la viande hachée.', 1190, 16, ['Gluten', 'Fruits à coque'], null],
            ],
            'Desserts' => [
                ['Baklava (3 pièces)', 'Feuilles de filo, pistaches et sirop de fleur d\'oranger.', 690, 5, ['Gluten', 'Fruits à coque'], null],
                ['Mouhalabieh', 'Crème de lait à la fleur d\'oranger et pistaches.', 590, 5, ['Lait', 'Fruits à coque'], null],
                ['Knefe', 'Pâtisserie chaude au fromage et sirop sucré.', 750, 12, ['Gluten', 'Lait', 'Fruits à coque'], 10],
            ],
        ]);
    }

    /**
     * @return array<string, list<array<int, mixed>>>
     */
    private function indianCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Samosas (3 pièces)', 'Beignets croustillants pommes de terre et petits pois.', 690, 12, ['Gluten'], null],
                ['Pakoras de légumes', 'Beignets de légumes à la farine de pois chiche.', 650, 12, [], null],
                ['Onion bhaji', 'Beignets d\'oignons épicés.', 590, 10, [], null],
                ['Poulet tikka', 'Émincé de poulet mariné au yaourt et épices tandoori.', 890, 15, ['Lait'], null],
                ['Soupe dal', 'Velouté de lentilles corail au cumin.', 590, 10, ['Céleri'], 15],
            ],
            'Plats' => [
                ['Poulet tikka masala', 'Poulet mariné, sauce tomate crémeuse aux épices.', 1490, 20, ['Lait', 'Fruits à coque'], null, [
                    ['name' => 'Niveau d\'épices', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Doux', 'delta' => 0],
                        ['name' => 'Moyen', 'delta' => 0],
                        ['name' => 'Fort', 'delta' => 0],
                    ]],
                ]],
                ['Butter chicken', 'Poulet mijoté dans une sauce beurre, tomate et fenugrec.', 1490, 20, ['Lait', 'Fruits à coque'], null],
                ['Agneau rogan josh', 'Agneau braisé, sauce épicée du Cachemire.', 1690, 25, ['Lait'], 8],
                ['Dal makhani', 'Lentilles noires mijotées à la crème et beurre.', 1190, 18, ['Lait'], null],
                ['Biryani de légumes', 'Riz basmati parfumé, légumes et épices.', 1290, 20, ['Lait', 'Fruits à coque'], null],
                ['Palak paneer', 'Fromage indien dans une crème d\'épinards.', 1290, 18, ['Lait'], null],
            ],
            'Desserts' => [
                ['Gulab jamun', 'Beignets de lait au sirop de cardamome.', 590, 8, ['Gluten', 'Lait'], null],
                ['Mangue lassi', 'Boisson yaourt à la mangue.', 490, 4, ['Lait'], null],
                ['Kheer', 'Riz au lait parfumé à la cardamome et amandes.', 590, 6, ['Lait', 'Fruits à coque'], 10],
            ],
        ]);
    }

    /**
     * @return array<string, list<array<int, mixed>>>
     */
    private function thaiCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Nems au poulet (4)', 'Rouleaux croustillants, sauce aigre-douce.', 690, 12, ['Gluten', 'Soja', 'Œufs'], null],
                ['Rouleaux de printemps', 'Crevettes, vermicelles et menthe fraîche.', 750, 12, ['Crustacés', 'Soja'], null],
                ['Soupe Tom Yum', 'Bouillon épicé citronnelle, crevettes et galanga.', 890, 12, ['Crustacés', 'Poissons'], null],
                ['Salade de papaye verte', 'Som tam, cacahuètes et citron vert.', 850, 10, ['Arachides', 'Poissons', 'Crustacés'], 12],
                ['Satay de poulet (4)', 'Brochettes marinées, sauce cacahuète.', 890, 15, ['Arachides', 'Soja'], null],
            ],
            'Plats' => [
                ['Pad thaï crevettes', 'Nouilles de riz sautées, crevettes, cacahuètes et tamarin.', 1490, 16, ['Crustacés', 'Arachides', 'Œufs', 'Soja', 'Poissons'], null, [
                    ['name' => 'Niveau d\'épices', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Doux', 'delta' => 0],
                        ['name' => 'Pimenté', 'delta' => 0],
                    ]],
                ]],
                ['Curry vert au poulet', 'Lait de coco, basilic thaï et aubergines.', 1390, 18, ['Poissons', 'Crustacés'], null],
                ['Curry massaman bœuf', 'Curry doux au lait de coco, cacahuètes et pommes de terre.', 1590, 22, ['Arachides', 'Poissons'], 8],
                ['Riz sauté thaï', 'Riz jasmin sauté, légumes et œuf.', 1190, 14, ['Œufs', 'Soja'], null],
                ['Poulet basilic thaï', 'Émincé de poulet sauté au basilic et piment.', 1390, 16, ['Soja', 'Poissons'], null],
                ['Bœuf sauté au gingembre', 'Bœuf émincé, gingembre frais et oignons.', 1490, 16, ['Soja', 'Graines de sésame'], null],
            ],
            'Desserts' => [
                ['Riz gluant à la mangue', 'Riz gluant au lait de coco et mangue fraîche.', 690, 8, [], null],
                ['Beignets de banane', 'Bananes frites, miel et sésame.', 590, 10, ['Gluten', 'Graines de sésame'], null],
                ['Perles de coco', 'Perles de tapioca au lait de coco.', 550, 6, [], 10],
            ],
        ]);
    }

    /**
     * @return array<string, list<array<int, mixed>>>
     */
    private function spanishCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Patatas bravas', 'Pommes de terre rissolées, sauce tomate épicée et aïoli.', 690, 12, ['Œufs', 'Moutarde'], null],
                ['Gambas al ajillo', 'Crevettes sautées à l\'ail et piment d\'Espelette.', 1190, 12, ['Crustacés'], null],
                ['Croquetas de jamón', 'Croquettes crémeuses au jambon ibérique.', 790, 12, ['Gluten', 'Lait', 'Œufs'], null],
                ['Tortilla española', 'Omelette aux pommes de terre et oignons.', 690, 15, ['Œufs'], null],
                ['Pan con tomate', 'Pain grillé frotté à la tomate et huile d\'olive.', 550, 8, ['Gluten'], null],
                ['Pimientos de Padrón', 'Petits poivrons grillés à la fleur de sel.', 690, 10, [], 15],
            ],
            'Plats' => [
                ['Paella valenciana', 'Riz safrané, poulet, lapin et haricots verts.', 1890, 30, ['Céleri'], 8],
                ['Paella de marisco', 'Riz aux fruits de mer, gambas, moules et calamars.', 2190, 30, ['Crustacés', 'Mollusques', 'Poissons'], 6],
                ['Chorizo a la sidra', 'Chorizo mijoté au cidre.', 1190, 15, ['Anhydride sulfureux et sulfites'], null],
                ['Calamares a la romana', 'Calamars frits, citron et aïoli.', 1390, 16, ['Gluten', 'Mollusques', 'Œufs'], null],
                ['Solomillo ibérico', 'Filet de porc ibérique grillé, légumes confits.', 1990, 22, [], null],
                ['Fideuà', 'Paella de vermicelles aux fruits de mer.', 1790, 28, ['Gluten', 'Crustacés', 'Mollusques', 'Poissons'], null],
            ],
            'Desserts' => [
                ['Crema catalana', 'Crème à la cannelle et caramel croquant.', 690, 5, ['Œufs', 'Lait'], null],
                ['Churros con chocolate', 'Beignets croustillants et chocolat chaud.', 690, 10, ['Gluten', 'Lait', 'Œufs'], null],
                ['Tarta de Santiago', 'Gâteau aux amandes traditionnel galicien.', 690, 6, ['Œufs', 'Fruits à coque'], 10],
            ],
        ]);
    }

    /**
     * Fallback quality French brasserie menu for uncovered cuisines.
     *
     * @return array<string, list<array<int, mixed>>>
     */
    private function brasserieCatalog(): array
    {
        return $this->withDrinks([
            'Entrees' => [
                ['Œuf parfait', 'Œuf basse température, crème de champignons et lard croustillant.', 890, 12, ['Œufs', 'Lait'], null],
                ['Burrata & tomates anciennes', 'Burrata crémeuse, tomates colorées et pesto.', 1190, 8, ['Lait', 'Fruits à coque'], 10],
                ['Tartare de saumon', 'Saumon frais, avocat, citron vert et toasts.', 1290, 12, ['Poissons', 'Gluten'], null],
                ['Velouté du moment', 'Crème de légumes de saison.', 750, 10, ['Lait', 'Céleri'], null],
                ['Salade de chèvre chaud', 'Toasts de chèvre, miel, noix et mesclun.', 990, 10, ['Gluten', 'Lait', 'Fruits à coque', 'Moutarde'], null],
            ],
            'Plats' => [
                ['Burger du chef', 'Steak haché, comté, oignons confits et frites maison.', 1590, 16, ['Gluten', 'Lait', 'Moutarde', 'Œufs'], null, [
                    ['name' => 'Cuisson', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Saignant', 'delta' => 0],
                        ['name' => 'À point', 'delta' => 0],
                        ['name' => 'Bien cuit', 'delta' => 0],
                    ]],
                    ['name' => 'Suppléments', 'min' => 0, 'max' => 3, 'required' => false, 'options' => [
                        ['name' => 'Bacon', 'delta' => 150],
                        ['name' => 'Œuf', 'delta' => 100],
                        ['name' => 'Extra fromage', 'delta' => 200],
                    ]],
                ]],
                ['Entrecôte & frites', 'Entrecôte grillée, frites maison et beurre maître d\'hôtel.', 2290, 18, ['Lait'], null, [
                    ['name' => 'Cuisson', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [
                        ['name' => 'Bleu', 'delta' => 0],
                        ['name' => 'Saignant', 'delta' => 0],
                        ['name' => 'À point', 'delta' => 0],
                        ['name' => 'Bien cuit', 'delta' => 0],
                    ]],
                ]],
                ['Suprême de volaille', 'Volaille fermière, jus corsé et purée maison.', 1790, 22, ['Lait'], null],
                ['Pavé de saumon', 'Saumon rôti, écrasé de pommes de terre et beurre citronné.', 1890, 18, ['Poissons', 'Lait'], 8],
                ['Risotto crémeux', 'Risotto aux légumes de saison et parmesan.', 1490, 22, ['Lait'], null],
                ['Fish & chips maison', 'Cabillaud pané, frites et sauce tartare.', 1590, 18, ['Gluten', 'Poissons', 'Œufs', 'Moutarde'], null],
            ],
            'Desserts' => [
                ['Fondant au chocolat', 'Cœur coulant, glace vanille.', 790, 12, ['Gluten', 'Œufs', 'Lait'], null],
                ['Crème brûlée', 'Vanille de Madagascar et caramel craquant.', 750, 5, ['Œufs', 'Lait'], null],
                ['Café gourmand', 'Expresso et trio de mignardises.', 850, 6, ['Gluten', 'Œufs', 'Lait', 'Fruits à coque'], null],
                ['Profiteroles', 'Choux, glace vanille et chocolat chaud.', 850, 8, ['Gluten', 'Œufs', 'Lait'], 10],
            ],
        ]);
    }
}
