/* Menu de variáveis ativado por "/" em textareas marcadas com data-variables.
   Digite "/" pra abrir, continue digitando pra filtrar, Enter ou clique pra
   inserir. O token inserido (ex: {{usuario}}) é substituído pelo valor real
   só na hora de enviar (ver AutomationEngine::applyVariables no backend). */
(function () {
    var VARIABLES = [
        { token: '{{usuario}}', label: 'Nome de usuário de quem comentou', hint: 'vira @usuario na hora de enviar' },
    ];

    function setup(textarea) {
        var menu = null;
        var triggerPos = -1;

        function closeMenu() {
            if (menu) {
                menu.remove();
                menu = null;
            }
            triggerPos = -1;
        }

        function currentFilter() {
            if (triggerPos === -1) return '';
            return textarea.value.slice(triggerPos + 1, textarea.selectionStart);
        }

        function matchingVariables() {
            var filter = currentFilter().toLowerCase();
            return VARIABLES.filter(function (v) {
                return v.token.toLowerCase().indexOf(filter) !== -1 || v.label.toLowerCase().indexOf(filter) !== -1;
            });
        }

        function positionMenu() {
            var rect = textarea.getBoundingClientRect();
            menu.style.left = (rect.left + window.scrollX) + 'px';
            menu.style.top = (rect.bottom + window.scrollY + 4) + 'px';
            menu.style.width = Math.min(Math.max(rect.width, 220), 360) + 'px';
        }

        function renderItems() {
            var items = matchingVariables();
            if (!items.length) {
                closeMenu();
                return;
            }
            menu.innerHTML = '';
            items.forEach(function (v, idx) {
                var row = document.createElement('div');
                row.className = 'variable-menu-item' + (idx === 0 ? ' active' : '');
                row.dataset.token = v.token;
                var strong = document.createElement('strong');
                strong.textContent = v.token;
                var span = document.createElement('span');
                span.textContent = v.hint;
                row.appendChild(strong);
                row.appendChild(span);
                row.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    insertToken(v.token);
                });
                menu.appendChild(row);
            });
        }

        function openMenu() {
            closeMenu();
            triggerPos = textarea.selectionStart - 1;
            menu = document.createElement('div');
            menu.className = 'variable-menu';
            document.body.appendChild(menu);
            renderItems();
            if (menu) positionMenu();
        }

        function insertToken(token) {
            var start = triggerPos;
            var end = textarea.selectionStart;
            var value = textarea.value;
            textarea.value = value.slice(0, start) + token + value.slice(end);
            var cursor = start + token.length;
            textarea.setSelectionRange(cursor, cursor);
            textarea.focus();
            closeMenu();
        }

        textarea.addEventListener('input', function () {
            var pos = textarea.selectionStart;
            var justTyped = textarea.value[pos - 1];

            if (justTyped === '/') {
                openMenu();
                return;
            }

            if (triggerPos !== -1) {
                var between = textarea.value.slice(triggerPos, pos);
                if (pos <= triggerPos || /\s/.test(between)) {
                    closeMenu();
                } else {
                    renderItems();
                    if (menu) positionMenu();
                }
            }
        });

        textarea.addEventListener('keydown', function (e) {
            if (!menu) return;
            if (e.key === 'Escape') {
                closeMenu();
            } else if (e.key === 'Enter') {
                var active = menu.querySelector('.variable-menu-item');
                if (active) {
                    e.preventDefault();
                    insertToken(active.dataset.token);
                }
            }
        });

        textarea.addEventListener('blur', function () {
            setTimeout(closeMenu, 150);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var nodes = document.querySelectorAll('textarea[data-variables]');
        for (var i = 0; i < nodes.length; i++) setup(nodes[i]);
    });
})();
