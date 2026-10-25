<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">My campaigns</h1></x-slot>
    <section class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        @if(session('status'))<div role="status" class="mb-5 rounded bg-green-50 p-4 text-green-800">{{ session('status') }}</div>@endif
        <a href="{{ route('campaigns.create') }}" class="mb-5 inline-block rounded bg-indigo-700 px-4 py-2 text-white">Start a campaign</a>
        <div class="space-y-3">@forelse($campaigns as $campaign)<article class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-white p-5 shadow-sm"><div><h2 class="font-semibold">{{ $campaign->title }}</h2><p class="text-sm text-gray-600">Status: {{ str_replace('_', ' ', $campaign->status) }} · Created {{ $campaign->created_at->format('M j, Y') }}</p></div>@if(in_array($campaign->status, ['pending_review','rejected'], true))<a class="text-indigo-700 underline" href="{{ route('campaigns.edit', $campaign) }}">Edit / resubmit</a>@elseif(in_array($campaign->status,['active','completed'],true))<a class="text-indigo-700 underline" href="{{ route('campaigns.show', $campaign) }}">View public campaign</a>@endif</article>@empty<p class="rounded-xl bg-white p-6 text-gray-600">No campaigns submitted yet.</p>@endforelse</div><div class="mt-6">{{ $campaigns->links() }}</div>
    </section>
</x-app-layout>
