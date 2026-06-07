<?php

use Livewire\Volt\Component;

new class extends Component {
    public function mount()
    {
        return redirect()->to(url('/logistics/vehicles?tab=drivers'));
    }
}; ?>
<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8 text-center text-slate-500">
    Redirecting to Driver Intelligence...
</div>
