@php
    $giving = $organization->publicGiving();
    $buttonClass = $buttonClass ?? (($compact ?? false) ? 'embed-give' : 'portal-give');
@endphp
@if ($giving)
    <div class="give-root" data-give-root>
        <button type="button" class="{{ $buttonClass }}" data-give-open>
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
