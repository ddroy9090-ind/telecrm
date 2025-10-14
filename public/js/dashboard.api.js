(function () {
    'use strict';

    if (!window.HH_DASHBOARD_BOOT) {
        return;
    }

    const config = window.HH_DASHBOARD_BOOT;
    const endpoints = config.endpoints || {};
    const state = {
        range: config.defaultRange || 'last_30_days',
        pendingSearch: null,
        searchVisible: false,
    };

    const els = {
        rangeSelect: document.querySelector('[data-dashboard-range]'),
        searchInput: document.querySelector('[data-dashboard-search]'),
        statValues: document.querySelectorAll('[data-stat-value]'),
        leadSourceList: document.querySelector('[data-lead-source-list]'),
        topAgents: document.querySelector('[data-top-agents]'),
        recentActivities: document.querySelector('[data-recent-activities]'),
        heatmapAverage: document.querySelector('[data-heatmap-average]'),
        heatmapGrid: document.querySelector('[data-heatmap-grid]'),
        performanceMetrics: document.querySelectorAll('[data-performance-metric]'),
        inventoryTotalValue: document.querySelector('[data-inventory-total-value]'),
        inventoryAvgSold: document.querySelector('[data-inventory-avg-sold]'),
        inventoryTable: document.querySelector('[data-inventory-table]'),
    };

    const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    const leadSourcePalette = ['#00B894', '#0984E3', '#6C5CE7', '#E17055', '#FDCB6E', '#2D3436'];

    function formatNumber(value) {
        return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value);
    }

    function formatPercent(value) {
        if (value === null || value === undefined || Number.isNaN(value)) {
            return '--';
        }
        return `${value.toFixed(2).replace(/\.00$/, '')}%`;
    }

    function formatCurrency(value) {
        if (value === null || value === undefined || Number.isNaN(value)) {
            return '--';
        }
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'AED',
            maximumFractionDigits: value >= 1000 ? 0 : 2,
        }).format(value);
    }

    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then((response) => {
            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }
            return response.json();
        });
    }

    function updateLeadCounters(payload) {
        if (!payload || !payload.data) {
            return;
        }

        const data = payload.data;

        Object.keys(data).forEach((key) => {
            const metric = data[key];
            const valueEl = document.querySelector(`[data-stat-value="${key.replace(/_/g, '-')}"]`);
            if (valueEl) {
                valueEl.textContent = typeof metric.value === 'number' ? formatNumber(metric.value) : '--';
            }

            const changeContainer = document.querySelector(`[data-stat-change="${key.replace(/_/g, '-')}"]`);
            if (changeContainer) {
                const changeValue = changeContainer.querySelector(`[data-stat-change-value="${key.replace(/_/g, '-')}"]`);
                const changeLabel = changeContainer.querySelector(`[data-stat-change-label="${key.replace(/_/g, '-')}"]`);
                if (changeValue) {
                    changeValue.textContent = metric.change_pct !== null && metric.change_pct !== undefined
                        ? `${metric.change_pct > 0 ? '+' : ''}${metric.change_pct.toFixed(2)}%`
                        : '--';
                }
                if (changeLabel) {
                    changeLabel.textContent = 'vs previous period';
                }
            }
        });
    }

    function updateLeadSources(payload) {
        if (!els.leadSourceList) {
            return;
        }

        els.leadSourceList.innerHTML = '';

        const data = (payload && payload.data) || [];
        if (!data.length) {
            const item = document.createElement('li');
            item.className = 'text-muted';
            item.textContent = 'No lead sources in this range.';
            els.leadSourceList.appendChild(item);
            return;
        }

        data.forEach((entry, index) => {
            const item = document.createElement('li');
            const dot = document.createElement('span');
            dot.className = 'dot';
            dot.style.backgroundColor = leadSourcePalette[index % leadSourcePalette.length];
            const strong = document.createElement('strong');
            strong.textContent = `${formatNumber(entry.count)} (${entry.percentage.toFixed(2)}%)`;
            item.appendChild(dot);
            item.append(` ${entry.source}`);
            item.appendChild(document.createTextNode(' '));
            item.appendChild(strong);
            els.leadSourceList.appendChild(item);
        });
    }

    function initialsFromName(name) {
        if (!name) {
            return '--';
        }
        const parts = name.trim().split(/\s+/);
        if (!parts.length) {
            return '--';
        }
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function updateTopAgents(payload) {
        if (!els.topAgents) {
            return;
        }

        els.topAgents.innerHTML = '';
        const data = (payload && payload.data) || [];
        if (!data.length) {
            const empty = document.createElement('div');
            empty.className = 'text-muted small';
            empty.textContent = 'No agent performance data found.';
            els.topAgents.appendChild(empty);
            return;
        }

        data.forEach((agent) => {
            const item = document.createElement('div');
            item.className = 'agent-item';

            const info = document.createElement('div');
            info.className = 'agent-info';

            const avatar = document.createElement('div');
            avatar.className = 'agent-avatar';
            avatar.textContent = initialsFromName(agent.agent_name);

            const details = document.createElement('div');
            details.className = 'agent-details';

            const nameEl = document.createElement('h6');
            nameEl.textContent = agent.agent_name || 'Unknown';

            const stats = document.createElement('div');
            stats.className = 'agent-stats';
            const closed = document.createElement('span');
            closed.textContent = `${formatNumber(agent.closed_leads)} closed`;
            const total = document.createElement('span');
            total.className = 'growth';
            total.textContent = `${formatNumber(agent.total_leads)} total`;
            stats.appendChild(closed);
            stats.appendChild(total);

            details.appendChild(nameEl);
            details.appendChild(stats);

            info.appendChild(avatar);
            info.appendChild(details);

            const value = document.createElement('div');
            value.className = 'agent-value';
            const conversion = document.createElement('h6');
            conversion.textContent = formatPercent(agent.conversion_rate);
            const label = document.createElement('span');
            label.textContent = 'Conversion Rate';
            value.appendChild(conversion);
            value.appendChild(label);

            item.appendChild(info);
            item.appendChild(value);
            els.topAgents.appendChild(item);
        });
    }

    function updateRecentActivities(payload) {
        if (!els.recentActivities) {
            return;
        }

        els.recentActivities.innerHTML = '';
        const data = (payload && payload.data) || [];
        if (!data.length) {
            const empty = document.createElement('div');
            empty.className = 'text-muted small';
            empty.textContent = 'No activities recorded yet.';
            els.recentActivities.appendChild(empty);
            return;
        }

        data.forEach((activity) => {
            const item = document.createElement('div');
            item.className = 'activity-item';

            const icon = document.createElement('div');
            icon.className = 'activity-icon';
            icon.innerHTML = '<i class="bi bi-activity"></i>';

            const content = document.createElement('div');
            content.className = 'activity-content';
            const title = document.createElement('p');
            title.textContent = activity.description || 'Activity logged';
            const meta = document.createElement('div');
            meta.className = 'activity-meta';

            const time = document.createElement('span');
            time.textContent = activity.relative_time || '';
            meta.appendChild(time);

            if (activity.activity_type) {
                const type = document.createElement('span');
                type.className = 'amount';
                type.textContent = activity.activity_type;
                meta.appendChild(type);
            }

            content.appendChild(title);
            content.appendChild(meta);

            item.appendChild(icon);
            item.appendChild(content);

            els.recentActivities.appendChild(item);
        });
    }

    function interpolateColor(startHex, endHex, ratio) {
        const start = parseInt(startHex.replace('#', ''), 16);
        const end = parseInt(endHex.replace('#', ''), 16);

        const sr = (start >> 16) & 0xff;
        const sg = (start >> 8) & 0xff;
        const sb = start & 0xff;

        const er = (end >> 16) & 0xff;
        const eg = (end >> 8) & 0xff;
        const eb = end & 0xff;

        const r = Math.round(sr + (er - sr) * ratio);
        const g = Math.round(sg + (eg - sg) * ratio);
        const b = Math.round(sb + (eb - sb) * ratio);

        return `rgb(${r}, ${g}, ${b})`;
    }

    function renderHeatmap(payload) {
        if (!els.heatmapGrid) {
            return;
        }

        const data = (payload && payload.data) || {};
        const grid = data.grid || [];
        const max = data.max || 0;

        els.heatmapGrid.innerHTML = '';
        const table = document.createElement('table');
        table.className = 'table table-borderless mb-0';

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        const blank = document.createElement('th');
        blank.textContent = '';
        headerRow.appendChild(blank);

        for (let hour = 0; hour < 24; hour += 1) {
            const th = document.createElement('th');
            th.textContent = hour;
            headerRow.appendChild(th);
        }
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        for (let dayIndex = 0; dayIndex < dayLabels.length; dayIndex += 1) {
            const row = document.createElement('tr');
            const dayCell = document.createElement('th');
            dayCell.textContent = dayLabels[dayIndex];
            row.appendChild(dayCell);

            const dayData = grid[dayIndex] || [];
            for (let hour = 0; hour < 24; hour += 1) {
                const cell = document.createElement('td');
                const value = dayData[hour] || 0;
                const ratio = max > 0 ? value / max : 0;
                cell.style.backgroundColor = interpolateColor('#E8F9F2', '#00B894', ratio);
                cell.title = `${dayLabels[dayIndex]} ${hour}:00 - ${value} activities`;
                row.appendChild(cell);
            }
            tbody.appendChild(row);
        }
        table.appendChild(tbody);
        els.heatmapGrid.appendChild(table);

        if (els.heatmapAverage) {
            const avg = data.average_fill_pct !== undefined ? data.average_fill_pct : null;
            els.heatmapAverage.textContent = avg !== null ? `${avg.toFixed(2)}%` : '--';
        }
    }

    function updatePerformance(payload) {
        const metrics = (payload && payload.data) || {};
        els.performanceMetrics.forEach((element) => {
            const key = element.getAttribute('data-performance-metric');
            if (!key || !metrics[key]) {
                return;
            }
            const metric = metrics[key];
            const valueEl = element.querySelector(`[data-metric-value="${key}"]`);
            if (valueEl) {
                if (metric.value === null || metric.value === undefined) {
                    valueEl.textContent = '--';
                } else if (metric.unit === 'pct') {
                    valueEl.textContent = formatPercent(Number(metric.value));
                } else if (metric.unit === 'hours') {
                    valueEl.textContent = `${Number(metric.value).toFixed(2)}h`;
                } else if (metric.unit === 'days') {
                    valueEl.textContent = `${Number(metric.value).toFixed(2)}d`;
                } else {
                    valueEl.textContent = metric.value;
                }
            }

            const bar = element.querySelector(`[data-metric-bar="${key}"]`);
            if (bar) {
                if (metric.value === null || metric.value === undefined || metric.unit === 'hours' || metric.unit === 'days') {
                    bar.style.width = '0%';
                } else {
                    const width = Math.max(0, Math.min(100, Number(metric.value)));
                    bar.style.width = `${width}%`;
                }
            }

            const statusEl = element.querySelector(`[data-metric-status="${key}"]`);
            if (statusEl) {
                if (metric.status === 'ok') {
                    statusEl.textContent = 'On Track';
                    statusEl.classList.remove('bg-danger', 'text-danger');
                    statusEl.classList.add('bg-success', 'text-success');
                } else if (metric.status === 'no_data') {
                    statusEl.textContent = 'No Data';
                    statusEl.classList.remove('bg-success', 'text-success');
                    statusEl.classList.add('bg-secondary', 'text-muted');
                } else {
                    statusEl.textContent = metric.status || 'N/A';
                }
            }
        });
    }

    function updateInventory(payload) {
        const data = payload && payload.data ? payload.data : [];
        if (els.inventoryTable) {
            els.inventoryTable.innerHTML = '';
            if (!data.length) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 6;
                cell.className = 'text-center py-4 text-muted';
                cell.textContent = 'No inventory records available.';
                row.appendChild(cell);
                els.inventoryTable.appendChild(row);
            } else {
                data.forEach((project) => {
                    const row = document.createElement('tr');

                    const name = document.createElement('td');
                    name.textContent = project.project_name || 'Unnamed Project';
                    row.appendChild(name);

                    const total = document.createElement('td');
                    total.textContent = project.total_units !== null && project.total_units !== undefined
                        ? formatNumber(project.total_units)
                        : '--';
                    row.appendChild(total);

                    const soldCell = document.createElement('td');
                    const soldBadge = document.createElement('span');
                    soldBadge.className = 'badge-sold';
                    soldBadge.textContent = project.sold_units !== null && project.sold_units !== undefined
                        ? formatNumber(project.sold_units)
                        : '--';
                    soldCell.appendChild(soldBadge);
                    row.appendChild(soldCell);

                    const available = document.createElement('td');
                    available.textContent = project.available !== null && project.available !== undefined
                        ? formatNumber(project.available)
                        : '--';
                    row.appendChild(available);

                    const price = document.createElement('td');
                    price.textContent = project.avg_price !== null && project.avg_price !== undefined
                        ? formatCurrency(Number(project.avg_price))
                        : '--';
                    row.appendChild(price);

                    const progressCell = document.createElement('td');
                    const progressWrapper = document.createElement('div');
                    progressWrapper.className = 'progress';
                    const progressBar = document.createElement('div');
                    progressBar.className = 'progress-bar';
                    const pct = project.progress_pct !== null && project.progress_pct !== undefined
                        ? Math.max(0, Math.min(100, Number(project.progress_pct)))
                        : 0;
                    progressBar.style.width = `${pct}%`;
                    progressWrapper.appendChild(progressBar);
                    const progressText = document.createElement('span');
                    progressText.className = 'progress-text';
                    progressText.textContent = pct ? `${pct.toFixed(2)}%` : '--';
                    progressCell.appendChild(progressWrapper);
                    progressCell.appendChild(progressText);
                    row.appendChild(progressCell);

                    els.inventoryTable.appendChild(row);
                });
            }
        }

        if (els.inventoryTotalValue) {
            const totals = payload && payload.meta ? payload.meta.totals || {} : {};
            els.inventoryTotalValue.textContent = totals.inventory_value
                ? formatCurrency(Number(totals.inventory_value))
                : '--';
        }

        if (els.inventoryAvgSold) {
            const totals = payload && payload.meta ? payload.meta.totals || {} : {};
            els.inventoryAvgSold.textContent = totals.avg_sold_pct !== null && totals.avg_sold_pct !== undefined
                ? formatPercent(Number(totals.avg_sold_pct))
                : '--';
        }
    }

    function refreshDashboard() {
        if (endpoints.leadCounters) {
            fetchJson(`${endpoints.leadCounters}?range=${encodeURIComponent(state.range)}`)
                .then(updateLeadCounters)
                .catch(() => {});
        }

        if (endpoints.leadSources) {
            fetchJson(`${endpoints.leadSources}?range=${encodeURIComponent(state.range)}`)
                .then(updateLeadSources)
                .catch(() => {});
        }

        if (endpoints.topAgents) {
            fetchJson(`${endpoints.topAgents}?range=${encodeURIComponent(state.range)}&limit=5`)
                .then(updateTopAgents)
                .catch(() => {});
        }

        if (endpoints.recentActivities) {
            fetchJson(`${endpoints.recentActivities}?limit=20`)
                .then(updateRecentActivities)
                .catch(() => {});
        }

        if (endpoints.activityHeatmap) {
            fetchJson(`${endpoints.activityHeatmap}?range=${encodeURIComponent(state.range)}`)
                .then(renderHeatmap)
                .catch(() => {});
        }

        if (endpoints.performance) {
            fetchJson(`${endpoints.performance}?range=${encodeURIComponent(state.range)}`)
                .then(updatePerformance)
                .catch(() => {});
        }

        if (endpoints.inventory) {
            fetchJson(`${endpoints.inventory}?range=${encodeURIComponent(state.range)}`)
                .then(updateInventory)
                .catch(() => {});
        }
    }

    function setupRangeSelector() {
        if (!els.rangeSelect) {
            return;
        }
        els.rangeSelect.addEventListener('change', (event) => {
            const value = event.target.value;
            state.range = value || config.defaultRange || 'last_30_days';
            refreshDashboard();
        });
    }

    function setupSearch() {
        if (!els.searchInput || !endpoints.search) {
            return;
        }

        const wrapper = els.searchInput.closest('.right-search') || els.searchInput.parentElement;
        if (wrapper) {
            wrapper.classList.add('position-relative');
        }

        const results = document.createElement('div');
        results.className = 'list-group position-absolute top-100 start-0 w-100 shadow bg-white d-none';
        results.style.zIndex = '1050';
        if (wrapper) {
            wrapper.appendChild(results);
        }

        function hideResults() {
            if (!state.searchVisible) {
                return;
            }
            state.searchVisible = false;
            results.classList.add('d-none');
            results.innerHTML = '';
        }

        function renderGroup(title, items, formatter) {
            if (!items || !items.length) {
                return;
            }
            const header = document.createElement('div');
            header.className = 'list-group-item active';
            header.textContent = title;
            results.appendChild(header);

            items.forEach((item) => {
                const entry = document.createElement('div');
                entry.className = 'list-group-item list-group-item-action';
                entry.innerHTML = formatter(item);
                results.appendChild(entry);
            });
        }

        function performSearch(term) {
            if (!term) {
                hideResults();
                return;
            }
            fetchJson(`${endpoints.search}?q=${encodeURIComponent(term)}`)
                .then((payload) => {
                    const data = payload && payload.data ? payload.data : {};
                    results.innerHTML = '';
                    renderGroup('Leads', data.leads || [], (lead) => `${lead.name || 'Unnamed'} <small class="text-muted">${lead.email || ''}</small>`);
                    renderGroup('Projects', data.projects || [], (project) => `${project.project_name || project.property_title || 'Project'} <small class="text-muted">${project.location || ''}</small>`);
                    renderGroup('Agents', data.agents || [], (agent) => `${agent.name || 'Agent'} <small class="text-muted">${agent.email || ''}</small>`);
                    renderGroup('Activities', data.activities || [], (activity) => `${activity.description || 'Activity'} <small class="text-muted">${activity.created_at || ''}</small>`);
                    results.classList.toggle('d-none', results.children.length === 0);
                    state.searchVisible = results.children.length > 0;
                })
                .catch(() => {
                    hideResults();
                });
        }

        els.searchInput.addEventListener('input', (event) => {
            const term = event.target.value.trim();
            if (state.pendingSearch) {
                clearTimeout(state.pendingSearch);
            }
            state.pendingSearch = setTimeout(() => {
                performSearch(term);
            }, 300);
        });

        els.searchInput.addEventListener('focus', () => {
            if (results.children.length) {
                results.classList.remove('d-none');
                state.searchVisible = true;
            }
        });

        document.addEventListener('click', (event) => {
            if (!results.contains(event.target) && event.target !== els.searchInput) {
                hideResults();
            }
        });

        els.searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                hideResults();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupRangeSelector();
        setupSearch();
        refreshDashboard();
    });
}());
