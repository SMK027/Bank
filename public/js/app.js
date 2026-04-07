/**
 * ToolBox — JavaScript utilitaire
 */
document.addEventListener('DOMContentLoaded', function () {

    /* ----- Navbar toggle (mobile) ----- */
    const navToggle = document.querySelector('.navbar-toggle');
    const navMenu = document.querySelector('.navbar-menu');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            navMenu.classList.toggle('open');
        });
        // Fermer le menu au clic en dehors
        document.addEventListener('click', function (e) {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('open');
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
