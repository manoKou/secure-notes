{{-- List of my notes, newest first, with a "New note" button. --}}
<x-layouts.app :title="__('My notes')">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <x-flash />

        <div class="flex items-center justify-between">
            <flux:heading size="xl" level="1">{{ __('My notes') }}</flux:heading>
            <flux:button variant="primary" icon="plus" :href="route('notes.create')">{{ __('New note') }}</flux:button>
        </div>

        @forelse ($notes as $note)
            <a href="{{ route('notes.show', $note) }}"
               class="block rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                <flux:heading>{{ $note->title }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $note->updated_at->diffForHumans() }}
                    @if ($note->attachments_count)
                        · {{ trans_choice(':count file|:count files', $note->attachments_count) }}
                    @endif
                </flux:text>
            </a>
        @empty
            <flux:text>{{ __('No notes yet. Create your first one.') }}</flux:text>
        @endforelse

        {{ $notes->links() }}
    </div>
</x-layouts.app>
