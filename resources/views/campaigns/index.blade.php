<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">Campaigns that matter</h1></x-slot>
    <section class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        @if(session('status'))<div role="status" class="mb-5 rounded bg-green-50 p-4 text-green-800">{{ session('status') }}</div>@endif
        <p class="mb-6 text-gray-600">Support a cause, or submit your own campaign for review.</p>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($campaigns as $campaign)
                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    @if($campaign->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($campaign->image_path) }}" alt="Campaign image" class="h-48 w-full object-cover">@endif
                    <div class="p-5">
                        <h2 class="text-lg font-semibold"><a class="text-indigo-700 hover:underline" href="{{ route('campaigns.show', $campaign) }}">{{ $campaign->title }}</a></h2>
                        <p class="mt-2 text-sm text-gray-600">{{ $campaign->summary }}</p>
                        @php($raised = (int) ($campaign->confirmed_total_minor ?? 0))
                        <div class="mt-4"><div class="flex justify-between text-sm"><span>Raised</span><span><x-money :minor="$raised" /></span></div><div class="mt-1 h-2 rounded bg-gray-100"><div class="h-2 rounded bg-indigo-600" style="width: {{ min(100, (int) floor($raised * 100 / $campaign->goal_minor)) }}%"></div></div><div class="mt-1 text-xs text-gray-500">Goal <x-money :minor="$campaign->goal_minor" /> · Ends {{ $campaign->deadline->format('M j, Y') }}</div></div>
                        <a class="mt-4 inline-block rounded bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800" href="{{ route('campaigns.show', $campaign) }}">View campaign</a>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed p-8 text-gray-600">No active campaigns yet. Sign in to submit one for review.</div>
            @endforelse
        </div>
        <div class="mt-6">{{ $campaigns->links() }}</div>
    </section>
</x-app-layout>
