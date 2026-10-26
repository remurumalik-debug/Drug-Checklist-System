// Drug Checklist Healthcare System - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {

    // Dark mode toggle
    var themeToggle = document.getElementById('themeToggle');
    var themeIconSun = document.getElementById('themeIconSun');
    var themeIconMoon = document.getElementById('themeIconMoon');
    function applyTheme(dark) {
        document.body.classList.toggle('dark', dark);
        if (themeIconSun) themeIconSun.style.display = dark ? 'none' : 'block';
        if (themeIconMoon) themeIconMoon.style.display = dark ? 'block' : 'none';
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    }
    var savedTheme = localStorage.getItem('theme');
    var isDark = savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches);
    applyTheme(isDark);
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            applyTheme(!document.body.classList.contains('dark'));
        });
    }

    // Sidebar toggle for mobile
    const sidebarToggleMobile = document.getElementById('sidebarToggleMobile');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggleMobile && sidebar) {
        sidebarToggleMobile.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // User dropdown toggle
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');
    if (userDropdownBtn && dropdownMenu) {
        userDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        document.addEventListener('click', function() {
            dropdownMenu.classList.remove('show');
        });
    }

    // Auto-dismiss toasts
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach(function(toast) {
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(40px)';
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 4000);
    });

    // Global search
    const globalSearch = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;
    const roleMap = { 'admin': 'admin', 'pharmacist': 'pharmacist', 'staff': 'staff' };

    if (globalSearch && searchResults) {
        globalSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            const userRole = roleMap[this.dataset.role] || 'pharmacist';
            const drugPage = userRole === 'staff' ? 'staff/inventory.php' : userRole + '/drugs.php';
            if (query.length < 2) {
                searchResults.classList.remove('show');
                return;
            }
            searchTimeout = setTimeout(function() {
                fetch(BASE_URL + 'api/search.php?q=' + encodeURIComponent(query))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        searchResults.innerHTML = '';
                        if (data.length === 0) {
                            searchResults.innerHTML = '<div class="search-result-item" style="justify-content:center;color:#9CA3AF;">No results found</div>';
                        } else {
                            data.forEach(function(drug) {
                                const div = document.createElement('a');
                                div.href = BASE_URL + drugPage + '?view=' + drug.id;
                                div.className = 'search-result-item';
                                div.innerHTML = '<div><div class="result-name">' + escapeHtml(drug.drug_name) + '</div><div class="result-detail">Batch: ' + escapeHtml(drug.batch_number) + ' | Qty: ' + drug.quantity + ' | ' + escapeHtml(drug.status) + '</div></div>';
                                searchResults.appendChild(div);
                            });
                        }
                        searchResults.classList.add('show');
                    })
                    .catch(function() {
                        searchResults.classList.remove('show');
                    });
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!globalSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('show');
            }
        });
    }

    // Modal functions
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('show');
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('show');
    };

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });

    // Confirmation dialogs
    window.confirmAction = function(message, callback) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay show';
        overlay.innerHTML = '<div class="modal" style="max-width:400px;"><div class="modal-body"><div class="confirm-dialog"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><h3>Confirm Action</h3><p>' + message + '</p><div class="confirm-actions"><button class="btn btn-secondary" id="confirmCancel">Cancel</button><button class="btn btn-danger" id="confirmOk">Confirm</button></div></div></div></div>';
        document.body.appendChild(overlay);

        document.getElementById('confirmCancel').addEventListener('click', function() {
            document.body.removeChild(overlay);
        });

        document.getElementById('confirmOk').addEventListener('click', function() {
            document.body.removeChild(overlay);
            if (typeof callback === 'function') callback();
        });

        overlay.addEventListener('click', function(e) {
            if (e.target === this) document.body.removeChild(overlay);
        });
    };

    // Form validation
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let valid = true;
            this.querySelectorAll('[required]').forEach(function(field) {
                const error = field.closest('.form-group').querySelector('.form-error');
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('error');
                    if (error) {
                        error.textContent = 'This field is required';
                        error.classList.add('show');
                    }
                } else {
                    field.classList.remove('error');
                    if (error) error.classList.remove('show');
                }
            });
            if (!valid) e.preventDefault();
        });
    });

    // Table row click
    document.querySelectorAll('.table-row-click').forEach(function(row) {
        row.addEventListener('click', function() {
            const href = this.dataset.href;
            if (href) window.location.href = href;
        });
    });

    // Prevent multiple form submissions
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<div class="spinner spinner-sm"></div> Processing...';
            }
        });
    });

    // Dashboard real-time auto-refresh
    initDashboardAutoRefresh();
    initNotificationBadgePolling();
    initCharts();
});

