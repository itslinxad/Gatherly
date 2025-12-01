// Manager Dashboard JavaScript

// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
        
        // Toggle hamburger icon
        const icon = mobileMenuBtn.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        }
    });
}

// Profile dropdown toggle
if (!window.profileDropdownInitialized) {
    const profileBtn = document.getElementById('profile-dropdown-btn');
    const profileDropdown = document.getElementById('profile-dropdown');

    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    }
    window.profileDropdownInitialized = true;
}

// Dynamic Pricing Tool Modal Management
const openPricingToolBtn = document.getElementById('openPricingTool');
const closePricingToolBtn = document.getElementById('closePricingTool');
const pricingModal = document.getElementById('pricingModal');
const calculatePriceBtn = document.getElementById('calculatePrice');
const pricingResult = document.getElementById('pricingResult');
const optimalPriceDisplay = document.getElementById('optimalPrice');

// Open pricing tool modal
if (openPricingToolBtn) {
    openPricingToolBtn.addEventListener('click', () => {
        pricingModal.classList.remove('hidden');
        pricingModal.classList.add('flex');
    });
}

// Close pricing tool modal
if (closePricingToolBtn) {
    closePricingToolBtn.addEventListener('click', () => {
        pricingModal.classList.add('hidden');
        pricingModal.classList.remove('flex');
    });
}

// Close modal when clicking outside
if (pricingModal) {
    pricingModal.addEventListener('click', (e) => {
        if (e.target === pricingModal) {
            pricingModal.classList.add('hidden');
            pricingModal.classList.remove('flex');
        }
    });
}

// Calculate optimal price
if (calculatePriceBtn) {
    calculatePriceBtn.addEventListener('click', () => {
        const basePrice = parseFloat(document.getElementById('basePrice').value) || 0;
        const seasonFactor = parseFloat(document.getElementById('season').value) || 1.0;
        const dayTypeFactor = parseFloat(document.getElementById('dayType').value) || 1.0;
        const demandFactor = parseFloat(document.getElementById('demand').value) || 1.0;

        // Calculate optimal price using dynamic pricing formula
        const optimalPrice = basePrice * seasonFactor * dayTypeFactor * demandFactor;

        // Display result
        if (pricingResult && optimalPriceDisplay) {
            optimalPriceDisplay.textContent = '₱' + formatNumber(optimalPrice.toFixed(2));
            pricingResult.classList.remove('hidden');
        }
    });
}

// Close modal with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && pricingModal && !pricingModal.classList.contains('hidden')) {
        pricingModal.classList.add('hidden');
        pricingModal.classList.remove('flex');
    }
});

// Charts Initialization
document.addEventListener('DOMContentLoaded', function() {
    if (typeof chartData !== 'undefined') {
        initializeVenuePerformanceChart();
        initializeMonthlyRevenueChart();
        initializeEventTypeChart();
        initializeStatusChart();
        initializePaymentChart();
    }
});

