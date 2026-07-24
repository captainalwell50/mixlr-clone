@php
    $group = $group ?? null; // mobile|desktop|null (all)
    $stores = collect(config('downloads'))
        ->when($group, fn ($c) => $c->where('group', $group))
        ->all();
@endphp
<div class="dl-stores">
    @foreach ($stores as $key => $store)
        <div class="dl-store{{ ($store['available'] ?? false) ? ' is-available' : ' is-soon' }}">
            @if (($store['available'] ?? false) && filled($store['url'] ?? null))
                <a href="{{ $store['url'] }}" class="dl-store-badge" target="_blank" rel="noopener noreferrer">
                    @include('partials.store-badges', ['store' => $store['badge']])
                </a>
            @else
                <div class="dl-store-badge is-disabled" aria-disabled="true">
                    @include('partials.store-badges', ['store' => $store['badge']])
                </div>
                <p class="dl-store-soon">Coming soon{{ isset($store['label']) ? ' · '.$store['label'] : '' }}</p>
            @endif
        </div>
    @endforeach
</div>
