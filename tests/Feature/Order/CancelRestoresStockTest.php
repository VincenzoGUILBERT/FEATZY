<?php

use App\Actions\Order\PlaceOrderAction;
use App\Actions\Order\RestoreOrderStockAction;
use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('restores a placed order stock when the reservation is cancelled', function () {
    $organizer = actingAsClient();
    ['restaurant' => $restaurant, 'reservation' => $reservation, 'order' => $order, 'participant' => $participant] =
        preorderContext($organizer);
    $item = menuItemFor($restaurant, ['stock_quantity' => 10]);
    OrderItem::factory()->for($order)->for($participant, 'participant')->for($item, 'menuItem')->create([
        'name_snapshot' => $item->name, 'quantity' => 3, 'unit_price_snapshot' => 0, 'options_total_snapshot' => 0,
    ]);

    app(PlaceOrderAction::class)->handle($order);
    expect($item->refresh()->stock_quantity)->toBe(7);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")->assertOk();

    expect($item->refresh()->stock_quantity)->toBe(10);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Cancelled->value]);
    expect($order->refresh()->stock_restored_at)->not->toBeNull();
});

it('restores stock even when the menu item was soft-deleted after placing', function () {
    $organizer = actingAsClient();
    ['restaurant' => $restaurant, 'reservation' => $reservation, 'order' => $order, 'participant' => $participant] =
        preorderContext($organizer);
    $item = menuItemFor($restaurant, ['stock_quantity' => 10]);
    OrderItem::factory()->for($order)->for($participant, 'participant')->for($item, 'menuItem')->create([
        'name_snapshot' => $item->name, 'quantity' => 3, 'unit_price_snapshot' => 0, 'options_total_snapshot' => 0,
    ]);

    app(PlaceOrderAction::class)->handle($order);
    expect($item->refresh()->stock_quantity)->toBe(7);

    $item->delete(); // restaurateur removes the dish after the order is placed

    $this->postJson("/api/reservations/{$reservation->id}/cancel")->assertOk();

    expect($item->refresh()->stock_quantity)->toBe(10);
});

it('does not re-stock or override a served order on reservation cancel', function () {
    $organizer = actingAsClient();
    ['restaurant' => $restaurant, 'reservation' => $reservation, 'order' => $order, 'participant' => $participant] =
        preorderContext($organizer);
    $item = menuItemFor($restaurant, ['stock_quantity' => 7]); // already decremented at place
    OrderItem::factory()->for($order)->for($participant, 'participant')->for($item, 'menuItem')->create([
        'name_snapshot' => $item->name, 'quantity' => 3, 'unit_price_snapshot' => 0, 'options_total_snapshot' => 0,
    ]);
    $order->update(['status' => OrderStatus::Served, 'placed_at' => now(), 'stock_restored_at' => null]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")->assertOk();

    expect($item->refresh()->stock_quantity)->toBe(7); // a served order's stock is consumed, never returned
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Served->value]);
    expect($order->refresh()->stock_restored_at)->toBeNull();
});

it('voids a pending order on cancellation without touching stock', function () {
    $organizer = actingAsClient();
    ['restaurant' => $restaurant, 'reservation' => $reservation, 'order' => $order, 'participant' => $participant] =
        preorderContext($organizer);
    $item = menuItemFor($restaurant, ['stock_quantity' => 10]);
    OrderItem::factory()->for($order)->for($participant, 'participant')->for($item, 'menuItem')->create([
        'name_snapshot' => $item->name, 'quantity' => 3, 'unit_price_snapshot' => 0, 'options_total_snapshot' => 0,
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")->assertOk();

    expect($item->refresh()->stock_quantity)->toBe(10);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Cancelled->value]);
    expect($order->refresh()->stock_restored_at)->toBeNull();
});

it('restores stock at most once across repeated restore calls', function () {
    $organizer = actingAsClient();
    ['restaurant' => $restaurant, 'order' => $order, 'participant' => $participant] = preorderContext($organizer);
    $item = menuItemFor($restaurant, ['stock_quantity' => 10]);
    OrderItem::factory()->for($order)->for($participant, 'participant')->for($item, 'menuItem')->create([
        'name_snapshot' => $item->name, 'quantity' => 3, 'unit_price_snapshot' => 0, 'options_total_snapshot' => 0,
    ]);

    app(PlaceOrderAction::class)->handle($order);
    expect($item->refresh()->stock_quantity)->toBe(7);

    $restore = app(RestoreOrderStockAction::class);
    $restore->handle($order->refresh());
    $restore->handle($order->refresh());

    expect($item->refresh()->stock_quantity)->toBe(10);
});
