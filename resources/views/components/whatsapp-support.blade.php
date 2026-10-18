@props([
    'context' => 'public',
    'organisation' => null,
    'eventTitle' => null,
    'pageLabel' => null,
    'status' => null,
    'variant' => 'floating',
    'class' => '',
])

@php
    if (! \App\Support\WhatsAppSupport::isEnabled()) {
        return;
    }

    $orgName = $organisation instanceof \App\Models\Organization
        ? $organisation->name
        : (is_string($organisation) ? $organisation : null);

    $url = \App\Support\WhatsAppSupport::url($context, $pageLabel, $orgName, $eventTitle, $status);
    $number = \App\Support\WhatsAppSupport::number();
    $isStudio = $context === 'studio' || $variant === 'studio';
    $isAuth = in_array($context, ['creator', 'dashboard', 'admin', 'authenticated', 'affiliate'], true);
    $isListener = $context === 'listener';

    $containerClass = 'soundmix-wa-float';
    if ($isStudio) {
        $containerClass .= ' soundmix-wa-float--studio';
    } elseif ($isListener) {
        $containerClass .= ' soundmix-wa-float--listener';
    }
@endphp

@if ($url)
    <div
        class="{{ $containerClass }}"
        data-soundmix-wa-widget="{{ $context }}"
    >
        <a
            href="{{ $url }}"
            target="_blank"
            rel="noopener noreferrer"
            id="{{ $isStudio ? 'btn-studio-whatsapp' : 'btn-soundmix-whatsapp' }}"
            data-wa-base="https://wa.me/{{ $number }}"
            data-org-name="{{ $orgName ?? '' }}"
            data-event-title="{{ $eventTitle ?? '' }}"
            data-context="{{ $context }}"
            class="group pointer-events-auto inline-flex items-center justify-center min-h-[44px] min-w-[44px] h-11 w-11 sm:h-auto sm:w-auto gap-2 rounded-full font-medium transition-all duration-200 transform hover:scale-105 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366] focus-visible:ring-offset-2 {{ $isStudio ? 'soundmix-wa-btn--studio px-0 sm:px-3 sm:py-2 text-xs font-semibold backdrop-blur-sm' : ($isAuth ? 'bg-zinc-900/95 hover:bg-zinc-900 text-zinc-100 dark:bg-zinc-900/95 dark:text-zinc-100 border border-zinc-700/80 dark:border-zinc-700/80 shadow-md backdrop-blur-sm px-0 sm:px-3.5 sm:py-2.5 text-xs font-semibold' : 'bg-[#25D366] hover:bg-[#20bd5a] text-white shadow-lg shadow-emerald-950/20 ring-1 ring-black/10 dark:ring-white/20 px-0 sm:px-3.5 sm:py-2.5 text-xs sm:text-sm font-semibold') }} {{ $class }}"
            aria-label="Chat with SoundMix Support on WhatsApp"
            title="Chat with SoundMix Support on WhatsApp"
        >
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $isAuth ? 'bg-[#25D366] text-white' : '' }}">
                <svg class="{{ $isAuth ? 'h-3.5 w-3.5' : ($isStudio ? 'h-4 w-4 text-[#25D366]' : 'h-5 w-5 text-white') }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
            </span>
            <span class="hidden sm:inline {{ $isStudio ? 'text-xs' : ($isAuth ? 'text-xs' : 'tracking-tight') }}">
                {{ $isStudio ? 'Support' : ($isAuth ? 'Support' : 'Chat with us') }}
            </span>
        </a>
    </div>
@endif