// Venue Performance Chart
function initializeVenuePerformanceChart() {
    const ctx = document.getElementById('venuePerformanceChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.venueNames,
            datasets: [
                {
                    label: 'Bookings',
                    data: chartData.venueBookings,
                    backgroundColor: 'rgba(34, 197, 94, 0.7)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 2,
                    yAxisID: 'y',
                    order: 2
                },
                {
                    label: 'Revenue (₱)',
                    data: chartData.venueRevenues,
                    type: 'line',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    yAxisID: 'y1',
                    order: 1,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            family: 'Montserrat'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        family: 'Montserrat'
                    },
                    bodyFont: {
                        size: 13,
                        family: 'Montserrat'
                    },
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (context.datasetIndex === 1) {
                                    label += '₱' + formatNumber(context.parsed.y);
                                } else {
                                    label += context.parsed.y + ' booking(s)';
                                }
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        },
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Bookings',
                        font: {
                            size: 12,
                            family: 'Montserrat',
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        stepSize: 2,
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Revenue (₱)',
                        font: {
                            size: 12,
                            family: 'Montserrat',
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + formatNumber(value);
                        },
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// Monthly Revenue Trend Chart
function initializeMonthlyRevenueChart() {
    const ctx = document.getElementById('monthlyRevenueChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.months,
            datasets: [
                {
                    label: 'Revenue (₱)',
                    data: chartData.monthlyRevenues,
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                },
                {
                    label: 'Bookings',
                    data: chartData.monthlyBookings,
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: 'rgba(34, 197, 94, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            family: 'Montserrat'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        family: 'Montserrat'
                    },
                    bodyFont: {
                        size: 13,
                        family: 'Montserrat'
                    },
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (context.datasetIndex === 0) {
                                    label += '₱' + formatNumber(context.parsed.y);
                                } else {
                                    label += context.parsed.y + ' booking(s)';
                                }
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue (₱)',
                        font: {
                            size: 12,
                            family: 'Montserrat',
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + formatNumber(value);
                        },
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Bookings',
                        font: {
                            size: 12,
                            family: 'Montserrat',
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// Event Type Distribution Chart
function initializeEventTypeChart() {
    const ctx = document.getElementById('eventTypeChart');
    if (!ctx) return;

    const colors = [
        'rgba(99, 102, 241, 0.8)',  // Indigo
        'rgba(236, 72, 153, 0.8)',  // Pink
        'rgba(249, 115, 22, 0.8)',  // Orange
        'rgba(14, 165, 233, 0.8)',  // Sky Blue
        'rgba(168, 85, 247, 0.8)'   // Purple
    ];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartData.eventTypeLabels,
            datasets: [{
                data: chartData.eventTypeCounts,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        family: 'Montserrat'
                    },
                    bodyFont: {
                        size: 13,
                        family: 'Montserrat'
                    },
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Booking Status Chart
function initializeStatusChart() {
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;

    const statusColors = {
        'Pending': 'rgba(251, 191, 36, 0.8)',     // Yellow
        'Confirmed': 'rgba(34, 197, 94, 0.8)',    // Green
        'Completed': 'rgba(59, 130, 246, 0.8)',   // Blue
        'Canceled': 'rgba(239, 68, 68, 0.8)'      // Red
    };

    const colors = chartData.statusLabels.map(label => statusColors[label] || 'rgba(156, 163, 175, 0.8)');

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartData.statusLabels,
            datasets: [{
                data: chartData.statusCounts,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        family: 'Montserrat'
                    },
                    bodyFont: {
                        size: 13,
                        family: 'Montserrat'
                    },
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Payment Status Chart
function initializePaymentChart() {
    const ctx = document.getElementById('paymentChart');
    if (!ctx) return;

    const paymentColors = {
        'Unpaid': 'rgba(239, 68, 68, 0.7)',      // Red
        'Partial': 'rgba(251, 191, 36, 0.7)',    // Yellow
        'Paid': 'rgba(34, 197, 94, 0.7)'         // Green
    };

    const colors = chartData.paymentLabels.map(label => paymentColors[label] || 'rgba(156, 163, 175, 0.7)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.paymentLabels,
            datasets: [
                {
                    label: 'Number of Events',
                    data: chartData.paymentCounts,
                    backgroundColor: colors,
                    borderColor: colors.map(c => c.replace('0.7', '1')),
                    borderWidth: 2,
                    yAxisID: 'y'
                },
                {
                    label: 'Total Amount (₱)',
                    data: chartData.paymentTotals,
                    type: 'line',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    borderColor: 'rgba(168, 85, 247, 1)',
                    borderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: 'rgba(168, 85, 247, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    yAxisID: 'y1',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            family: 'Montserrat'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        family: 'Montserrat'
                    },
                    bodyFont: {
                        size: 13,
                        family: 'Montserrat'
                    },
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (context.datasetIndex === 1) {
                                    label += '₱' + formatNumber(context.parsed.y);
                                } else {
                                    label += context.parsed.y + ' event(s)';
                                }
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Events',
                        font: {
                            size: 12,
                            family: 'Montserrat',
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Amount (₱)',
                        font: {
                            size: 12,
                            family: 'Montserrat',
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + formatNumber(value);
                        },
                        font: {
                            size: 11,
                            family: 'Montserrat'
                        }
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
