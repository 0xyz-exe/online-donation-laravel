<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Donation;
use App\Models\DonationEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DonationController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::query()->where('status', 'active')->where('deadline', '>=', now())->with('owner')
            ->withSum(['donations as confirmed_total_minor' => fn ($q) => $q->where('status', 'confirmed')], 'amount_minor')
            ->latest()->paginate(12);
        return view('campaigns.index', compact('campaigns'));
    }

    public function createCampaign()
    {
        return view('campaigns.create', ['campaign' => new Campaign]);
    }

    public function saveCampaign(Request $request, ?Campaign $campaign = null)
    {
        $user = $request->user();
        if ($campaign) {
            abort_unless($campaign->owner_id === $user->id && in_array($campaign->status, ['pending_review', 'rejected'], true), 403);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'summary' => ['required', 'string', 'max:500'],
            'story' => ['required', 'string', 'max:20000'], 'goal' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'deadline' => ['required', 'date', 'after:today'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'allows_anonymous' => ['nullable', 'boolean'],
        ]);
        $goalMinor = $this->minor($data['goal']);
        abort_if($goalMinor < 100 || $goalMinor > 999999999999, 422, 'Goal is outside the supported range.');
        $oldPath = $campaign?->image_path;
        $path = $request->file('image')?->store('campaigns', 'public') ?? $oldPath;
        $from = $campaign?->status;
        $campaign ??= new Campaign;
        $campaign->fill([
            'owner_id' => $user->id, 'title' => $data['title'], 'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(7)),
            'summary' => $data['summary'], 'story' => $data['story'], 'goal_minor' => $goalMinor,
            'deadline' => $data['deadline'], 'image_path' => $path, 'allows_anonymous' => $request->boolean('allows_anonymous'),
            'status' => 'pending_review',
        ]);
        DB::transaction(function () use ($campaign, $from, $user) {
            $campaign->save();
            CampaignEvent::create(['campaign_id' => $campaign->id, 'actor_user_id' => $user->id, 'from_status' => $from, 'to_status' => 'pending_review', 'note' => $from ? 'Campaign updated and resubmitted for review.' : 'Campaign submitted for review.']);
        });
        if ($oldPath && $oldPath !== $path) Storage::disk('public')->delete($oldPath);
        return redirect()->route('campaigns.mine')->with('status', 'Campaign submitted for admin review.');
    }

    public function editCampaign(Request $request, Campaign $campaign)
    {
        abort_unless($campaign->owner_id === $request->user()->id && in_array($campaign->status, ['pending_review', 'rejected'], true), 403);
        return view('campaigns.create', compact('campaign'));
    }

    public function mine(Request $request)
    {
        $campaigns = Campaign::where('owner_id', $request->user()->id)->latest()->paginate(12);
        return view('campaigns.mine', compact('campaigns'));
    }

    public function show(Campaign $campaign)
    {
        abort_unless($campaign->status === 'active' || ($campaign->status === 'completed'), 404);
        $campaign->load('owner');
        $donations = $campaign->donations()->where('status', 'confirmed')->where('is_anonymous', false)->whereNotNull('public_name')->latest('confirmed_at')->limit(12)->get();
        $campaign->loadSum(['donations as confirmed_total_minor' => fn ($q) => $q->where('status', 'confirmed')], 'amount_minor');
        return view('campaigns.show', ['campaign' => $campaign, 'donations' => $donations, 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function donate(Request $request, Campaign $campaign)
    {
        abort_unless($campaign->status === 'active' && $campaign->deadline->isFuture(), 404);
        $data = $request->validate([
            'amount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'idempotency_key' => ['required', 'string', 'max:100'], 'is_anonymous' => ['nullable', 'boolean'],
            'public_name' => ['nullable', 'string', 'max:80'], 'show_amount' => ['nullable', 'boolean'],
        ]);
        $amount = $this->minor($data['amount']);
        abort_if($amount < 100 || $amount > 999999999999, 422, 'Donation must be at least Rp 1 and within the supported limit.');
        $anonymous = $campaign->allows_anonymous && $request->boolean('is_anonymous');
        $attrs = [
            'reference' => 'DON-'.Str::upper(Str::random(16)), 'campaign_id' => $campaign->id,
            'user_id' => $request->user()->id, 'amount_minor' => $amount, 'status' => 'pending',
            'is_anonymous' => $anonymous, 'public_name' => $anonymous ? null : ($data['public_name'] ?? null),
            'show_amount' => !$anonymous && $request->boolean('show_amount'), 'payment_method' => 'manual',
        ];
        try {
            $donation = DB::transaction(function () use ($campaign, $request, $data, $attrs) {
                $locked = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'active' && $locked->deadline->isFuture(), 404);
                $existing = Donation::where('idempotency_user_id', $request->user()->id)->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    abort_unless($existing->campaign_id === $campaign->id && $existing->amount_minor === $attrs['amount_minor'], 409, 'Submission key was already used for another donation.');
                    return $existing;
                }
                $attrs['idempotency_user_id'] = $request->user()->id;
                $attrs['idempotency_key'] = $data['idempotency_key'];
                $donation = Donation::create($attrs);
                DonationEvent::create(['donation_id' => $donation->id, 'actor_user_id' => $request->user()->id, 'to_status' => 'pending', 'note' => 'Donation recorded; awaiting manual confirmation.']);
                return $donation;
            });
        } catch (QueryException $e) {
            $donation = Donation::where('idempotency_user_id', $request->user()->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if (!$donation) throw $e;
            abort_unless($donation->campaign_id === $campaign->id && $donation->amount_minor === $amount, 409);
        }
        return redirect()->route('donations.receipt', $donation)->with('status', 'Donation recorded. Confirmation is pending manual review.');
    }

    public function receipt(Request $request, Donation $donation)
    {
        abort_unless($donation->user_id === $request->user()->id || $request->user()->is_admin, 403);
        return view('donations.receipt', compact('donation'));
    }

    public function history(Request $request)
    {
        $donations = Donation::with('campaign')->where('user_id', $request->user()->id)->latest()->paginate(15);
        return view('donations.history', compact('donations'));
    }

    public function admin(Request $request)
    {
        Gate::authorize('admin');
        $campaigns = Campaign::with('owner')->whereIn('status', ['pending_review', 'active', 'rejected'])->latest()->paginate(15, ['*'], 'campaigns_page');
        $donations = Donation::with(['campaign', 'user'])->where('status', 'pending')->latest()->paginate(15, ['*'], 'donations_page');
        return view('admin.dashboard', compact('campaigns', 'donations'));
    }

    public function moderateCampaign(Request $request, Campaign $campaign)
    {
        Gate::authorize('admin');
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'rejected', 'completed', 'closed'])], 'note' => ['nullable', 'string', 'max:500']]);
        $allowed = match ($campaign->status) {
            'pending_review' => ['active', 'rejected'], 'active' => ['completed', 'closed'], default => [],
        };
        abort_unless(in_array($data['status'], $allowed, true), 422, 'Invalid campaign status transition.');
        DB::transaction(function () use ($campaign, $data, $request) {
            $locked = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $allowed = match ($from) {
                'pending_review' => ['active', 'rejected'], 'rejected' => ['active'], 'active' => ['completed', 'closed'], default => [],
            };
            abort_unless(in_array($data['status'], $allowed, true), 422, 'Invalid campaign status transition.');
            $locked->update(['status' => $data['status']]);
            CampaignEvent::create(['campaign_id' => $locked->id, 'actor_user_id' => $request->user()->id, 'from_status' => $from, 'to_status' => $data['status'], 'note' => $data['note'] ?? null]);
        });
        return back()->with('status', 'Campaign status updated.');
    }

    public function reviewDonation(Request $request, Donation $donation)
    {
        Gate::authorize('admin');
        $data = $request->validate(['status' => ['required', Rule::in(['confirmed', 'failed'])], 'note' => ['nullable', 'string', 'max:500']]);
        abort_unless($donation->status === 'pending', 422, 'Only pending donations can be reviewed.');
        DB::transaction(function () use ($donation, $data, $request) {
            $locked = Donation::whereKey($donation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'Only pending donations can be reviewed.');
            $from = $locked->status; $now = now();
            $locked->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => $now, 'confirmed_at' => $data['status'] === 'confirmed' ? $now : null]);
            DonationEvent::create(['donation_id' => $locked->id, 'actor_user_id' => $request->user()->id, 'from_status' => $from, 'to_status' => $data['status'], 'note' => $data['note'] ?? null]);
        });
        return back()->with('status', 'Donation reviewed.');
    }

    public function report()
    {
        Gate::authorize('admin');
        $campaigns = Campaign::withSum(['donations as confirmed_total_minor' => fn ($q) => $q->where('status', 'confirmed')], 'amount_minor')
            ->withCount(['donations as pending_count' => fn ($q) => $q->where('status', 'pending')])->with('owner')->latest()->paginate(25);
        return view('admin.report', compact('campaigns'));
    }

    public function export()
    {
        Gate::authorize('admin');
        $rows = Donation::query()->where('status', 'confirmed')->orderBy('confirmed_at')->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w'); fputcsv($out, ['reference', 'campaign_id', 'amount_minor', 'status', 'confirmed_at']);
            foreach ($rows as $row) fputcsv($out, [$row->reference, $row->campaign_id, $row->amount_minor, $row->status, $row->confirmed_at?->toIso8601String()]);
            fclose($out);
        }, 'confirmed-donations.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function minor(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
