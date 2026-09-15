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
        const folderChecks = tree.querySelectorAll('input.folder-check');
        folderChecks.forEach((cb) => {
            cb.addEventListener('change', function () {
                const li = this.closest('li');
                if (!li) return;
                const descendants = li.querySelectorAll('input[type="checkbox"]');
                descendants.forEach((d) => {
                    if (d === this) return;
                    if (this.checked) {
                        d.checked = true;
                        d.disabled = true;
                    } else {
                        d.disabled = false;
                    }
                });
            });
            // Reflect initial state (e.g. re-rendering after a save).
            if (cb.checked) {
                cb.dispatchEvent(new Event('change'));
            }
        });
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
