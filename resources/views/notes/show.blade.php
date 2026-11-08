{{-- One note: title, body (escaped plain text), attachments, upload form. --}}
<x-layouts.app :title="$note->title">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <x-flash />

        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $note->title }}</flux:heading>
                <flux:text class="mt-1">{{ __('Updated :time', ['time' => $note->updated_at->diffForHumans()]) }}</flux:text>
            </div>

            <div class="flex gap-2">
                <flux:button icon="pencil" :href="route('notes.edit', $note)">{{ __('Edit') }}</flux:button>

                <form method="POST" action="{{ route('notes.destroy', $note) }}"
                      onsubmit="return confirm('{{ __('Delete this note and all its files?') }}')">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="danger" icon="trash">{{ __('Delete') }}</flux:button>
                </form>
            </div>
        </div>

        {{-- Escaped with {{ }}: no HTML is ever rendered. whitespace-pre-line keeps line breaks. --}}
        <div class="whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $note->body }}</div>

        <flux:separator />

        {{-- TODO: attachments list: download link + delete form per file --}}
        {{-- TODO: upload form (POST → notes.attachments.store, enctype multipart),
                   hint with allowed types and max size from config('attachments') --}}
    </div>
</x-layouts.app>
