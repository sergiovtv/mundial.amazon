(() => {
    const endpoint = '/api/photo-reaction.php';

    const searchPanel = document.querySelector('#property-search');
    const discovery = document.querySelector('.visual-discovery');

    function setHomeMode(mode) {
        ['choice', 'search', 'discovery'].forEach(name => {
            document.body.classList.toggle(`home-mode-${name}`, name === mode);
        });
        searchPanel?.classList.toggle('is-open', mode === 'search');

        const target = mode === 'search'
            ? searchPanel
            : mode === 'discovery' ? discovery : document.querySelector('.home-choice');
        target?.classList.remove('home-mode-enter');
        window.requestAnimationFrame(() => target?.classList.add('home-mode-enter'));
        window.setTimeout(() => target?.scrollIntoView({behavior: 'smooth', block: mode === 'choice' ? 'center' : 'start'}), 40);
    }

    document.querySelectorAll('[data-home-mode]').forEach(control => {
        control.addEventListener('click', () => setHomeMode(control.dataset.homeMode));
    });

    function tileMarkup(photo) {
        return `
            <a class="visual-tile-link" href="${escapeAttribute(photo.propertyUrl)}" aria-label="${escapeAttribute(photo.ariaLabel)}">
                <img src="${escapeAttribute(photo.imageUrl)}" alt="${escapeAttribute(photo.alt)}" loading="lazy" decoding="async">
                <span class="visual-caption">
                    <small>${escapeHtml(photo.type)}</small>
                    <strong>${escapeHtml(photo.neighborhood)}</strong>
                </span>
            </a>
            <div class="photo-reactions" aria-label="Avalie esta foto">
                <button type="button" class="photo-reaction photo-like" data-reaction="1" aria-label="Gostei desta foto" title="Gostei desta foto">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 22H3a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h4m0 11V11l4-9a3 3 0 0 1 3 3v4h5.3a2 2 0 0 1 2 2.4l-1.5 8A2 2 0 0 1 17.8 21H7Z"/></svg>
                </button>
                <button type="button" class="photo-reaction photo-dislike" data-reaction="-1" aria-label="Não gostei desta foto" title="Não gostei desta foto">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2H3a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h4m0-11v11l4 9a3 3 0 0 0 3-3v-4h5.3a2 2 0 0 0 2-2.4l-1.5-8A2 2 0 0 0 17.8 3H7Z"/></svg>
                </button>
            </div>`;
    }

    function escapeHtml(value) {
        const element = document.createElement('span');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }

    function escapeAttribute(value) {
        return escapeHtml(value).replace(/`/g, '&#96;');
    }

    function replaceRejectedPhoto(tile, gallery) {
        const reserve = gallery._photoReserves.shift();
        tile.classList.add('is-leaving');
        window.setTimeout(() => {
            if (!reserve) {
                tile.remove();
                return;
            }
            tile.dataset.photoId = String(reserve.photoId);
            tile.dataset.photoSource = reserve.source;
            tile.innerHTML = tileMarkup(reserve);
            tile.classList.remove('is-leaving', 'is-liked', 'is-voted');
            tile.classList.add('is-entering');
            window.setTimeout(() => tile.classList.remove('is-entering'), 350);
        }, 260);
    }

    async function react(button) {
        const tile = button.closest('.visual-tile');
        const gallery = button.closest('[data-photo-gallery]');
        if (!tile || !gallery || tile.classList.contains('is-saving')) return;

        const reaction = Number(button.dataset.reaction);
        tile.classList.add('is-saving');
        button.setAttribute('aria-pressed', 'true');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({
                    source: tile.dataset.photoSource,
                    photoId: Number(tile.dataset.photoId),
                    reaction,
                }),
            });
            if (!response.ok) throw new Error('Não foi possível registrar a avaliação.');

            if (reaction === -1) {
                replaceRejectedPhoto(tile, gallery);
            } else {
                tile.classList.add('is-liked', 'is-voted');
                tile.querySelectorAll('.photo-reaction').forEach(control => {
                    control.disabled = true;
                });
            }
        } catch (error) {
            button.removeAttribute('aria-pressed');
            tile.classList.add('has-reaction-error');
            window.setTimeout(() => tile.classList.remove('has-reaction-error'), 1800);
        } finally {
            tile.classList.remove('is-saving');
        }
    }

    document.querySelectorAll('[data-photo-gallery]').forEach(gallery => {
        const data = gallery.querySelector('.visual-reserves');
        try {
            gallery._photoReserves = JSON.parse(data?.textContent || '[]');
        } catch (_) {
            gallery._photoReserves = [];
        }
    });

    document.addEventListener('click', event => {
        const button = event.target.closest('.photo-reaction');
        if (button) react(button);
    });
})();
