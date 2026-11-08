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

        <section class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">{{ __('Attachments') }}</flux:heading>

            @forelse ($note->attachments as $attachment)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                    <div class="min-w-0">
                        <flux:link :href="route('notes.attachments.download', [$note, $attachment])" class="truncate">
                            {{ $attachment->original_name }}
                        </flux:link>
                        <flux:text size="sm">{{ Number::fileSize($attachment->size) }}</flux:text>
                    </div>

                    <form method="POST" action="{{ route('notes.attachments.destroy', [$note, $attachment]) }}"
                          onsubmit="return confirm('{{ __('Delete this file?') }}')">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" variant="ghost" size="sm" icon="trash"
                                     :aria-label="__('Delete :name', ['name' => $attachment->original_name])" />
                    </form>
                </div>
            @empty
                <flux:text>{{ __('No files yet.') }}</flux:text>
            @endforelse

            {{-- The hint and the accept list come from config/attachments.php, like the validation. --}}
            <form method="POST" action="{{ route('notes.attachments.store', $note) }}"
                  enctype="multipart/form-data" class="flex flex-col gap-3">
                @csrf
                <flux:input type="file" name="file" :label="__('Add a file')" required
                            accept="{{ collect(config('attachments.mimes'))->map(fn ($ext) => '.'.$ext)->implode(',') }}" />
                <flux:text size="sm">
                    {{ __('Allowed: :types. Max :size.', [
                        'types' => implode(', ', config('attachments.mimes')),
                        'size' => Number::fileSize(config('attachments.max_kb') * 1024),
                    ]) }}
                </flux:text>
                <div>
                    <flux:button type="submit" icon="arrow-up-tray">{{ __('Upload') }}</flux:button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
