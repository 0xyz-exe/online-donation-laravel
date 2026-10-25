<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">{{ $campaign->exists ? 'Edit campaign' : 'Start a campaign' }}</h1></x-slot>
    <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="mb-5 text-sm text-gray-600">Campaigns are reviewed before publication. No funds are processed by this demo.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ $campaign->exists ? route('campaigns.update', $campaign) : route('campaigns.store') }}" class="space-y-5 rounded-xl bg-white p-6 shadow-sm">
            @csrf @if($campaign->exists)@method('PUT')@endif
            @foreach(['title' => 'Title', 'summary' => 'Short summary'] as $field => $label)
                <div><label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $campaign->$field) }}" required maxlength="{{ $field === 'title' ? 160 : 500 }}" class="mt-1 w-full rounded border-gray-300">@error($field)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            @endforeach
            <div><label for="story" class="block text-sm font-medium">Campaign story</label><textarea id="story" name="story" rows="8" maxlength="20000" required class="mt-1 w-full rounded border-gray-300">{{ old('story', $campaign->story) }}</textarea>@error('story')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div class="grid gap-4 sm:grid-cols-2"><div><label for="goal" class="block text-sm font-medium">Goal (Rp)</label><input id="goal" name="goal" type="number" min="1" step="0.01" max="9999999999.99" value="{{ old('goal', $campaign->exists ? number_format($campaign->goal_minor / 100, 2, '.', '') : '') }}" required class="mt-1 w-full rounded border-gray-300">@error('goal')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div><div><label for="deadline" class="block text-sm font-medium">Deadline</label><input id="deadline" name="deadline" type="date" min="{{ now()->addDay()->toDateString() }}" value="{{ old('deadline', $campaign->deadline?->toDateString()) }}" required class="mt-1 w-full rounded border-gray-300">@error('deadline')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div></div>
            <div><label for="image" class="block text-sm font-medium">Campaign image (JPG, PNG, WebP; max 4 MB)</label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm">@error('image')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <label class="flex items-start gap-2"><input type="checkbox" name="allows_anonymous" value="1" @checked(old('allows_anonymous', $campaign->exists ? $campaign->allows_anonymous : true)) class="mt-1 rounded border-gray-300"><span class="text-sm">Allow donors to hide their public identity</span></label>
            <button class="rounded bg-indigo-700 px-5 py-2 font-medium text-white hover:bg-indigo-800">Submit for review</button>
        </form>
    </section>
</x-app-layout>
