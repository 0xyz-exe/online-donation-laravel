<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Campaign reports</h1></x-slot>
    <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-gray-600">Only manually confirmed records count as raised funds. No seeded donations.</p><a href="{{ route('admin.donations.csv') }}" class="rounded bg-indigo-700 px-4 py-2 text-sm text-white">Export confirmed donations (CSV)</a></div>
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm"><table class="min-w-full divide-y divide-gray-200"><thead><tr class="text-left text-sm"><th class="p-4">Campaign</th><th class="p-4">Status</th><th class="p-4">Goal</th><th class="p-4">Confirmed</th><th class="p-4">Pending records</th><th class="p-4">Deadline</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($campaigns as $campaign)<tr class="text-sm"><td class="p-4 font-medium">{{ $campaign->title }}</td><td class="p-4">{{ str_replace('_', ' ', $campaign->status) }}</td><td class="p-4"><x-money :minor="$campaign->goal_minor" /></td><td class="p-4"><x-money :minor="$campaign->confirmed_total_minor ?? 0" /></td><td class="p-4">{{ $campaign->pending_count }}</td><td class="p-4">{{ $campaign->deadline->format('M j, Y') }}</td></tr>@empty<tr><td colspan="6" class="p-6 text-gray-600">No campaigns recorded.</td></tr>@endforelse</tbody></table></div>
        <div>{{ $campaigns->links() }}</div>
    </section>
</x-app-layout>
