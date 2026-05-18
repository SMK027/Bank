/**
 * ToolBox — JavaScript utilitaire
 */
document.addEventListener('DOMContentLoaded', function () {

    /* ----- Navbar toggle (mobile) ----- */
    const navToggle = document.querySelector('.navbar-toggle');
    const navMenu = document.querySelector('.navbar-menu');
    if (navToggle && navMenu) {
        const closeNavMenu = function () {
            navMenu.classList.remove('open');
            navToggle.classList.remove('active');
            navToggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('nav-menu-open');
        };

        navToggle.addEventListener('click', function () {
            const isOpen = navMenu.classList.toggle('open');
            navToggle.classList.toggle('active', isOpen);
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            document.body.classList.toggle('nav-menu-open', isOpen);
        });
        // Fermer le menu au clic en dehors
        document.addEventListener('click', function (e) {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                closeNavMenu();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeNavMenu();
            }
        });
        navMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                closeNavMenu();
            });
        });
    }

    /* ----- Dropdown modération (navbar) ----- */
    const modToggle = document.getElementById('modToggle');
    const modDropdown = document.getElementById('modDropdown');
    if (modToggle && modDropdown) {
        modToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = modDropdown.classList.toggle('open');
            modToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!modDropdown.contains(e.target)) {
                modDropdown.classList.remove('open');
                modToggle.setAttribute('aria-expanded', 'false');
            }
        });
        // Fermer sur Échap
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                modDropdown.classList.remove('open');
                modToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* ----- Sidebar toggle (tablette) ----- */
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    /* ----- Password visibility toggle ----- */
    document.querySelectorAll('.btn-toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = this.closest('.password-wrapper').querySelector('input');
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
                this.classList.add('active');
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
                this.classList.remove('active');
            }
        });
    });

    /* ----- Fermer les alertes ----- */
    document.querySelectorAll('.alert-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const alert = this.closest('.alert');
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () { alert.remove(); }, 200);
        });
    });

    /* ----- Auto-dismiss des alertes (5s) ----- */
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (alert) {
        setTimeout(function () {
            const closeBtn = alert.querySelector('.alert-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });

    /* ----- Modal de confirmation ----- */
    document.querySelectorAll('[data-confirm]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            const message = this.dataset.confirm || 'Êtes-vous sûr ?';
            const overlay = document.getElementById('confirm-modal');
            if (overlay) {
                overlay.querySelector('.modal-message').textContent = message;
                overlay.dataset.action = this.href || this.dataset.action || '';
                overlay.classList.add('active');
            }
        });
    });

    const confirmModal = document.getElementById('confirm-modal');
    if (confirmModal) {
        confirmModal.querySelector('.btn-confirm')?.addEventListener('click', function () {
            const action = confirmModal.dataset.action;
            if (action) window.location.href = action;
            confirmModal.classList.remove('active');
        });
        confirmModal.querySelector('.btn-cancel')?.addEventListener('click', function () {
            confirmModal.classList.remove('active');
        });
        confirmModal.addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('active');
        });
    }

    /* ----- Toast notifications ----- */
    window.showToast = function (message, type) {

        type = type || 'info';
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(function () { toast.remove(); }, 300);
        }, 4000);
    };
});

/* ----- Collapse de sections (jQuery) ----- */
$(function () {
    // Toutes les cartes ayant un card-header + card-body, sauf celles marquées .card-static
    var $collapsible = $('.card:not(.card-static)').filter(function () {
        return $(this).children('.card-header').length > 0 &&
               $(this).children('.card-body').length > 0;
    });

    $collapsible.each(function (i) {
        var $card   = $(this);
        var $header = $card.children('.card-header');
        var $body   = $card.children('.card-body');
        var $footer = $card.children('.card-footer');

        // Clé localStorage unique par page + position
        var key = 'tbx_collapse_' + location.pathname.replace(/\//g, '-') + '_' + i;

        // Injecter le bouton chevron (une seule fois)
        if ($header.find('.card-collapse-btn').length === 0) {
            $header.append(
                '<button class="card-collapse-btn" type="button" ' +
                'title="Replier / D\u00e9plier" aria-expanded="true">' +
                '<i class="bi bi-chevron-up"></i></button>'
            );
        }
        $header.addClass('is-collapsible');
        var $btn = $header.find('.card-collapse-btn');

        function collapse(animate) {
            if (animate) {
                $body.slideUp(180);
                if ($footer.length) $footer.slideUp(180);
            } else {
                $body.hide();
                if ($footer.length) $footer.hide();
            }
            $btn.attr('aria-expanded', 'false').find('i').css('transform', 'rotate(180deg)');
            $header.addClass('card-header--collapsed');
        }

        function expand(animate) {
            if (animate) {
                $body.slideDown(180);
                if ($footer.length) $footer.slideDown(180);
            } else {
                $body.show();
                if ($footer.length) $footer.show();
            }
            $btn.attr('aria-expanded', 'true').find('i').css('transform', '');
            $header.removeClass('card-header--collapsed');
        }

        // Restaurer l'état sauvegardé (sans animation)
        if (localStorage.getItem(key) === '1') {
            collapse(false);
        }

        // Clic sur le header — ignorer les liens, formulaires et boutons d'action
        $header.on('click', function (e) {
            if ($(e.target).closest(
                'a, input, select, textarea, form, button:not(.card-collapse-btn)'
            ).length) return;

            if ($body.is(':hidden')) {
                expand(true);
                localStorage.setItem(key, '0');
            } else {
                collapse(true);
                localStorage.setItem(key, '1');
            }
        });
    });
});

/* ───────────────────────────────────────────────────────────
   Flatpickr — initialisation automatique des champs date/heure
   ─────────────────────────────────────────────────────────── */
window.initFlatpickrs = function () {
    if (typeof flatpickr === 'undefined') return;

    // Tous les inputs avec placeholder "jj/mm/aaaa hh:mm" → datetime picker
    document.querySelectorAll('input[placeholder="jj/mm/aaaa hh:mm"]').forEach(function (el) {
        if (el._flatpickr) return;
        flatpickr(el, {
            locale: 'fr',
            enableTime: true,
            time_24hr: true,
            dateFormat: 'd/m/Y H:i',
            allowInput: true,
            minuteIncrement: 1,
            disableMobile: true
        });
    });

    // Inputs avec placeholder "jj/mm/aaaa" → date picker (sans heure)
    document.querySelectorAll('input[placeholder="jj/mm/aaaa"]').forEach(function (el) {
        if (el._flatpickr) return;
        flatpickr(el, {
            locale: 'fr',
            dateFormat: 'd/m/Y',
            allowInput: true,
            minDate: 'today',
            disableMobile: true
        });
    });
};

document.addEventListener('DOMContentLoaded', function () {
    window.initFlatpickrs();
});
