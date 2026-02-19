document.addEventListener('DOMContentLoaded', function () {
    /* ===== Sidebar Hover Expand/Collapse (Desktop) ===== */
    var appShell = document.querySelector('.app-shell');
    var sidebar = appShell ? appShell.querySelector('.sidebar') : null;
    var isDesktopSidebar = window.matchMedia('(min-width: 901px)');

    var updateSidebarMode = function () {
        if (!appShell || !sidebar) return;
        if (!isDesktopSidebar.matches) {
            appShell.classList.remove('sidebar-hover');
        }
    };

    if (appShell && sidebar) {
        sidebar.addEventListener('mouseenter', function () {
            if (isDesktopSidebar.matches) {
                appShell.classList.add('sidebar-hover');
            }
        });

        sidebar.addEventListener('mouseleave', function () {
            appShell.classList.remove('sidebar-hover');
        });

        sidebar.addEventListener('focusin', function () {
            if (isDesktopSidebar.matches) {
                appShell.classList.add('sidebar-hover');
            }
        });

        sidebar.addEventListener('focusout', function () {
            var nextTarget = document.activeElement;
            if (!sidebar.contains(nextTarget)) {
                appShell.classList.remove('sidebar-hover');
            }
        });

        if (typeof isDesktopSidebar.addEventListener === 'function') {
            isDesktopSidebar.addEventListener('change', updateSidebarMode);
        }

        updateSidebarMode();
    }

    /* ===== Live DateTime & Analog Clock ===== */
    var liveDateTime = document.getElementById('liveDateTime');
    var hourHand = document.getElementById('clockHour');
    var minuteHand = document.getElementById('clockMinute');
    var secondHand = document.getElementById('clockSecond');

    var pad2 = function (value) {
        return String(value).padStart(2, '0');
    };

    var updateLiveDateTime = function () {
        if (!liveDateTime) return;
        var now = new Date();
        var dateText = [pad2(now.getDate()), pad2(now.getMonth() + 1), now.getFullYear()].join('-');
        var timeText = [pad2(now.getHours()), pad2(now.getMinutes()), pad2(now.getSeconds())].join(':');
        liveDateTime.textContent = dateText + ' ' + timeText;
    };

    var updateAnalogClock = function () {
        if (!hourHand || !minuteHand || !secondHand) return;
        var now = new Date();
        var seconds = now.getSeconds();
        var minutes = now.getMinutes();
        var hours = now.getHours() % 12;

        var secondDeg = seconds * 6;
        var minuteDeg = (minutes * 6) + (seconds * 0.1);
        var hourDeg = (hours * 30) + (minutes * 0.5);

        secondHand.style.transform = 'translateX(-50%) rotate(' + secondDeg + 'deg)';
        minuteHand.style.transform = 'translateX(-50%) rotate(' + minuteDeg + 'deg)';
        hourHand.style.transform = 'translateX(-50%) rotate(' + hourDeg + 'deg)';
    };

    updateLiveDateTime();
    updateAnalogClock();
    setInterval(function () {
        updateLiveDateTime();
        updateAnalogClock();
    }, 1000);

    /* ===== Dashboard Header Shrink on Scroll ===== */
    var isDashboardPage = document.body.classList.contains('dashboard-body');
    if (isDashboardPage) {
        var lastKnownScrollY = window.scrollY || 0;
        var isTicking = false;
        var compactThreshold = 56;

        var updateDashboardScrollState = function () {
            document.body.classList.toggle('dashboard-scrolled', lastKnownScrollY > compactThreshold);
            isTicking = false;
        };

        window.addEventListener('scroll', function () {
            lastKnownScrollY = window.scrollY || 0;

            if (!isTicking) {
                window.requestAnimationFrame(updateDashboardScrollState);
                isTicking = true;
            }
        }, { passive: true });

        updateDashboardScrollState();
    }

    /* ===== Scroll-triggered Animations (IntersectionObserver) ===== */
    var observerOptions = {
        threshold: 0.08,
        rootMargin: '0px 0px -40px 0px'
    };

    var animateOnScroll = function (entries, observer) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                var el = entry.target;
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
                observer.unobserve(el);
            }
        });
    };

    if ('IntersectionObserver' in window) {
        var scrollObserver = new IntersectionObserver(animateOnScroll, observerOptions);

        /* Animate panels, info-cards, and ig-cards on scroll */
        var animTargets = document.querySelectorAll('.panel, .info-card, .ig-card, .export-report-panel');
        animTargets.forEach(function (el, index) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(24px)';
            el.style.transition = 'opacity 0.5s cubic-bezier(0.22, 1, 0.36, 1) ' + (index * 0.04) + 's, transform 0.5s cubic-bezier(0.22, 1, 0.36, 1) ' + (index * 0.04) + 's';
            scrollObserver.observe(el);
        });

        /* Animate summary grid cards with stagger */
        var summaryCards = document.querySelectorAll('.summary-grid .info-card');
        summaryCards.forEach(function (card, idx) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px) scale(0.97)';
            card.style.transition = 'opacity 0.5s ease ' + (idx * 0.1) + 's, transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) ' + (idx * 0.1) + 's';
            scrollObserver.observe(card);
        });
    }

    /* ===== Counter Animation for Info Cards ===== */
    var animateCounter = function (element, targetValue, duration) {
        var startValue = 0;
        var startTime = null;
        var originalText = element.textContent;

        /* Parse numeric value */
        var cleanValue = targetValue.replace(/[^0-9.,%-]/g, '');
        var numericValue = parseFloat(cleanValue.replace(/\./g, '').replace(',', '.'));

        if (isNaN(numericValue)) return;

        var isPercentage = targetValue.includes('%');
        var hasPlus = targetValue.startsWith('+');
        var decimalPlaces = 0;
        if (cleanValue.includes(',')) {
            var parts = cleanValue.split(',');
            if (parts.length > 1) {
                decimalPlaces = parts[parts.length - 1].replace('%', '').length;
            }
        }

        var step = function (timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            /* easeOutExpo */
            var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            var currentValue = startValue + (numericValue - startValue) * eased;

            var formatted;
            if (decimalPlaces > 0) {
                formatted = currentValue.toFixed(decimalPlaces).replace('.', ',');
            } else {
                formatted = Math.round(currentValue).toLocaleString('id-ID');
            }

            if (hasPlus) formatted = '+' + formatted;
            if (isPercentage) formatted += '%';

            element.textContent = formatted;

            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };

        window.requestAnimationFrame(step);
    };

    /* Observe info-card values for counter animation */
    if ('IntersectionObserver' in window) {
        var counterObserver = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var pElement = entry.target.querySelector('p');
                    if (pElement && !pElement.dataset.animated) {
                        pElement.dataset.animated = '1';
                        animateCounter(pElement, pElement.textContent.trim(), 1200);
                    }
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        var infoCardElements = document.querySelectorAll('.summary-grid .info-card');
        infoCardElements.forEach(function (card) {
            counterObserver.observe(card);
        });
    }

    /* ===== Ripple Effect on Buttons ===== */
    document.addEventListener('click', function (event) {
        var button = event.target.closest('button, .export-btn, .sync-btn');
        if (!button) return;

        var ripple = document.createElement('span');
        var rect = button.getBoundingClientRect();
        var size = Math.max(rect.width, rect.height);
        var x = event.clientX - rect.left - size / 2;
        var y = event.clientY - rect.top - size / 2;

        ripple.style.cssText = 'position:absolute;width:' + size + 'px;height:' + size + 'px;left:' + x + 'px;top:' + y + 'px;border-radius:50%;background:rgba(255,255,255,0.3);transform:scale(0);animation:rippleEffect 0.6s ease-out forwards;pointer-events:none;z-index:1;';

        /* Inject ripple keyframes once */
        if (!document.getElementById('rippleStyle')) {
            var style = document.createElement('style');
            style.id = 'rippleStyle';
            style.textContent = '@keyframes rippleEffect{to{transform:scale(2.5);opacity:0;}}';
            document.head.appendChild(style);
        }

        button.style.position = button.style.position || 'relative';
        button.style.overflow = 'hidden';
        button.appendChild(ripple);

        setTimeout(function () {
            ripple.remove();
        }, 700);
    });

    /* ===== Smooth Page Transition on Nav Links ===== */
    var navLinks = document.querySelectorAll('.sidebar nav a:not([href="logout.php"])');
    navLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (link.classList.contains('active')) {
                e.preventDefault();
                return;
            }

            var contentArea = document.querySelector('.content-area');
            if (contentArea) {
                contentArea.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                contentArea.style.opacity = '0';
                contentArea.style.transform = 'translateY(10px)';
            }
        });
    });

    /* ===== Tooltip for Social Links ===== */
    var socialLinks = document.querySelectorAll('.social-link');
    socialLinks.forEach(function (link) {
        var label = link.getAttribute('aria-label') || '';
        if (!label) return;

        link.addEventListener('mouseenter', function () {
            var tooltip = document.createElement('div');
            tooltip.className = 'social-tooltip';
            tooltip.textContent = label;
            tooltip.style.cssText = 'position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:rgba(15,23,42,0.9);color:#fff;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap;pointer-events:none;z-index:100;animation:fadeIn 0.2s ease;backdrop-filter:blur(4px);';
            link.style.position = 'relative';
            link.appendChild(tooltip);
        });

        link.addEventListener('mouseleave', function () {
            var tooltip = link.querySelector('.social-tooltip');
            if (tooltip) tooltip.remove();
        });
    });

    /* ===== Chart.js Integration ===== */
    if (typeof Chart === 'undefined') return;

    var payload = window.dashboardPayload || {};

    /* Shared chart styling */
    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#64748b';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding = 16;

    var interactionTrendCanvas = document.getElementById('interactionTrendChart');
    if (interactionTrendCanvas) {
        var ctx = interactionTrendCanvas.getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.25)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.02)');

        new Chart(interactionTrendCanvas, {
            type: 'line',
            data: {
                labels: payload.dailyLabels || ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
                datasets: [{
                    label: 'Interactions',
                    data: payload.dailyInteractions || [120, 148, 132, 154, 181, 166, 140],
                    borderColor: '#3b82f6',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 2,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#3b82f6',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { weight: '700' },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    var categoryCanvas = document.getElementById('contentCategoryChart');
    if (categoryCanvas) {
        new Chart(categoryCanvas, {
            type: 'doughnut',
            data: {
                labels: payload.categoryLabels || ['Berita', 'Hiburan', 'Olahraga', 'Edukasi'],
                datasets: [{
                    data: payload.categoryValues || [120, 95, 80, 70],
                    backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6', '#06b6d4'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                cutout: '55%',
                animation: {
                    animateRotate: true,
                    duration: 1500,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 12,
                        cornerRadius: 10
                    }
                }
            }
        });
    }

    var igInteractionBarCanvas = document.getElementById('igInteractionBarChart');
    if (igInteractionBarCanvas) {
        new Chart(igInteractionBarCanvas, {
            type: 'bar',
            data: {
                labels: payload.igBarLabels || ['Konten 1', 'Konten 2', 'Konten 3', 'Konten 4', 'Konten 5'],
                datasets: [{
                    label: 'Total Interaksi (Likes + Comments)',
                    data: payload.igBarValues || [220, 310, 280, 340, 295],
                    backgroundColor: [
                        'rgba(96, 165, 250, 0.85)',
                        'rgba(59, 130, 246, 0.85)',
                        'rgba(37, 99, 235, 0.85)',
                        'rgba(29, 78, 216, 0.85)',
                        'rgba(30, 58, 138, 0.85)'
                    ],
                    borderRadius: 10,
                    maxBarThickness: 48,
                    borderSkipped: false,
                    hoverBackgroundColor: [
                        'rgba(96, 165, 250, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(37, 99, 235, 1)',
                        'rgba(29, 78, 216, 1)',
                        'rgba(30, 58, 138, 1)'
                    ]
                }]
            },
            options: {
                responsive: true,
                animation: {
                    duration: 1200,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 12,
                        cornerRadius: 10
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 45 }
                    }
                }
            }
        });
    }
});
