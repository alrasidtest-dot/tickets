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
</script>
</body>
</html>
