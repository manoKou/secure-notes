{{-- Title + body fields, shared by create and edit so they never differ.
     Shows validation errors and keeps old input after a failed submit. --}}

<flux:input name="title" :label="__('Title')" :value="old('title', $note->title ?? '')"
            required maxlength="255" autofocus />

<flux:textarea name="body" :label="__('Body')" rows="10" maxlength="10000">{{ old('body', $note->body ?? '') }}</flux:textarea>

<div>
    <flux:input type="file" name="files[]" multiple :label="__('Files (optional)')"
                accept="{{ collect(config('attachments.mimes'))->map(fn ($ext) => '.'.$ext)->implode(',') }}" />
    <flux:error name="files" />
    <flux:text size="sm" class="mt-2">
        {{ __('Up to :count files. Allowed: :types. Max :size each.', [
            'count' => config('attachments.max_files'),
            'types' => implode(', ', config('attachments.mimes')),
            'size' => Number::fileSize(config('attachments.max_kb') * 1024),
        ]) }}
    </flux:text>
</div>

<div class="flex items-center gap-3">
    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
    <flux:button variant="ghost" :href="isset($note) ? route('notes.show', $note) : route('notes.index')">
        {{ __('Cancel') }}
    </flux:button>
</div>
