{{-- Empty form for a new note. Fields come from the shared _form partial. --}}
<x-layouts.app :title="__('New note')">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ __('New note') }}</flux:heading>

        <form method="POST" action="{{ route('notes.store') }}" class="flex flex-col gap-6">
            @csrf
            @include('notes._form')
        </form>
    </div>
</x-layouts.app>
