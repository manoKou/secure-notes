{{-- Edit form for a note I own. Same fields as create, via _form. --}}
<x-layouts.app :title="__('Edit note')">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ __('Edit note') }}</flux:heading>

        <form method="POST" action="{{ route('notes.update', $note) }}" enctype="multipart/form-data" class="flex flex-col gap-6">
            @csrf
            @method('PUT')
            @include('notes._form')
        </form>
    </div>
</x-layouts.app>
