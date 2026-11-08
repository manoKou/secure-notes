{{-- One note: title, body (escaped plain text), attachments, upload form. --}}
<x-layouts.app :title="$note->title">
    <x-flash />

    {{-- TODO: title + body with {{ }} (escaped, no HTML rendering) --}}
    {{-- TODO: edit link + delete form (DELETE → notes.destroy) --}}
    {{-- TODO: attachments list: download link + delete form per file --}}
    {{-- TODO: upload form (POST → notes.attachments.store, enctype multipart),
               hint with allowed types and max size from config('attachments') --}}
</x-layouts.app>
