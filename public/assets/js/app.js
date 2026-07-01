/*
 * app.js — progressive table enhancement for the IT Helpdesk system.
 *
 * Any <table class="table" data-enhance> rendered by the server is upgraded in
 * place with three client-side features, with no extra requests:
 *   1. Live search ...... a debounced text box filters rows across all cells.
 *   2. Column sort ...... clickable headers sort rows (text, number or date);
 *                         columns flagged data-no-sort (e.g. Actions) opt out.
 *   3. Pagination ....... rows are sliced into pages (default 10, data-page-size
 *                         overrides) with a Prev / page / Next pager.
 *
 * Everything degrades gracefully: with JS off the server still renders the full
 * table (and its own pagination). All visible strings come from window.APP_I18N,
 * populated from lang/ via t() in the footer — no hardcoded UI text here.
 *
 * Sorting is direction-aware for RTL/LTR only in icon placement; comparison is
 * value based. Date cells are detected from a YYYY/MM/DD[ HH:MM] shape and
 * compared as timestamps so they order chronologically, not lexically.
 */
(function () {
    'use strict';

    var I18N = window.APP_I18N || {};

    /** Translate with {placeholder} substitution, mirroring PHP t(). */
    function t(key, params) {
        var s = I18N[key] != null ? String(I18N[key]) : key;
        if (params) {
            Object.keys(params).forEach(function (k) {
                s = s.replace('{' + k + '}', params[k]);
            });
        }
        return s;
    }

    /** Normalised, lower-cased text content of a cell for searching/sorting. */
    function cellText(td) {
        return (td.textContent || '').replace(/\s+/g, ' ').trim();
    }

    var DATE_RE = /^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2}))?/;

    /**
     * Comparable value for a cell: a timestamp for dates, a float for plain
     * numbers, otherwise the lower-cased string. Keeps mixed columns stable.
     */
    function sortValue(text) {
        var m = DATE_RE.exec(text);
        if (m) {
            return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0)).getTime();
        }
        // Numbers possibly carrying separators / a unit suffix (e.g. "44 ساعة").
        var num = text.replace(/[, ]+/g, '').match(/^-?\d+(?:\.\d+)?/);
        if (num && num[0].length === text.replace(/[, ]+/g, '').length) {
            return parseFloat(num[0]);
        }
        return text.toLowerCase();
    }

    function debounce(fn, wait) {
        var timer;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    function enhance(table) {
        var tbody = table.tBodies[0];
        if (!tbody) { return; }

        var allRows = Array.prototype.slice.call(tbody.rows);
        if (allRows.length === 0) { return; }

        var headers = Array.prototype.slice.call(table.tHead ? table.tHead.rows[0].cells : []);
        var pageSize = parseInt(table.getAttribute('data-page-size'), 10) || 10;
        var rtl = document.documentElement.getAttribute('dir') === 'rtl';

        var state = { query: '', sortCol: -1, sortDir: 1, page: 1, filtered: allRows };

        // ---- Build the surrounding chrome (toolbar + footer) ----------------
        var wrap = table.closest('.table-wrap') || table;
        var shell = document.createElement('div');
        shell.className = 'data-table';
        wrap.parentNode.insertBefore(shell, wrap);

        var toolbar = document.createElement('div');
        toolbar.className = 'data-table__toolbar';

        var search = document.createElement('div');
        search.className = 'data-table__search';
        search.innerHTML =
            '<svg class="data-table__search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"' +
            ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
        var input = document.createElement('input');
        input.type = 'search';
        input.className = 'data-table__search-input';
        input.placeholder = t('table_search_placeholder');
        input.setAttribute('aria-label', t('table_search_placeholder'));
        search.appendChild(input);

        var count = document.createElement('span');
        count.className = 'data-table__count';

        toolbar.appendChild(search);
        toolbar.appendChild(count);

        var footer = document.createElement('div');
        footer.className = 'data-table__footer';
        var info = document.createElement('span');
        info.className = 'data-table__info';
        var pager = document.createElement('div');
        pager.className = 'data-table__pager';
        footer.appendChild(info);
        footer.appendChild(pager);

        // "No matches" row, shown only while a search hides every row.
        var emptyRow = document.createElement('tr');
        emptyRow.className = 'data-table__empty';
        var emptyCell = document.createElement('td');
        emptyCell.colSpan = headers.length || 1;
        emptyCell.textContent = t('table_no_matches');
        emptyRow.appendChild(emptyCell);
        emptyRow.hidden = true;
        tbody.appendChild(emptyRow);

        shell.appendChild(toolbar);
        shell.appendChild(wrap);
        shell.appendChild(footer);

        // ---- Sorting headers ------------------------------------------------
        headers.forEach(function (th, idx) {
            if (th.hasAttribute('data-no-sort')) { return; }
            th.classList.add('is-sortable');
            th.tabIndex = 0;
            th.setAttribute('role', 'button');
            var indicator = document.createElement('span');
            indicator.className = 'data-table__sort-icon';
            indicator.setAttribute('aria-hidden', 'true');
            th.appendChild(indicator);

            function activate() { toggleSort(idx, th); }
            th.addEventListener('click', activate);
            th.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(); }
            });
        });

        function toggleSort(idx, th) {
            if (state.sortCol === idx) {
                state.sortDir = -state.sortDir;
            } else {
                state.sortCol = idx;
                state.sortDir = 1;
            }
            headers.forEach(function (h) {
                h.classList.remove('is-sorted-asc', 'is-sorted-desc');
                h.removeAttribute('aria-sort');
            });
            th.classList.add(state.sortDir === 1 ? 'is-sorted-asc' : 'is-sorted-desc');
            th.setAttribute('aria-sort', state.sortDir === 1 ? 'ascending' : 'descending');

            allRows.sort(function (a, b) {
                var va = sortValue(cellText(a.cells[idx]));
                var vb = sortValue(cellText(b.cells[idx]));
                if (va < vb) { return -1 * state.sortDir; }
                if (va > vb) { return 1 * state.sortDir; }
                return 0;
            });
            allRows.forEach(function (r) { tbody.insertBefore(r, emptyRow); });
            state.page = 1;
            render();
        }

        // ---- Search ---------------------------------------------------------
        input.addEventListener('input', debounce(function () {
            state.query = input.value.toLowerCase().trim();
            state.page = 1;
            render();
        }, 150));

        // ---- Pagination controls -------------------------------------------
        function pagerButton(label, targetPage, disabled, current) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'data-table__page-btn';
            if (current) { b.classList.add('is-current'); b.setAttribute('aria-current', 'page'); }
            b.textContent = label;
            b.disabled = !!disabled;
            if (!disabled && !current) {
                b.addEventListener('click', function () { state.page = targetPage; render(); });
            }
            return b;
        }

        function buildPager(totalPages) {
            pager.innerHTML = '';
            if (totalPages <= 1) { return; }
            var prevArrow = rtl ? '›' : '‹';
            var nextArrow = rtl ? '‹' : '›';
            pager.appendChild(pagerButton(prevArrow, state.page - 1, state.page <= 1, false));

            // Window of page numbers around the current page (max 5).
            var start = Math.max(1, state.page - 2);
            var end = Math.min(totalPages, start + 4);
            start = Math.max(1, end - 4);
            for (var p = start; p <= end; p++) {
                pager.appendChild(pagerButton(String(p), p, false, p === state.page));
            }
            pager.appendChild(pagerButton(nextArrow, state.page + 1, state.page >= totalPages, false));
        }

        // ---- Render (filter -> paginate -> paint) ---------------------------
        function render() {
            // Filter.
            state.filtered = state.query
                ? allRows.filter(function (r) { return cellText(r).toLowerCase().indexOf(state.query) !== -1; })
                : allRows;

            var total = state.filtered.length;
            var totalPages = Math.max(1, Math.ceil(total / pageSize));
            if (state.page > totalPages) { state.page = totalPages; }
            var from = total === 0 ? 0 : (state.page - 1) * pageSize;
            var to = Math.min(from + pageSize, total);

            // Toggle visibility per row.
            allRows.forEach(function (r) { r.hidden = true; });
            for (var i = from; i < to; i++) { state.filtered[i].hidden = false; }

            emptyRow.hidden = total !== 0;

            count.textContent = t('table_showing', {
                from: total === 0 ? 0 : from + 1,
                to: to,
                total: total
            });
            info.textContent = total === 0 ? '' : t('table_showing', { from: from + 1, to: to, total: total });

            buildPager(totalPages);
        }

        render();
    }

    function init() {
        document.querySelectorAll('table.table[data-enhance]').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
