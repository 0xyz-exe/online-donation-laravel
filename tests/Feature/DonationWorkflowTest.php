<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => $admin])->save();
        return $user;
    }

    private function campaign(User $owner, array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'owner_id' => $owner->id, 'title' => 'Food fund', 'slug' => 'food-fund-'.Str::lower(Str::random(5)),
            'summary' => 'Food for neighbors', 'story' => 'Details about the campaign', 'goal_minor' => 1000000,
            'deadline' => now()->addDays(10), 'allows_anonymous' => true, 'status' => 'active',
        ], $overrides));
    }

    public function test_campaign_submission_is_queued_and_hidden_until_approved(): void
    {
        $owner = $this->user();
        $response = $this->actingAs($owner)->post(route('campaigns.store'), [
            'title' => 'Help our community', 'summary' => 'A short summary', 'story' => 'Full details',
            'goal' => '100.25', 'deadline' => now()->addDays(5)->toDateString(), 'allows_anonymous' => 1,
        ]);
        $campaign = Campaign::firstOrFail();
        $response->assertRedirect(route('campaigns.mine'));
        $this->assertSame(10025, $campaign->goal_minor);
        $this->assertSame('pending_review', $campaign->status);
        $this->assertDatabaseHas('campaign_events', ['campaign_id' => $campaign->id, 'to_status' => 'pending_review']);
        $this->get(route('campaigns.index'))->assertDontSee($campaign->title);
        $this->get(route('campaigns.show', $campaign))->assertNotFound();
    }

    public function test_campaign_image_upload_is_validated_and_stored_on_public_disk(): void
    {
        Storage::fake('public');
        $owner = $this->user();
        $this->actingAs($owner)->post(route('campaigns.store'), [
            'title' => 'Image campaign', 'summary' => 'Image summary', 'story' => 'Image story',
            'goal' => '500', 'deadline' => now()->addDays(5)->toDateString(), 'image' => UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg==', true)),
        ])->assertRedirect(route('campaigns.mine'));
        $campaign = Campaign::firstOrFail();
        $this->assertNotEmpty($campaign->image_path);
        Storage::disk('public')->assertExists($campaign->image_path);
        $this->actingAs($owner)->post(route('campaigns.store'), [
            'title' => 'Bad image campaign', 'summary' => 'Image summary', 'story' => 'Image story',
            'goal' => '500', 'deadline' => now()->addDays(5)->toDateString(), 'image' => UploadedFile::fake()->create('evil.svg', 2, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');
    }

    public function test_only_admin_can_moderate_and_campaign_transitions_are_audited(): void
    {
        $owner = $this->user(); $admin = $this->user(true);
        $campaign = $this->campaign($owner, ['status' => 'pending_review']);
        $this->actingAs($owner)->patch(route('admin.campaigns.update', $campaign), ['status' => 'active'])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.campaigns.update', $campaign), ['status' => 'active', 'note' => 'Reviewed'])->assertRedirect();
        $this->assertDatabaseHas('campaign_events', ['campaign_id' => $campaign->id, 'from_status' => 'pending_review', 'to_status' => 'active', 'note' => 'Reviewed']);
        $this->patch(route('admin.campaigns.update', $campaign), ['status' => 'active'])->assertUnprocessable();
    }

    public function test_pending_donations_are_not_counted_and_confirmed_amount_is_exact_and_idempotent(): void
    {
        $owner = $this->user(); $donor = $this->user(); $admin = $this->user(true); $campaign = $this->campaign($owner);
        $key = (string) Str::uuid();
        $data = ['amount' => '12.34', 'idempotency_key' => $key, 'public_name' => 'Friendly donor', 'show_amount' => 1];
        $response = $this->actingAs($donor)->post(route('donations.store', $campaign), $data);
        $donation = Donation::firstOrFail();
        $response->assertRedirect(route('donations.receipt', $donation));
        $this->assertSame(1234, $donation->amount_minor);
        $this->assertSame(0, $campaign->fresh()->confirmedTotal());
        $this->actingAs($donor)->post(route('donations.store', $campaign), $data)->assertRedirect(route('donations.receipt', $donation));
        $this->assertSame(1, Donation::count());
        $this->actingAs($admin)->patch(route('admin.donations.update', $donation), ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame(1234, $campaign->fresh()->confirmedTotal());
        $this->assertDatabaseHas('donation_events', ['donation_id' => $donation->id, 'from_status' => 'pending', 'to_status' => 'confirmed']);
        $this->patch(route('admin.donations.update', $donation), ['status' => 'failed'])->assertUnprocessable();
    }

    public function test_idempotency_key_reuse_with_changed_amount_is_rejected(): void
    {
        $owner = $this->user(); $donor = $this->user(); $campaign = $this->campaign($owner);
        $key = (string) Str::uuid();
        $this->actingAs($donor)->post(route('donations.store', $campaign), ['amount' => '10', 'idempotency_key' => $key])->assertRedirect();
        $this->post(route('donations.store', $campaign), ['amount' => '20', 'idempotency_key' => $key])->assertStatus(409);
        $this->assertSame(1, Donation::count());
    }

    public function test_pending_or_failed_records_never_inflate_campaign_progress(): void
    {
        $owner = $this->user(); $donor = $this->user(); $admin = $this->user(true); $campaign = $this->campaign($owner);
        $donation = Donation::create(['reference' => 'DON-'.Str::upper(Str::random(16)), 'campaign_id' => $campaign->id, 'user_id' => $donor->id, 'amount_minor' => 5000, 'status' => 'pending', 'payment_method' => 'manual']);
        $this->assertSame(0, $campaign->confirmedTotal());
        $this->actingAs($admin)->patch(route('admin.donations.update', $donation), ['status' => 'failed'])->assertRedirect();
        $this->assertSame(0, $campaign->fresh()->confirmedTotal());
    }

    public function test_donor_details_and_amount_are_hidden_without_explicit_public_opt_in(): void
    {
        $owner = $this->user(); $donor = $this->user(); $admin = $this->user(true); $campaign = $this->campaign($owner);
        $donation = Donation::create(['reference' => 'DON-'.Str::upper(Str::random(16)), 'campaign_id' => $campaign->id, 'user_id' => $donor->id, 'amount_minor' => 5000, 'status' => 'pending', 'is_anonymous' => true, 'payment_method' => 'manual']);
        $this->actingAs($admin)->patch(route('admin.donations.update', $donation), ['status' => 'confirmed'])->assertRedirect();
        $page = $this->get(route('campaigns.show', $campaign));
        $page->assertOk()->assertDontSee($donor->email)->assertDontSee($donor->name)->assertDontSee('DON-');
        $this->actingAs($donor)->get(route('donations.receipt', $donation))->assertOk()->assertSee($donation->reference);
        $this->actingAs($this->user())->get(route('donations.receipt', $donation))->assertForbidden();
    }

    public function test_public_name_and_amount_appear_only_when_donor_opts_in(): void
    {
        $owner = $this->user(); $donor = $this->user(); $admin = $this->user(true); $campaign = $this->campaign($owner);
        $donation = Donation::create(['reference' => 'DON-'.Str::upper(Str::random(16)), 'campaign_id' => $campaign->id, 'user_id' => $donor->id, 'amount_minor' => 1234, 'status' => 'pending', 'is_anonymous' => false, 'public_name' => 'Public alias', 'show_amount' => false, 'payment_method' => 'manual']);
        $this->actingAs($admin)->patch(route('admin.donations.update', $donation), ['status' => 'confirmed'])->assertRedirect();
        $response = $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Public alias');
        $this->assertStringNotContainsString('<span>Public alias</span><span>Rp 12,34</span>', $response->getContent());
        $donation->update(['show_amount' => true]);
        $response = $this->get(route('campaigns.show', $campaign));
        $this->assertStringContainsString('<span>Public alias</span><span>Rp 12,34</span>', $response->getContent());
    }

    public function test_donation_validation_rejects_malformed_and_too_small_values(): void
    {
        $owner = $this->user(); $donor = $this->user(); $campaign = $this->campaign($owner);
        $this->actingAs($donor)->post(route('donations.store', $campaign), ['amount' => '1e3', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->post(route('donations.store', $campaign), ['amount' => '0.50', 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);
        $this->assertSame(0, Donation::count());
    }

    public function test_only_campaign_owner_can_edit_a_pending_campaign(): void
    {
        $owner = $this->user(); $other = $this->user(); $campaign = $this->campaign($owner, ['status' => 'pending_review']);
        $this->actingAs($other)->get(route('campaigns.edit', $campaign))->assertForbidden();
        $this->actingAs($owner)->put(route('campaigns.update', $campaign), [
            'title' => 'Revised food fund', 'summary' => 'Updated summary', 'story' => 'Revised story', 'goal' => '200',
            'deadline' => now()->addDays(12)->toDateString(),
        ])->assertRedirect(route('campaigns.mine'));
        $this->assertSame('pending_review', $campaign->fresh()->status);
        $this->assertDatabaseHas('campaign_events', ['campaign_id' => $campaign->id, 'from_status' => 'pending_review', 'to_status' => 'pending_review']);
    }
}
