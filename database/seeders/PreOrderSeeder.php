<?php

namespace Database\Seeders;

use App\Enums\InvitationStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PreOrderSeeder extends Seeder
{
    /**
     * Short notes a participant might leave on a pre-ordered dish.
     *
     * @var list<string>
     */
    private array $itemNotes = [
        'Sans oignon',
        'Bien cuit',
        'À point',
        'Saignant',
        'Cuisson à cœur',
        'Sauce à part',
        'Sans coriandre',
        'Peu épicé',
        'Sans gluten si possible',
        'Pas trop salé',
    ];

    /**
     * Notes a restaurant might attach to a whole pre-order.
     *
     * @var list<string>
     */
    private array $orderNotes = [
        'Table fêtant un anniversaire.',
        'Allergie aux fruits à coque signalée par le client.',
        'Prévoir une chaise haute.',
        'Client habitué, prévoir le digestif offert.',
        'Service rapide demandé (contrainte horaire).',
    ];

    /**
     * Build a pre-order for every reservation flagged as a pre-order.
     */
    public function run(): void
    {
        $reservations = Reservation::query()
            ->where('is_preorder', true)
            ->with(['participants', 'restaurant'])
            ->get();

        if ($reservations->isEmpty()) {
            return;
        }

        $menuCache = $this->cacheRestaurantMenus($reservations);

        foreach ($reservations as $reservation) {
            /** @var Collection<int, MenuItem> $menuItems */
            $menuItems = $menuCache->get($reservation->restaurant_id, collect());

            if ($menuItems->isEmpty()) {
                continue;
            }

            $this->createPreOrder($reservation, $menuItems);
        }
    }

    /**
     * Eager-load and group every restaurant's available menu items once,
     * with their option groups and options, to avoid per-reservation queries.
     *
     * @param  Collection<int, Reservation>  $reservations
     * @return Collection<int, Collection<int, MenuItem>>
     */
    private function cacheRestaurantMenus(Collection $reservations): Collection
    {
        $restaurantIds = $reservations->pluck('restaurant_id')->unique()->all();

        return MenuItem::query()
            ->whereIn('restaurant_id', $restaurantIds)
            ->where('is_available', true)
            ->with(['optionGroups.options' => function ($query): void {
                $query->where('is_available', true);
            }])
            ->get()
            ->groupBy('restaurant_id');
    }

    /**
     * Create the order, its items and options, then refresh the cached total.
     *
     * @param  Collection<int, MenuItem>  $menuItems
     */
    private function createPreOrder(Reservation $reservation, Collection $menuItems): void
    {
        $acceptedParticipants = $reservation->participants
            ->where('invitation_status', InvitationStatus::Accepted);

        if ($acceptedParticipants->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($reservation, $menuItems, $acceptedParticipants): void {
            $orderStatus = $this->orderStatusFor($reservation);
            $itemStatus = $this->itemStatusFor($orderStatus);

            $order = Order::create([
                'reservation_id' => $reservation->id,
                'restaurant_id' => $reservation->restaurant_id,
                'status' => $orderStatus,
                'placed_at' => $reservation->created_at ?? now(),
                'notes' => fake()->boolean(25) ? fake()->randomElement($this->orderNotes) : null,
            ]);

            $createdAny = false;

            foreach ($acceptedParticipants as $participant) {
                $createdAny = $this->createParticipantItems(
                    $order->id,
                    $participant,
                    $menuItems,
                    $itemStatus,
                ) || $createdAny;
            }

            if (! $createdAny) {
                $order->forceDelete();

                return;
            }

            $this->refreshOrderTotal($order);
        });
    }

    /**
     * Create 1 to 3 order items (with optional supplements) for one participant.
     *
     * @param  Collection<int, MenuItem>  $menuItems
     */
    private function createParticipantItems(
        int $orderId,
        ReservationParticipant $participant,
        Collection $menuItems,
        OrderItemStatus $itemStatus,
    ): bool {
        $itemCount = min($menuItems->count(), random_int(1, 3));
        $chosenItems = $menuItems->random($itemCount);

        if ($chosenItems instanceof MenuItem) {
            $chosenItems = collect([$chosenItems]);
        }

        $createdAny = false;

        foreach ($chosenItems as $menuItem) {
            $quantity = random_int(1, 2);

            if (! $this->reserveStock($menuItem, $quantity)) {
                continue;
            }

            $orderItem = OrderItem::create([
                'order_id' => $orderId,
                'reservation_participant_id' => $participant->id,
                'menu_item_id' => $menuItem->id,
                'name_snapshot' => $menuItem->name,
                'quantity' => $quantity,
                'unit_price_snapshot' => $menuItem->price,
                'options_total_snapshot' => 0,
                'status' => $itemStatus,
                'notes' => fake()->boolean(20) ? fake()->randomElement($this->itemNotes) : null,
            ]);

            $optionsTotal = $this->attachOptions($orderItem, $menuItem, $quantity);

            if ($optionsTotal !== 0) {
                $orderItem->update(['options_total_snapshot' => $optionsTotal]);
            }

            $createdAny = true;
        }

        return $createdAny;
    }

    /**
     * Sometimes pick supplements from the dish option groups and persist them.
     *
     * Returns the per-unit options total (sum of price_delta_snapshot * quantity)
     * to store on the order item.
     */
    private function attachOptions(OrderItem $orderItem, MenuItem $menuItem, int $itemQuantity): int
    {
        if ($menuItem->optionGroups->isEmpty() || fake()->boolean(50)) {
            return 0;
        }

        $optionsTotal = 0;

        foreach ($menuItem->optionGroups as $group) {
            $options = $group->options;

            if ($options->isEmpty()) {
                continue;
            }

            $maxSelect = $group->max_select ?? $options->count();
            $pickCount = max($group->min_select, 1);
            $pickCount = min($pickCount, $maxSelect, $options->count());

            $chosenOptions = $options->shuffle()->take($pickCount);

            foreach ($chosenOptions as $option) {
                $neededStock = $itemQuantity;

                if ($option->stock_quantity !== null && ! $this->reserveOptionStock($option, $neededStock)) {
                    continue;
                }

                $priceDelta = $option->price_delta;

                if ($menuItem->price + $optionsTotal + $priceDelta < 0) {
                    continue;
                }

                OrderItemOption::create([
                    'order_item_id' => $orderItem->id,
                    'menu_item_option_id' => $option->id,
                    'label_snapshot' => $option->name,
                    'price_delta_snapshot' => $priceDelta,
                    'quantity' => 1,
                ]);

                $optionsTotal += $priceDelta;
            }
        }

        return $optionsTotal;
    }

    /**
     * Decrement a menu item's stock when it is tracked, staying >= 0.
     *
     * Returns false when the dish is out of stock (item should be skipped).
     */
    private function reserveStock(MenuItem $menuItem, int &$quantity): bool
    {
        if ($menuItem->stock_quantity === null) {
            return true;
        }

        if ($menuItem->stock_quantity <= 0) {
            return false;
        }

        $quantity = min($quantity, $menuItem->stock_quantity);

        $menuItem->decrement('stock_quantity', $quantity);
        $menuItem->stock_quantity -= $quantity;

        return true;
    }

    /**
     * Decrement a tracked option's stock, staying >= 0.
     *
     * Returns false when the option lacks the requested stock.
     */
    private function reserveOptionStock(MenuItemOption $option, int $quantity): bool
    {
        if ($option->stock_quantity === null) {
            return true;
        }

        if ($option->stock_quantity < $quantity) {
            return false;
        }

        $option->decrement('stock_quantity', $quantity);
        $option->stock_quantity -= $quantity;

        return true;
    }

    /**
     * Reload the generated line_total of every item and cache the order total.
     */
    private function refreshOrderTotal(Order $order): void
    {
        $itemsTotal = (int) OrderItem::query()
            ->where('order_id', $order->id)
            ->sum('line_total');

        $order->update(['items_total' => $itemsTotal]);
    }

    /**
     * Map the reservation status to a coherent order status.
     */
    private function orderStatusFor(Reservation $reservation): OrderStatus
    {
        return match ($reservation->status) {
            ReservationStatus::Completed => OrderStatus::Served,
            ReservationStatus::Seated => OrderStatus::Preparing,
            default => fake()->randomElement([OrderStatus::Confirmed, OrderStatus::Preparing]),
        };
    }

    /**
     * Keep order item status aligned with its parent order status.
     */
    private function itemStatusFor(OrderStatus $orderStatus): OrderItemStatus
    {
        return match ($orderStatus) {
            OrderStatus::Served => OrderItemStatus::Served,
            OrderStatus::Preparing => OrderItemStatus::Preparing,
            OrderStatus::Confirmed => OrderItemStatus::Confirmed,
            OrderStatus::Cancelled => OrderItemStatus::Cancelled,
            OrderStatus::Pending => OrderItemStatus::Pending,
        };
    }
}
