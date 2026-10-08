{{--
    Aplica o tema antes da primeira pintura, para que a tela não pisque de claro
    para escuro. Precisa vir no <head>, antes do @vite. As chaves abaixo são as
    mesmas de resources/js/nexusfield/theme.js.
--}}
<script>
    (() => {
        const storageKey = 'nexusfield:tema';
        const cookieName = 'nf_theme';
        const barColors = { light: '#f5f6f8', dark: '#000000' };
        const modes = new Set(['light', 'dark']);
        let mode = null;

        try {
            mode = window.localStorage.getItem(storageKey);
        } catch (error) {
            mode = null;
        }

        if (!modes.has(mode)) {
            const match = document.cookie.match(new RegExp('(?:^|; )' + cookieName + '=([^;]*)'));
            mode = match ? decodeURIComponent(match[1]) : null;
        }

        if (!modes.has(mode)) {
            mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        document.documentElement.dataset.bsTheme = mode;

        const bar = document.createElement('meta');
        bar.name = 'theme-color';
        bar.content = barColors[mode];
        document.head.appendChild(bar);
    })();
</script>
