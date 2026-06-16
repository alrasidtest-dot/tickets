<?php
/**
 * Shared layout — closing partial.
 *
 * Closes .content / .content-wrap / .layout-body opened by header.php and
 * sidebar.php, renders the page footer, then closes .layout and the document.
 * A tiny inline script (no external library) toggles the off-canvas sidebar
 * on mobile. All visible strings come from lang/ via t().
 */
$year = date('Y');
?>
            </main>

            <footer class="footer">
                <?php echo e(t('footer_copyright', ['year' => $year])); ?>
            </footer>
        </div><!-- /.content-wrap -->
    </div><!-- /.layout-body -->
</div><!-- /.layout -->

<script>
    // Off-canvas sidebar toggle for viewports under 768px.
    (function () {
        var btn = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');
        function close() { document.body.classList.remove('sidebar-open'); }
        if (btn) {
            btn.addEventListener('click', function () {
                document.body.classList.toggle('sidebar-open');
            });
        }
        if (overlay) { overlay.addEventListener('click', close); }
    })();

    // Modal dialogs: open via [data-modal-open="id"], close via the backdrop,
    // a [data-modal-close] control or the Escape key. Modals flagged with
    // data-open-on-load (server-driven edit / validation errors) open at once.
    (function () {
        function openModal(modal) {
            if (!modal) { return; }
            modal.classList.add('is-open');
            document.body.classList.add('modal-lock');
            var field = modal.querySelector('input:not([type=hidden]), select, textarea');
            if (field) { try { field.focus(); } catch (e) {} }
        }
        function closeModal(modal) {
            if (!modal) { return; }
            modal.classList.remove('is-open');
            if (!document.querySelector('.modal.is-open')) {
                document.body.classList.remove('modal-lock');
            }
        }

        document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                // Some triggers are links (no-JS fallback to a full page); with JS
                // we keep the user in place and open the modal instead.
                e.preventDefault();
                openModal(document.getElementById(btn.getAttribute('data-modal-open')));
            });
        });
        document.querySelectorAll('[data-modal-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeModal(el.closest('.modal'));
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeModal(document.querySelector('.modal.is-open')); }
        });
        document.querySelectorAll('.modal[data-open-on-load="1"]').forEach(openModal);
    })();
</script>
</body>
</html>
