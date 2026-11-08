{{-- Empty form for a new note. Fields come from the shared _form partial. --}}
<x-layouts.app :title="__('New note')">
    {{-- TODO: form POST → notes.store, @csrf, @include('notes._form') --}}
</x-layouts.app>
