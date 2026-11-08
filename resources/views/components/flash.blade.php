{{-- Success / error message after a redirect (e.g. "Note saved").
     Used as <x-flash /> on any page that receives a redirect. --}}

@if (session('success'))
    <flux:callout variant="success" icon="check-circle" :heading="session('success')" />
@endif

@if (session('error'))
    <flux:callout variant="danger" icon="x-circle" :heading="session('error')" />
@endif