function initCharts() {
    if (typeof Chart === 'undefined') return;
    var isDark = document.body.classList.contains('dark');
    var textColor = isDark ? '#94A3B8' : '#6B7280';
    var gridColor = isDark ? '#334155' : '#F1F5F9';
    Chart.defaults.color = textColor;
    Chart.defaults.borderColor = gridColor;

    fetch(BASE_URL + 'api/chart_data.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            // Status donut (all roles)
            var statusEl = document.getElementById('chartStatus');
            if (statusEl && data.statusDistribution) {
                new Chart(statusEl, {
                    type: 'doughnut',
                    data: {
                        labels: data.statusDistribution.labels,
                        datasets: [{ data: data.statusDistribution.data, backgroundColor: data.statusDistribution.colors, borderWidth: 0, hoverOffset: 4 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { padding: 12, usePointStyle: true, pointStyleWidth: 8, font: { size: 12 } } } }, cutout: '65%' }
                });
            }

            // Monthly transactions (admin)
            var monthlyEl = document.getElementById('chartMonthly');
            if (monthlyEl && data.monthlyTransactions) {
                new Chart(monthlyEl, {
                    type: 'bar',
                    data: {
                        labels: data.monthlyTransactions.labels,
                        datasets: [
                            { label: 'Stock In', data: data.monthlyTransactions.stockIn, backgroundColor: '#059669', borderRadius: 6, barPercentage: 0.6 },
                            { label: 'Stock Out', data: data.monthlyTransactions.stockOut, backgroundColor: '#DC2626', borderRadius: 6, barPercentage: 0.6 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { usePointStyle: true, pointStyleWidth: 8, font: { size: 12 } } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: gridColor } } } }
                });
            }

            // Category distribution (admin)
            var catEl = document.getElementById('chartCategory');
            if (catEl && data.categoryDistribution) {
                new Chart(catEl, {
                    type: 'bar',
                    data: {
                        labels: data.categoryDistribution.labels,
                        datasets: [{ label: 'Drugs', data: data.categoryDistribution.data, backgroundColor: '#0D9488', borderRadius: 6, barPercentage: 0.7 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { color: gridColor } }, y: { grid: { display: false } } } }
                });
            }

            // Weekly activity (pharmacist)
            var weeklyEl = document.getElementById('chartWeekly');
            if (weeklyEl && data.weeklyActivity) {
                new Chart(weeklyEl, {
                    type: 'bar',
                    data: {
                        labels: data.weeklyActivity.labels,
                        datasets: [
                            { label: 'Stock In', data: data.weeklyActivity.stockIn, backgroundColor: '#059669', borderRadius: 6, barPercentage: 0.6 },
                            { label: 'Stock Out', data: data.weeklyActivity.stockOut, backgroundColor: '#DC2626', borderRadius: 6, barPercentage: 0.6 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { usePointStyle: true, pointStyleWidth: 8, font: { size: 12 } } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: gridColor } } } }
                });
            }

            // Checklist trend (pharmacist)
            var trendEl = document.getElementById('chartTrend');
            if (trendEl && data.recentChecklistTrend) {
                new Chart(trendEl, {
                    type: 'line',
                    data: {
                        labels: data.recentChecklistTrend.labels,
                        datasets: [{ label: 'Checklists', data: data.recentChecklistTrend.data, borderColor: '#0D9488', backgroundColor: 'rgba(13,148,136,0.1)', fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#0D9488' }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: gridColor } } } }
                });
            }

            // Drug quantities (staff)
            var drugsEl = document.getElementById('chartDrugs');
            if (drugsEl && data.recentDrugs) {
                new Chart(drugsEl, {
                    type: 'bar',
                    data: {
                        labels: data.recentDrugs.labels,
                        datasets: [{ label: 'Quantity', data: data.recentDrugs.data, backgroundColor: ['#0D9488', '#2563EB', '#7C3AED', '#D97706', '#DC2626'], borderRadius: 6, barPercentage: 0.7 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: gridColor } } } }
                });
            }
        })
        .catch(function() {});
}

