(function () {
    'use strict';

    function normalise(value) {
        return String(value || '').toLowerCase().trim();
    }

    function setHidden(element, hidden) {
        if (!element) return;
        element.classList.toggle('is-hidden', !!hidden);
        element.hidden = !!hidden;
    }

    function initSurface(surface) {
        var mode = surface.getAttribute('data-work-filter-mode') || '';
        var input = surface.querySelector('[data-work-log-search]');
        var prompt = surface.querySelector('[data-work-search-prompt]');
        var empty = surface.querySelector('[data-work-search-empty]');
        var items = Array.prototype.slice.call(surface.querySelectorAll('[data-work-activity-item]'));
        var rows = Array.prototype.slice.call(surface.querySelectorAll('[data-work-team-row]'));
        var storageKey = surface.hasAttribute('data-work-team-time')
            ? 'foxdesk_work_team_search'
            : 'foxdesk_work_my_search';

        function itemMatches(item, query) {
            return normalise(item.getAttribute('data-work-search-text') || item.textContent).indexOf(query) !== -1;
        }

        function rowMatches(row, query) {
            var rowText = normalise(row.getAttribute('data-work-search-text') || row.textContent);
            var nestedItems = Array.prototype.slice.call(row.querySelectorAll('[data-work-activity-item]'));
            var nestedVisible = 0;

            nestedItems.forEach(function (item) {
                var match = itemMatches(item, query);
                setHidden(item, !match);
                if (match) nestedVisible += 1;
            });

            return rowText.indexOf(query) !== -1 || nestedVisible > 0;
        }

        function applySearch() {
            if (mode !== 'search') {
                setHidden(prompt, true);
                setHidden(empty, true);
                return;
            }

            var query = normalise(input ? input.value : '');
            var hasQuery = query.length > 0;
            var visible = 0;

            setHidden(prompt, hasQuery);

            items.forEach(function (item) {
                var match = hasQuery && itemMatches(item, query);
                setHidden(item, !match);
                if (match) visible += 1;
            });

            rows.forEach(function (row) {
                var match = hasQuery && rowMatches(row, query);
                setHidden(row, !match);
                if (match) visible += 1;
            });

            setHidden(empty, !hasQuery || visible > 0);
        }

        if (!input) {
            applySearch();
            return;
        }

        try {
            input.value = localStorage.getItem(storageKey) || '';
        } catch (_) {}

        input.addEventListener('input', function () {
            try {
                localStorage.setItem(storageKey, input.value || '');
            } catch (_) {}
            applySearch();
        });

        applySearch();
    }

    function cssVar(name, fallback) {
        var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    function formatMinutes(minutes) {
        var total = Math.max(0, Math.round(Number(minutes) || 0));
        var hours = Math.floor(total / 60);
        var mins = total % 60;
        if (hours > 0) return hours + 'h ' + mins + 'min';
        return mins + ' min';
    }

    function initHoursChart() {
        var surface = document.querySelector('[data-work-hours-chart]');
        var canvas = surface ? surface.querySelector('[data-work-hours-chart-canvas]') : null;
        var payloadNode = surface ? surface.querySelector('[data-work-hours-chart-payload]') : null;
        var fallback = surface ? surface.querySelector('[data-work-hours-chart-fallback]') : null;

        function showFallback() {
            if (!surface) return;
            surface.classList.remove('is-chart-ready');
            surface.classList.add('is-chart-fallback');
            if (fallback) fallback.hidden = false;
        }

        function showCanvas() {
            if (!surface) return;
            surface.classList.add('is-chart-ready');
            surface.classList.remove('is-chart-fallback');
            if (fallback) fallback.hidden = true;
        }

        if (!surface || !canvas || !payloadNode || typeof window.Chart === 'undefined') {
            showFallback();
            return;
        }

        var payload;
        try {
            payload = JSON.parse(payloadNode.textContent || '{}');
        } catch (_) {
            showFallback();
            return;
        }

        var labels = Array.isArray(payload.labels) ? payload.labels : [];
        var datasets = Array.isArray(payload.datasets) ? payload.datasets : [];
        var fullLabels = Array.isArray(payload.fullLabels) ? payload.fullLabels : labels;
        var textColor = cssVar('--text-secondary', '#475569');
        var mutedColor = cssVar('--text-muted', '#64748b');
        var gridColor = cssVar('--border-light', 'rgba(148, 163, 184, 0.28)');

        var chartDatasets = datasets
            .filter(function (dataset) {
                return Array.isArray(dataset.data) && dataset.data.some(function (value) { return Number(value) > 0; });
            })
            .map(function (dataset) {
                return {
                    label: dataset.label || 'Agent',
                    data: dataset.data || [],
                    backgroundColor: dataset.backgroundColor || '#3b5bdb',
                    borderColor: dataset.borderColor || dataset.backgroundColor || '#3b5bdb',
                    borderWidth: 0,
                    borderRadius: 5,
                    borderSkipped: false,
                    barPercentage: 0.72,
                    categoryPercentage: 0.74,
                    maxBarThickness: 42
                };
            });

        if (!labels.length || !chartDatasets.length) {
            showFallback();
            return;
        }

        if (surface._foxdeskWorkChart) {
            surface._foxdeskWorkChart.destroy();
        }

        surface._foxdeskWorkChart = new window.Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: chartDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        align: 'start',
                        labels: {
                            color: textColor,
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            boxWidth: 9,
                            boxHeight: 9,
                            padding: 16,
                            font: {
                                size: 12,
                                weight: '700'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                var index = items && items[0] ? items[0].dataIndex : 0;
                                return fullLabels[index] || labels[index] || '';
                            },
                            label: function (item) {
                                return item.dataset.label + ': ' + formatMinutes(item.raw);
                            },
                            footer: function (items) {
                                var total = (items || []).reduce(function (sum, item) {
                                    return sum + (Number(item.raw) || 0);
                                }, 0);
                                return (payload.totalLabel || 'Total') + ': ' + formatMinutes(total);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: mutedColor,
                            maxRotation: 0,
                            autoSkip: true,
                            autoSkipPadding: 14,
                            font: {
                                size: 11,
                                weight: '700'
                            }
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        border: {
                            display: false
                        },
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: mutedColor,
                            precision: 0,
                            callback: function (value) {
                                return Math.round(Number(value) / 60) + 'h';
                            },
                            font: {
                                size: 11,
                                weight: '700'
                            }
                        }
                    }
                }
            }
        });
        showCanvas();
    }

    function init() {
        document.querySelectorAll('[data-work-log-surface]').forEach(initSurface);
        initHoursChart();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
