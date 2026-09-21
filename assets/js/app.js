// ---- Dashboard search: filters the folder/link tree client-side ----
(function () {
    const input = document.getElementById('tree-search');
    const root = document.getElementById('tree-root');
    if (!input || !root) return;

    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        const nodes = root.querySelectorAll('.tree-node');

        if (q === '') {
            nodes.forEach((li) => { li.style.display = ''; });
            root.querySelectorAll('.link-item').forEach((li) => { li.style.display = ''; });
            return;
        }

        // Hide everything, then reveal matches and their ancestors.
        nodes.forEach((li) => { li.style.display = 'none'; });
        root.querySelectorAll('.link-item').forEach((li) => { li.style.display = 'none'; });

        const reveal = (el) => {
            let node = el;
            while (node && node !== root) {
                node.style.display = '';
                const details = node.closest('details');
                if (details) details.open = true;
                node = node.parentElement ? node.parentElement.closest('.tree-node, .link-item') : null;
            }
        };

        root.querySelectorAll('.link-item').forEach((li) => {
            const text = li.textContent.toLowerCase();
            if (text.includes(q)) reveal(li);
        });
        nodes.forEach((li) => {
            const summary = li.querySelector(':scope > details > summary, :scope > .empty-folder');
            if (summary && summary.textContent.toLowerCase().includes(q)) reveal(li);
        });
    });
})();

// ---- Permission tree: checking a folder implies its whole subtree ----
(function () {
    const trees = document.querySelectorAll('.perm-tree');
    if (!trees.length) return;

    trees.forEach((tree) => {
        const boxes = Array.from(tree.querySelectorAll('input[type="checkbox"]'));

        // Remember what was explicitly granted (data-own), separately from
        // what is only *implied* by a checked parent folder.
        boxes.forEach((cb) => { cb.dataset.own = cb.checked ? '1' : '0'; });

        // Is any ancestor folder of this checkbox explicitly checked?
        function impliedByAncestor(cb) {
            let li = cb.closest('li');
            let parent = li && li.parentElement ? li.parentElement.closest('li') : null;
            while (parent) {
                const pc = parent.querySelector(':scope > label > input.folder-check');
                if (pc && pc.dataset.own === '1') return true;
                parent = parent.parentElement ? parent.parentElement.closest('li') : null;
            }
            return false;
        }

        function refresh() {
            boxes.forEach((cb) => {
                const implied = impliedByAncestor(cb);
                cb.disabled = implied;               // locked while a parent covers it
                cb.checked = implied || cb.dataset.own === '1';
            });
        }

        boxes.forEach((cb) => {
            cb.addEventListener('change', function () {
                if (!this.disabled) this.dataset.own = this.checked ? '1' : '0';
                refresh();
            });
        });

        refresh();
    });
})();

// ---- Confirm destructive actions ----
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.matches('[data-confirm]')) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    }
});

// ---- Keep-alive: real activity (clicks, typing, scrolling, searching) keeps
// the session open, even though it doesn't trigger a full page load. ----
(function () {
    if (!document.body || !document.body.hasAttribute('data-keepalive')) return;

    const PING_EVERY_MS = 4 * 60 * 1000;   // at most one ping per 4 minutes
    let lastActivity = Date.now();
    let lastPing = Date.now();
    let pinging = false;

    const markActive = () => { lastActivity = Date.now(); };
    ['click', 'keydown', 'scroll', 'touchstart', 'mousemove', 'input'].forEach((evt) =>
        document.addEventListener(evt, markActive, { passive: true, capture: true })
    );

    function ping() {
        if (pinging) return;
        pinging = true;
        lastPing = Date.now();
        fetch('/keepalive.php', { method: 'POST', credentials: 'same-origin', cache: 'no-store' })
            .then((res) => {
                if (res.status === 401) window.location.href = '/login.php?timeout=1';
            })
            .catch(() => { /* offline / server blip: try again next tick */ })
            .finally(() => { pinging = false; });
    }

    setInterval(function () {
        // Only ping if the person actually did something since the last ping.
        if (lastActivity > lastPing) ping();
    }, 60 * 1000);

    // Coming back to the tab (e.g. after working in a link opened in another
    // tab) counts as activity and validates the session straight away.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            markActive();
            if (Date.now() - lastPing > 30 * 1000) ping();
        }
    });
})();
