<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Donation receipt</h1></x-slot>
    <section class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8"><div class="rounded-xl bg-white p-6 shadow-sm">
        @if(session('status'))<div role="status" class="mb-5 rounded bg-blue-50 p-4 text-blue-800">{{ session('status') }}</div>@endif
        <p class="text-sm text-gray-500">Reference {{ $donation->reference }}</p><h2 class="mt-2 text-xl font-semibold">{{ $donation->campaign->title }}</h2><p class="mt-4">Recorded amount: <strong><x-money :minor="$donation->amount_minor" /></strong></p>
        <p class="mt-3 rounded bg-amber-50 p-3 text-sm text-amber-900">Status: {{ ucfirst($donation->status) }}. This is a record of a manual/offline donation, not proof of payment or a payment processor receipt. Only confirmation by an administrator counts toward the campaign.</p>
        <p class="mt-3 text-sm text-gray-600">Payment method: {{ ucfirst($donation->payment_method) }}. Recorded {{ $donation->created_at->format('M j, Y H:i') }}.</p><a class="mt-5 inline-block text-indigo-700 underline" href="{{ route('donations.history') }}">Donation history</a>
    </div></section>
</x-app-layout>
