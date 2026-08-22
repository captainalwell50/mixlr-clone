@php
    $giving = $organization->publicGiving();
    $buttonClass = $buttonClass ?? (($compact ?? false) ? 'embed-give' : 'portal-give');
@endphp
@if ($giving)
    <div class="give-root" data-give-root>
        <button type="button" class="{{ $buttonClass }}" data-give-open>
            <svg viewBox="0 -960 960 960" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M535-87q11 3 25.5 2.5T585-89l295-111q0-34-24-57t-56-23H526q-3 0-7-.5t-6-1.5l-59-21q-8-3-11-10t-1-15q2-7 10-11t16-1l45 17q4 2 6.5 2.5t7.5.5h105q19 0 33.5-13t14.5-34q0-14-8.5-27T649-412L372-515q-7-2-14-3.5t-14-1.5h-64v361l255 72ZM40-160q0 33 23.5 56.5T120-80q33 0 56.5-23.5T200-160v-280q0-33-23.5-56.5T120-520q-33 0-56.5 23.5T40-440v280Zm570.5-317.5Q596-483 584-494L474-602q-31-30-52.5-66.5T400-748q0-55 38.5-93.5T532-880q32 0 60 13.5t48 36.5q20-23 48-36.5t60-13.5q55 0 93.5 38.5T880-748q0 43-21 79.5T807-602L696-494q-12 11-26.5 16.5T640-472q-15 0-29.5-5.5Z"/></svg>
            Give online
        </button>

        <dialog class="give-dialog" data-give-dialog aria-labelledby="give-dialog-title">
            <div class="give-sheet">
                <div class="give-sheet-head">
                    <div>
                        <p class="give-kicker">Give online</p>
                        <h2 id="give-dialog-title">{{ $organization->name }}</h2>
                    </div>
                    <button type="button" class="give-close" data-give-close aria-label="Close">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    </button>
                </div>

                @if (filled($giving['note']))
                    <p class="give-note">{{ $giving['note'] }}</p>
                @endif

                @if (filled($giving['url']))
                    <a class="give-cta" href="{{ $giving['url'] }}" target="_blank" rel="noopener noreferrer">
                        Open giving page
                    </a>
                @endif

                @if ($organization->hasGivingAccount())
                    <div class="give-account" @if (filled($giving['url'])) data-give-has-url @endif>
                        <p class="give-account-label">Account details</p>

                        @if (filled($giving['account_name']))
                            <div class="give-row">
                                <div>
                                    <span class="give-row-key">Name</span>
                                    <span class="give-row-val">{{ $giving['account_name'] }}</span>
                                </div>
                                <button type="button" class="give-copy" data-copy="{{ $giving['account_name'] }}">Copy</button>
                            </div>
                        @endif

                        @if (filled($giving['bank_name']))
                            <div class="give-row">
                                <div>
                                    <span class="give-row-key">Bank</span>
                                    <span class="give-row-val">{{ $giving['bank_name'] }}</span>
                                </div>
                                <button type="button" class="give-copy" data-copy="{{ $giving['bank_name'] }}">Copy</button>
                            </div>
                        @endif

                        @if (filled($giving['account_number']))
                            <div class="give-row">
                                <div>
                                    <span class="give-row-key">Account number</span>
                                    <span class="give-row-val give-row-val--mono">{{ $giving['account_number'] }}</span>
                                </div>
                                <button type="button" class="give-copy" data-copy="{{ $giving['account_number'] }}">Copy</button>
                            </div>
                        @endif

                        @php
                            $allDetails = collect([
                                filled($giving['account_name']) ? 'Name: '.$giving['account_name'] : null,
                                filled($giving['bank_name']) ? 'Bank: '.$giving['bank_name'] : null,
                                filled($giving['account_number']) ? 'Account: '.$giving['account_number'] : null,
                            ])->filter()->implode("\n");
                        @endphp
                        @if ($allDetails !== '')
                            <button type="button" class="give-copy-all" data-copy="{{ $allDetails }}">Copy all details</button>
                        @endif
                    </div>
                @endif
            </div>
        </dialog>
    </div>
    <script>
        (function () {
            document.querySelectorAll('[data-give-root]').forEach(function (root) {
                if (root.dataset.giveBound === '1') {
                    return;
                }
                root.dataset.giveBound = '1';
                var dialog = root.querySelector('[data-give-dialog]');
                var openBtn = root.querySelector('[data-give-open]');
                if (!dialog || !openBtn) {
                    return;
                }
                openBtn.addEventListener('click', function () {
                    if (typeof dialog.showModal === 'function') {
                        dialog.showModal();
                    }
                });
                root.querySelectorAll('[data-give-close]').forEach(function (btn) {
                    btn.addEventListener('click', function () { dialog.close(); });
                });
                dialog.addEventListener('click', function (event) {
                    if (event.target === dialog) {
                        dialog.close();
                    }
                });
                root.querySelectorAll('[data-copy]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var text = btn.getAttribute('data-copy') || '';
                        var label = btn.textContent;
                        var done = function () {
                            btn.textContent = 'Copied';
                            window.setTimeout(function () { btn.textContent = label; }, 1600);
                        };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(text).then(done).catch(function () {});
                        }
                    });
                });
            });
        })();
    </script>
@endif
