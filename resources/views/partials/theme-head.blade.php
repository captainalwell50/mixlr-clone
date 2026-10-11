<script>
    (function() {
        try {
            var preference = localStorage.getItem('soundmix_theme') || 'system';
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var isDark = preference === 'dark' || (preference === 'system' && prefersDark);
            var root = document.documentElement;
            if (isDark) {
                root.classList.add('dark');
                root.setAttribute('data-theme', 'dark');
            } else {
                root.classList.remove('dark');
                root.setAttribute('data-theme', 'light');
            }
            root.setAttribute('data-theme-preference', preference);
        } catch (e) {}
    })();

    window.SoundMixTheme = {
        getPreference: function() {
            try {
                return localStorage.getItem('soundmix_theme') || 'system';
            } catch (e) {
                return 'system';
            }
        },
        setTheme: function(preference) {
            if (preference !== 'light' && preference !== 'dark' && preference !== 'system') {
                preference = 'system';
            }
            try {
                localStorage.setItem('soundmix_theme', preference);
            } catch (e) {}

            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var isDark = preference === 'dark' || (preference === 'system' && prefersDark);
            var root = document.documentElement;

            if (isDark) {
                root.classList.add('dark');
                root.setAttribute('data-theme', 'dark');
            } else {
                root.classList.remove('dark');
                root.setAttribute('data-theme', 'light');
            }
            root.setAttribute('data-theme-preference', preference);

            // Update UI elements
            this.syncButtons(preference);

            window.dispatchEvent(new CustomEvent('themechanged', { detail: { theme: preference, isDark: isDark } }));
        },
        syncButtons: function(current) {
            current = current || this.getPreference();
            document.querySelectorAll('[data-theme-option]').forEach(function(btn) {
                var opt = btn.getAttribute('data-theme-option');
                var isSelected = opt === current;
                btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                if (isSelected) {
                    btn.classList.add('theme-option-active');
                } else {
                    btn.classList.remove('theme-option-active');
                }
            });
        },
        initListener: function() {
            if (!window.matchMedia) return;
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            var self = this;
            var handler = function(e) {
                var pref = self.getPreference();
                if (pref === 'system') {
                    var root = document.documentElement;
                    if (e.matches) {
                        root.classList.add('dark');
                        root.setAttribute('data-theme', 'dark');
                    } else {
                        root.classList.remove('dark');
                        root.setAttribute('data-theme', 'light');
                    }
                }
            };
            if (mq.addEventListener) {
                mq.addEventListener('change', handler);
            } else if (mq.addListener) {
                mq.addListener(handler);
            }
        }
    };
    window.SoundMixTheme.initListener();

    document.addEventListener('DOMContentLoaded', function() {
        window.SoundMixTheme.syncButtons();
    });
</script>
