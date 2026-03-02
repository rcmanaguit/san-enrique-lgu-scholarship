<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Global Search';
$initialQuery = trim((string) ($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $initialQuery = mb_substr($initialQuery, 0, 120);
} else {
    $initialQuery = substr($initialQuery, 0, 120);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>Global Search</h1>
    <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
    </div>
</div>

<div class="card card-soft shadow-sm mb-3">
    <div class="card-body">
        <form id="globalSearchForm" class="row g-2" autocomplete="off">
            <div class="col-12 col-lg-8">
                <label for="globalSearchInput" class="form-label">Search Across Records</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                    <input
                        type="text"
                        class="form-control"
                        id="globalSearchInput"
                        name="q"
                        placeholder="Application no, name, school, reference no, QR token, announcement..."
                        value="<?= e($initialQuery) ?>"
                        maxlength="120"
                    >
                    <button type="button" class="btn btn-outline-secondary" id="globalSearchClearBtn">Clear</button>
                </div>
                <div class="small text-muted mt-1">Live search starts at 2 characters.</div>
            </div>
            <div class="col-12 col-lg-4 d-flex align-items-end">
                <div class="small text-muted" id="globalSearchStatus">Type to search.</div>
            </div>
        </form>
    </div>
</div>

<div id="globalSearchResults" class="row g-3"></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('globalSearchInput');
        const clearBtn = document.getElementById('globalSearchClearBtn');
        const status = document.getElementById('globalSearchStatus');
        const results = document.getElementById('globalSearchResults');
        const endpoint = 'global-search-api.php';
        const initialQuery = <?= json_encode($initialQuery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let controller = null;
        let debounceTimer = null;

        function setStatus(message) {
            if (status) {
                status.textContent = message;
            }
        }

        function escapeHtml(value) {
            const temp = document.createElement('div');
            temp.textContent = value == null ? '' : String(value);
            return temp.innerHTML;
        }

        function renderEmpty(message) {
            results.innerHTML = '<div class="col-12"><div class="card card-soft"><div class="card-body text-muted">' + escapeHtml(message) + '</div></div></div>';
        }

        function renderData(payload) {
            const sections = Array.isArray(payload.sections) ? payload.sections : [];
            if (!sections.length) {
                renderEmpty('No matching records found.');
                return;
            }

            const cards = sections.map(function (section) {
                const items = Array.isArray(section.items) ? section.items : [];
                const list = items.map(function (item) {
                    const title = escapeHtml(item.title || '-');
                    const subtitle = escapeHtml(item.subtitle || '');
                    const meta = escapeHtml(item.meta || '');
                    const url = escapeHtml(item.url || '#');
                    return (
                        '<a href="' + url + '" class="list-group-item list-group-item-action">' +
                            '<div class="fw-semibold">' + title + '</div>' +
                            (subtitle ? '<div class="small text-muted">' + subtitle + '</div>' : '') +
                            (meta ? '<div class="small mt-1">' + meta + '</div>' : '') +
                        '</a>'
                    );
                }).join('');

                return (
                    '<div class="col-12 col-lg-6">' +
                        '<div class="card card-soft shadow-sm h-100">' +
                            '<div class="card-body">' +
                                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                                    '<h2 class="h6 m-0">' + escapeHtml(section.label || 'Results') + '</h2>' +
                                    '<span class="badge text-bg-primary">' + Number(section.count || 0) + '</span>' +
                                '</div>' +
                                '<div class="list-group list-group-flush">' + list + '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
            }).join('');

            results.innerHTML = cards;
        }

        async function fetchResults(query) {
            if (controller) {
                controller.abort();
            }

            if (!query || query.trim().length < 2) {
                renderEmpty('Type at least 2 characters to start searching.');
                setStatus('Waiting for input...');
                return;
            }

            controller = new AbortController();
            setStatus('Searching...');

            try {
                const response = await fetch(endpoint + '?q=' + encodeURIComponent(query.trim()), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal
                });

                if (!response.ok) {
                    throw new Error('Search request failed.');
                }

                const payload = await response.json();
                renderData(payload);
                const total = Number(payload.total || 0);
                setStatus(total > 0 ? ('Found ' + total + ' result(s).') : 'No matches found.');
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                renderEmpty('Unable to load results. Please try again.');
                setStatus('Search failed.');
            }
        }

        function queueSearch() {
            const query = input ? input.value : '';
            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(function () {
                fetchResults(query);
            }, 260);
        }

        if (input) {
            input.addEventListener('input', queueSearch);
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (input) {
                    input.value = '';
                    input.focus();
                }
                fetchResults('');
            });
        }

        if (initialQuery && initialQuery.length >= 2) {
            fetchResults(initialQuery);
        } else {
            renderEmpty('Type at least 2 characters to start searching.');
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
