<?php

use App\Models\FriendGroup;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('lists only the user own friend groups', function () {
    $user = actingAsClient();
    FriendGroup::factory()->count(2)->for($user, 'owner')->create();
    FriendGroup::factory()->count(3)->create();

    $this->getJson('/api/friend-groups')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('creates a friend group', function () {
    $user = actingAsClient();

    $this->postJson('/api/friend-groups', ['name' => 'Best Friends'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Best Friends')
        ->assertJsonPath('data.owner_id', $user->id)
        ->assertJsonPath('data.members_count', 0);
});

it('rejects a duplicate name for the same owner', function () {
    $user = actingAsClient();
    FriendGroup::factory()->for($user, 'owner')->create(['name' => 'Squad']);

    $this->postJson('/api/friend-groups', ['name' => 'Squad'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('allows the same name for different owners', function () {
    actingAsClient();
    FriendGroup::factory()->create(['name' => 'Squad']);

    $this->postJson('/api/friend-groups', ['name' => 'Squad'])->assertCreated();
});

it('shows an owned friend group', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();

    $this->getJson("/api/friend-groups/{$group->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $group->id);
});

it('forbids viewing another owner group', function () {
    actingAsClient();
    $group = FriendGroup::factory()->create();

    $this->getJson("/api/friend-groups/{$group->id}")->assertForbidden();
});

it('updates the name', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create(['name' => 'Old']);

    $this->patchJson("/api/friend-groups/{$group->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New');
});

it('rejects updating to a name owned by the same user', function () {
    $user = actingAsClient();
    FriendGroup::factory()->for($user, 'owner')->create(['name' => 'Alpha']);
    $beta = FriendGroup::factory()->for($user, 'owner')->create(['name' => 'Beta']);

    $this->patchJson("/api/friend-groups/{$beta->id}", ['name' => 'Alpha'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('keeping the same name on update is allowed', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create(['name' => 'Stable']);

    $this->patchJson("/api/friend-groups/{$group->id}", ['name' => 'Stable'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Stable');
});

it('soft deletes an owned group', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();

    $this->deleteJson("/api/friend-groups/{$group->id}")->assertNoContent();

    $this->assertSoftDeleted($group);
});

it('forbids deleting another owner group', function () {
    actingAsClient();
    $group = FriendGroup::factory()->create();

    $this->deleteJson("/api/friend-groups/{$group->id}")->assertForbidden();
});

it('prevents reusing a soft-deleted group name', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create(['name' => 'Gone']);
    $group->delete();

    $this->postJson('/api/friend-groups', ['name' => 'Gone'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('returns pagination metadata', function () {
    $user = actingAsClient();
    FriendGroup::factory()->count(20)->for($user, 'owner')->create();

    $this->getJson('/api/friend-groups')
        ->assertOk()
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.current_page', 1);
});

it('requires authentication', function () {
    $this->getJson('/api/friend-groups')->assertUnauthorized();
});
