<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">{{ $campaign->title }}</h1></x-slot>
    <section class="mx-auto grid max-w-7xl gap-8 px-4 py-8 lg:grid-cols-3 sm:px-6 lg:px-8">
        <article class="lg:col-span-2 overflow-hidden rounded-xl bg-white shadow-sm">
            @if($campaign->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($campaign->image_path) }}" alt="Campaign image for {{ $campaign->title }}" class="max-h-[28rem] w-full object-cover">@endif
            <div class="p-6"><p class="text-lg text-gray-600">{{ $campaign->summary }}</p><div class="prose mt-6 max-w-none whitespace-pre-line">{{ $campaign->story }}</div>
                <div class="mt-7"><div class="flex justify-between text-sm"><span><strong><x-money :minor="$campaign->confirmed_total_minor ?? 0" /></strong> raised</span><span>Goal <x-money :minor="$campaign->goal_minor" /></span></div><div class="mt-2 h-3 rounded-full bg-gray-100"><div class="h-3 rounded-full bg-indigo-600" style="width: {{ min(100, (int) floor(((int) ($campaign->confirmed_total_minor ?? 0)) * 100 / $campaign->goal_minor)) }}%"></div></div><p class="mt-2 text-sm text-gray-500">Campaign ends {{ $campaign->deadline->format('M j, Y') }}. Only manually confirmed donations count.</p></div>
            </div>
        </article>
        <aside class="space-y-5">
            <section class="rounded-xl bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">Support this campaign</h2><p class="mt-1 text-sm text-gray-600">Manual/offline simulation only. This site does not collect or transfer money. Donate through the campaign organizer’s verified channel, then record it here for confirmation.</p>
                @auth
                    @if($campaign->status === 'active' && $campaign->deadline->isFuture())
                    <form method="POST" action="{{ route('donations.store', $campaign) }}" class="mt-5 space-y-4">@csrf<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}"><div><label for="amount" class="block text-sm font-medium">Donation amount (Rp)</label><input id="amount" name="amount" type="number" min="1" max="9999999999.99" step="0.01" required class="mt-1 w-full rounded border-gray-300">@error('amount')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                        @if($campaign->allows_anonymous)<label class="flex gap-2 text-sm"><input name="is_anonymous" value="1" type="checkbox" checked class="mt-1 rounded border-gray-300">Keep my identity private on the public donor list</label>@endif
                        <div><label for="public_name" class="block text-sm font-medium">Public name (optional)</label><input id="public_name" name="public_name" maxlength="80" class="mt-1 w-full rounded border-gray-300"><p class="mt-1 text-xs text-gray-500">Your account name and email are never shown publicly.</p></div>
                        <label class="flex gap-2 text-sm"><input name="show_amount" value="1" type="checkbox" class="mt-1 rounded border-gray-300">Show my donation amount publicly</label>
                        <button class="w-full rounded bg-indigo-700 px-4 py-2 font-medium text-white hover:bg-indigo-800">Record donation</button>
                    </form><p class="mt-3 text-xs text-gray-500">A pending receipt is generated; organizers or admins confirm offline donations. No payment processor is connected.</p>
                    @else <p class="mt-4 text-sm text-gray-600">Donation period ended.</p>@endif
                @else <a class="mt-4 inline-block rounded bg-indigo-700 px-4 py-2 text-white" href="{{ route('login') }}">Sign in to donate</a>@endauth
            </section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h2 class="font-semibold">Recent supporters</h2>@forelse($donations as $donation)<div class="mt-3 flex justify-between gap-2 border-t pt-3 text-sm"><span>{{ $donation->public_name ?: 'Supporter' }}</span>@if($donation->show_amount)<span><x-money :minor="$donation->amount_minor" /></span>@endif</div>@empty<p class="mt-3 text-sm text-gray-500">No public supporters yet.</p>@endforelse</section>
        </aside>
    </section>
</x-app-layout>
