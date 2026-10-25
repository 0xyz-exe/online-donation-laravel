<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_and_admin_views_render_and_csv_is_admin_only(): void
    {
        $owner = User::factory()->create(); $admin = User::factory()->create(); $admin->forceFill(['is_admin' => true])->save();
        $campaign = Campaign::create(['owner_id' => $owner->id, 'title' => 'Community food', 'slug' => 'community-food', 'summary' => 'Support neighbors', 'story' => 'Campaign details', 'goal_minor' => 100000, 'deadline' => now()->addDays(7), 'status' => 'pending_review']);
        $donation = Donation::create(['reference' => 'DON-'.Str::upper(Str::random(16)), 'campaign_id' => $campaign->id, 'user_id' => $owner->id, 'amount_minor' => 1250, 'status' => 'pending', 'payment_method' => 'manual']);
        $this->actingAs($owner)->get(route('campaigns.create'))->assertOk();
        $this->get(route('campaigns.mine'))->assertOk();
        $this->get(route('campaigns.edit', $campaign))->assertOk();
        $this->get(route('donations.history'))->assertOk();
        $this->get(route('donations.receipt', $donation))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.reports'))->assertForbidden();
        $this->get(route('admin.donations.csv'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.reports'))->assertOk();
        $this->get(route('admin.donations.csv'))->assertDownload('confirmed-donations.csv');
    }
}
