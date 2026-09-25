<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('users.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_owner_can_access_their_business_member_list(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member', [
            'name' => 'Anggota Terdaftar',
            'email' => 'anggota@example.com',
        ]);

        $response = $this->actingAs($owner)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Pengguna');
        $response->assertSee($owner->name);
        $response->assertSee($member->email);
    }

    public function test_non_owner_member_receives_forbidden(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member');

        $this->actingAs($member)->get(route('users.index'))->assertForbidden();
        $this->actingAs($member)->getJson(route('users.detail', $owner->id))->assertForbidden();
    }

    public function test_user_without_business_does_not_receive_any_member_data(): void
    {
        $outsider = User::factory()->create(['email_verified_at' => now()]);

        $otherOwner = User::factory()->create(['email_verified_at' => now()]);
        $foreignBusiness = Business::factory()->create();
        $foreignBusiness->users()->attach($otherOwner->id, ['role' => 'owner']);
        $foreignMember = $this->attachMember($foreignBusiness, 'member', [
            'email' => 'rahasia-bisnis-lain@example.com',
        ]);

        $response = $this->actingAs($outsider)->get(route('users.index'));

        $response->assertForbidden();
        $response->assertDontSee($foreignMember->email);
    }

    public function test_members_of_other_businesses_are_not_listed(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member', ['name' => 'Anggota Kita']);

        $foreignOwner = User::factory()->create(['email_verified_at' => now()]);
        $foreignBusiness = Business::factory()->create();
        $foreignBusiness->users()->attach($foreignOwner->id, ['role' => 'owner']);
        $foreignMember = $this->attachMember($foreignBusiness, 'member', ['name' => 'Anggota Asing']);

        $response = $this->actingAs($owner)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee($member->name);
        $response->assertDontSee('Anggota Asing');
        $response->assertDontSee($foreignMember->email);
        $response->assertDontSee($foreignOwner->email);
    }

    public function test_detail_of_member_from_another_business_returns_404(): void
    {
        [$owner] = $this->makeOwnerWithBusiness();

        $foreignBusiness = Business::factory()->create();
        $foreignMember = $this->attachMember($foreignBusiness, 'member');

        $this->actingAs($owner)->getJson(route('users.detail', $foreignMember->id))
            ->assertNotFound();
    }

    public function test_switching_business_changes_the_member_dataset(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $owner->businesses()->attach($businessA->id, ['role' => 'owner']);
        $owner->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->attachMember($businessA, 'member', ['name' => 'Anggota Bisnis A']);
        $this->attachMember($businessB, 'member', ['name' => 'Anggota Bisnis B']);

        $responseA = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('users.index'));
        $responseA->assertSee('Anggota Bisnis A');
        $responseA->assertDontSee('Anggota Bisnis B');

        $responseB = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('users.index'));
        $responseB->assertSee('Anggota Bisnis B');
        $responseB->assertDontSee('Anggota Bisnis A');
    }

    public function test_same_user_role_follows_the_active_business(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $owner->businesses()->attach($businessA->id, ['role' => 'owner']);
        $owner->businesses()->attach($businessB->id, ['role' => 'owner']);

        $shared = User::factory()->create([
            'email_verified_at' => now(),
            'email' => 'dua-bisnis@example.com',
        ]);
        $businessA->users()->attach($shared->id, ['role' => 'member']);
        $businessB->users()->attach($shared->id, ['role' => 'supervisor']);

        $responseA = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('users.index'));
        $responseA->assertOk();
        $rowA = $this->memberRow($responseA, $shared->email);
        $this->assertNotNull($rowA);
        $this->assertSame('Anggota', $rowA['role']);
        $this->assertSame('member', $rowA['role_raw']);

        $responseB = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('users.index'));
        $responseB->assertOk();
        $rowB = $this->memberRow($responseB, $shared->email);
        $this->assertNotNull($rowB);
        $this->assertSame('Supervisor', $rowB['role']);
        $this->assertSame('supervisor', $rowB['role_raw']);
        $this->assertSame('other', $rowB['role_category']);
    }

    public function test_summary_counts_owner_member_and_other_roles_separately(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->attachMember($business, 'member');
        $this->attachMember($business, 'supervisor', ['email' => 'supervisor@example.com']);
        $this->attachMember($business, 'cashier', ['email' => 'kasir-belum-terdefinisi@example.com']);

        $foreign = Business::factory()->create();
        $this->attachMember($foreign, 'member');

        $response = $this->actingAs($owner)->get(route('users.index'));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_members'] === 4
                && $summary['owner_count'] === 1
                && $summary['member_count'] === 1
                && $summary['other_role_count'] === 2;
        });

        // An unmapped role must never be counted as a member.
        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['member_count']);
    }

    public function test_summary_counts_email_verification_state(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->attachMember($business, 'member', ['email_verified_at' => now()]);
        $this->attachMember($business, 'member', ['email_verified_at' => null]);

        $response = $this->actingAs($owner)->get(route('users.index'));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['verified_count'] === 2
                && $summary['unverified_count'] === 1;
        });
    }

    public function test_search_matches_member_name_and_email(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->attachMember($business, 'member', ['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        $this->attachMember($business, 'member', ['name' => 'Siti Aminah', 'email' => 'siti@example.com']);

        $byName = $this->actingAs($owner)->get(route('users.index', ['q' => 'Budi']));
        $byName->assertOk();
        $byName->assertSee('budi@example.com');
        $byName->assertDontSee('siti@example.com');

        $byEmail = $this->actingAs($owner)->get(route('users.index', ['q' => 'siti@']));
        $byEmail->assertOk();
        $byEmail->assertSee('Siti Aminah');
        $byEmail->assertDontSee('budi@example.com');
    }

    public function test_filters_by_role_and_verification_status(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $verifiedMember = $this->attachMember($business, 'member', [
            'name' => 'Anggota Terverifikasi',
            'email' => 'member-verified@example.com',
            'email_verified_at' => now(),
        ]);
        $pendingMember = $this->attachMember($business, 'member', [
            'name' => 'Anggota Belum Verifikasi',
            'email' => 'member-pending@example.com',
            'email_verified_at' => null,
        ]);

        $onlyOwners = $this->actingAs($owner)->get(route('users.index', ['role' => 'owner']));
        $onlyOwners->assertOk();
        $onlyOwners->assertSee($owner->email);
        $onlyOwners->assertDontSee($verifiedMember->email);
        $onlyOwners->assertDontSee($pendingMember->email);

        $onlyMembers = $this->actingAs($owner)->get(route('users.index', ['role' => 'member']));
        $onlyMembers->assertOk();
        $onlyMembers->assertSee($verifiedMember->email);
        $onlyMembers->assertSee($pendingMember->email);
        // The owner's own email is rendered in the header menu, so assert on the dataset instead.
        $onlyMembers->assertViewHas('users', function ($users) use ($owner): bool {
            return ! collect($users->items())->pluck('email')->contains($owner->email);
        });

        $unverified = $this->actingAs($owner)->get(route('users.index', ['verification' => 'unverified']));
        $unverified->assertOk();
        $unverified->assertSee($pendingMember->email);
        $unverified->assertDontSee($verifiedMember->email);

        $verified = $this->actingAs($owner)->get(route('users.index', ['verification' => 'verified']));
        $verified->assertOk();
        $verified->assertSee($verifiedMember->email);
        $verified->assertDontSee($pendingMember->email);
    }

    public function test_pagination_is_twenty_five_items_and_preserves_query_string(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->attachMember($business, 'member', [
                'name' => sprintf('Anggota %02d', $i),
                'email' => sprintf('anggota-%02d@example.com', $i),
            ]);
        }

        $response = $this->actingAs($owner)->get(route('users.index', ['role' => 'member', 'page' => 1]));

        $response->assertOk();
        $response->assertSee('role=member', false);
        $response->assertViewHas('users', function ($users): bool {
            return $users->count() === 25 && $users->total() === 30;
        });
    }

    public function test_invalid_query_parameters_do_not_cause_500(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->attachMember($business, 'member', ['email' => 'valid-anggota@example.com']);

        $response = $this->actingAs($owner)->get(route('users.index', [
            'q' => ['array-instead-of-string'],
            'role' => ['owner'],
            'verification' => str_repeat('x', 80),
            'page' => -2,
        ]));

        $response->assertOk();
        $response->assertSee('valid-anggota@example.com');
    }

    public function test_detail_response_does_not_expose_sensitive_attributes(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member', [
            'email' => 'detail-anggota@example.com',
        ]);
        $member->forceFill([
            'two_factor_secret' => encrypt('secret-value'),
            'two_factor_recovery_codes' => encrypt(json_encode(['kode-pemulihan'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $member->createToken('mobile-api', ['mobile']);

        $response = $this->actingAs($owner)->getJson(route('users.detail', $member->id));

        $response->assertOk()
            ->assertJsonPath('email', 'detail-anggota@example.com')
            ->assertJsonPath('role_raw', 'member')
            ->assertJsonPath('role', 'Anggota')
            ->assertJsonPath('is_verified', true);

        $payload = $response->json();

        foreach ([
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
            'businesses',
            'tokens',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $payload);
        }

        $this->assertStringNotContainsString('secret-value', $response->getContent());
    }

    public function test_detail_cannot_be_reached_through_id_manipulation(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $foreignBusiness = Business::factory()->create();
        $foreignMember = $this->attachMember($foreignBusiness, 'member');
        $nonMember = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($owner)->getJson(route('users.detail', $foreignMember->id))->assertNotFound();
        $this->actingAs($owner)->getJson(route('users.detail', $nonMember->id))->assertNotFound();
        $this->actingAs($owner)->getJson(route('users.detail', 999999))->assertNotFound();
    }

    public function test_users_menu_is_only_visible_to_owners(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member');

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('users.index'), false);

        $this->actingAs($member)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('users.index'), false);
    }

    public function test_membership_and_api_behaviour_are_unchanged(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member');

        $this->assertTrue(Gate::forUser($owner)->allows('update', $business));
        $this->assertFalse(Gate::forUser($member)->allows('update', $business));
        $this->assertTrue(Gate::forUser($member)->allows('view', $business));
        $this->assertSame('member', $member->businesses()->first()?->pivot?->role);

        $this->postJson('/api/auth/login', [])->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeOwnerWithBusiness(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$owner, $business];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function attachMember(Business $business, string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $business->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function memberRow(TestResponse $response, string $email): ?array
    {
        $users = $response->viewData('users');

        foreach ($users->items() as $row) {
            if ($row['email'] === $email) {
                return $row;
            }
        }

        return null;
    }
}