// Set BASE_URL for JS
var BASE_URL = (typeof BASE_URL === 'undefined') ? '/Drug-Checklist-System/' : BASE_URL;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initDashboardAutoRefresh() {
    var statEls = document.querySelectorAll('[data-stat]');
    if (statEls.length === 0) return;

    var body = document.body;
    var searchRole = '';
    if (body.classList.contains('admin')) searchRole = 'admin';
    else if (body.classList.contains('pharmacist')) searchRole = 'pharmacist';
    else if (body.classList.contains('staff')) searchRole = 'staff';

    var tableMap = {
        'admin': { 'transactions': 'tbl-admin-transactions', 'checklists': 'tbl-admin-checklists' },
        'pharmacist': { 'low_stock': 'tbl-pharm-lowstock', 'stock_in': 'tbl-pharm-stockin', 'stock_out': 'tbl-pharm-stockout', 'my_today_checklists': 'tbl-pharm-checklists' },
        'staff': { 'recent_drugs': 'tbl-staff-drugs', 'checklists': 'tbl-staff-checklists' }
    };
    var maps = tableMap[searchRole] || {};

    // Add live indicator
    var pageHeader = document.querySelector('.page-header');
    if (pageHeader && !document.getElementById('liveIndicator')) {
        var live = document.createElement('div');
        live.id = 'liveIndicator';
        live.style.cssText = 'display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#6B7280;margin-top:4px;';
        live.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#10B981;display:inline-block;animation:pulse 2s infinite;"></span><span id="liveStatus">Live</span>';
        pageHeader.querySelector('div').appendChild(live);
    }

    // Add pulse animation
    if (!document.getElementById('liveStyle')) {
        var style = document.createElement('style');
        style.id = 'liveStyle';
        style.textContent = '@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}} .stat-flash{animation:flash .6s ease-out}@keyframes flash{0%{background:rgba(16,185,129,.2)}100%{background:transparent}}';
        document.head.appendChild(style);
    }

    var refreshDashboard = function() {
        fetch(BASE_URL + 'api/dashboard_data.php')
            .then(function(r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function(data) {
                if (data.error) return;

                // Update stat cards
                if (data.stats) {
                    var els = document.querySelectorAll('[data-stat]');
                    for (var i = 0; i < els.length; i++) {
                        var key = els[i].getAttribute('data-stat');
                        if (data.stats[key] !== undefined) {
                            var newVal = String(data.stats[key]);
                            if (els[i].textContent.trim() !== newVal) {
                                els[i].textContent = newVal;
                                var card = els[i].closest('.stat-card');
                                if (card) {
                                    card.classList.add('stat-flash');
                                    setTimeout(function(c) { return function() { c.classList.remove('stat-flash'); }; }(card), 700);
                                }
                            }
                        }
                    }
                }

                // Update notification badge
                if (data.stats.notifications !== undefined) {
                    updateNotificationBadge(data.stats.notifications);
                }

                // Update tables
                if (data.tables) {
                    for (var tblKey in maps) {
                        if (data.tables[tblKey] !== undefined) {
                            var tbody = document.getElementById(maps[tblKey]);
                            if (tbody) {
                                tbody.innerHTML = data.tables[tblKey];
                            }
                        }
                    }
                }

                var statusEl = document.getElementById('liveStatus');
                if (statusEl) statusEl.textContent = 'Updated ' + new Date().toLocaleTimeString();
            })
            .catch(function() {
                var statusEl = document.getElementById('liveStatus');
                if (statusEl) statusEl.textContent = 'Connection error';
            });
    };

    // First refresh after 10 seconds, then every 30 seconds
    setTimeout(refreshDashboard, 10000);
    setInterval(refreshDashboard, 30000);
}

function updateNotificationBadge(count) {
    var badges = document.querySelectorAll('.notification-badge');
    for (var i = 0; i < badges.length; i++) {
        if (count > 0) {
            badges[i].textContent = count > 99 ? '99+' : count;
            badges[i].style.display = 'flex';
        } else {
            badges[i].style.display = 'none';
        }
    }
}

function initNotificationBadgePolling() {
    setInterval(function() {
        fetch(BASE_URL + 'api/dashboard_data.php')
            .then(function(r) { if (!r.ok) return; return r.json(); })
            .then(function(data) {
                if (data && data.stats && data.stats.notifications !== undefined) {
                    updateNotificationBadge(data.stats.notifications);
                }
            })
            .catch(function() {});
    }, 15000);
}
