<?php

use App\Models\FriendGroup;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('syncs members onto a group', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();
    $members = User::factory()->count(2)->create();

    $this->putJson("/api/friend-groups/{$group->id}/members", ['members' => $members->pluck('id')->all()])
        ->assertOk()
        ->assertJsonPath('data.members_count', 2)
        ->assertJsonCount(2, 'data.members');
});

it('replaces the membership on sync', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $group->members()->attach($first->id);

    $this->putJson("/api/friend-groups/{$group->id}/members", ['members' => [$second->id]])
        ->assertOk()
        ->assertJsonPath('data.members_count', 1)
        ->assertJsonPath('data.members.0.id', $second->id);

    $this->assertDatabaseMissing('friend_group_user', [
        'friend_group_id' => $group->id,
        'user_id' => $first->id,
    ]);
});

it('clears members with an empty array', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();
    $group->members()->attach(User::factory()->create()->id);

    $this->putJson("/api/friend-groups/{$group->id}/members", ['members' => []])
        ->assertOk()
        ->assertJsonPath('data.members_count', 0);
});

it('rejects non-existent users', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();

    $this->putJson("/api/friend-groups/{$group->id}/members", ['members' => [999999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('members.0');
});

it('requires the members key to be present', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();

    $this->putJson("/api/friend-groups/{$group->id}/members", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('members');
});

it('removes a single member', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();
    $keep = User::factory()->create();
    $remove = User::factory()->create();
    $group->members()->attach([$keep->id, $remove->id]);

    $this->deleteJson("/api/friend-groups/{$group->id}/members/{$remove->id}")->assertNoContent();

    $this->assertDatabaseHas('friend_group_user', ['friend_group_id' => $group->id, 'user_id' => $keep->id]);
    $this->assertDatabaseMissing('friend_group_user', ['friend_group_id' => $group->id, 'user_id' => $remove->id]);
});

it('forbids managing members of another owner group', function () {
    actingAsClient();
    $group = FriendGroup::factory()->create();
    $member = User::factory()->create();

    $this->putJson("/api/friend-groups/{$group->id}/members", ['members' => [$member->id]])
        ->assertForbidden();
});

it('forbids removing a member from another owner group', function () {
    actingAsClient();
    $group = FriendGroup::factory()->create();
    $member = User::factory()->create();
    $group->members()->attach($member->id);

    $this->deleteJson("/api/friend-groups/{$group->id}/members/{$member->id}")->assertForbidden();
});

it('exposes only minimal member identity', function () {
    $user = actingAsClient();
    $group = FriendGroup::factory()->for($user, 'owner')->create();
    $member = User::factory()->create();
    $group->members()->attach($member->id);

    $response = $this->getJson("/api/friend-groups/{$group->id}")->assertOk();

    $response->assertJsonPath('data.members.0.id', $member->id)
        ->assertJsonPath('data.members.0.first_name', $member->first_name)
        ->assertJsonMissingPath('data.members.0.email')
        ->assertJsonMissingPath('data.members.0.phone');
});
