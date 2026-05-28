// assets/js/main.js - Version complète

class MedicalSystem {
    constructor() {
        this.init();
    }
    
    init() {
        this.initSidebar();
        this.initTheme();
        this.initNotifications();
        this.initForms();
        this.initModals();
        this.initTooltips();
        this.initDataTables();
        this.initCharts();
    }
    
    initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
                this.saveSidebarState(sidebar.classList.contains('show'));
            });
        }
        
        // Load saved state
        const savedState = localStorage.getItem('sidebarOpen');
        if (savedState === 'true') {
            sidebar.classList.add('show');
        }
        
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && 
                sidebar.classList.contains('show') &&
                !sidebar.contains(e.target) && 
                (!sidebarToggle || !sidebarToggle.contains(e.target))) {
                sidebar.classList.remove('show');
                this.saveSidebarState(false);
            }
        });
    }
    
    saveSidebarState(isOpen) {
        localStorage.setItem('sidebarOpen', isOpen);
    }
    
    initTheme() {
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        if (themeToggle) {
            // Load saved theme
            const savedTheme = localStorage.getItem('theme') || 'light';
            html.setAttribute('data-bs-theme', savedTheme);
            this.updateThemeIcon(savedTheme);
            
            themeToggle.addEventListener('click', () => {
                const currentTheme = html.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                html.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                this.updateThemeIcon(newTheme);
            });
        }
    }
    
    updateThemeIcon(theme) {
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.innerHTML = theme === 'dark' ? 
                '<i class="fas fa-sun"></i>' : 
                '<i class="fas fa-moon"></i>';
        }
    }
    
    initNotifications() {
        // Auto-dismiss alerts
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => {
            setTimeout(() => {
                if (alert.parentNode) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            }, 5000);
        });
        
        // Load notifications via AJAX
        this.loadNotifications();
        
        // Poll for new notifications every 30 seconds
        setInterval(() => this.loadNotifications(), 30000);
    }
    
    async loadNotifications() {
        try {
            const response = await fetch('../ajax/notifications.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateNotificationsUI(data.notifications);
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }
    
    updateNotificationsUI(notifications) {
        const container = document.getElementById('notificationsList');
        if (!container) return;
        
        container.innerHTML = notifications.map(notif => `
            <a class="dropdown-item d-flex align-items-start py-2" href="${notif.url}">
                <div class="me-3">
                    <i class="fas fa-circle text-${notif.important ? 'danger' : 'primary'}"></i>
                </div>
                <div>
                    <small class="text-muted">${notif.title}</small>
                    <p class="mb-0 small">${notif.message}</p>
                </div>
            </a>
        `).join('');
        
        // Update badge count
        const unreadCount = notifications.filter(n => !n.read).length;
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = unreadCount;
            badge.style.display = unreadCount > 0 ? 'inline' : 'none';
        }
    }
    
    initForms() {
        // Form validation
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
        
        // Auto-save forms
        const autosaveForms = document.querySelectorAll('[data-autosave]');
        autosaveForms.forEach(form => {
            let timeout;
            const inputs = form.querySelectorAll('input, textarea, select');
            
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        this.autoSaveForm(form);
                    }, 1000);
                });
            });
        });
    }
    
    async autoSaveForm(form) {
        const formData = new FormData(form);
        const saveBtn = form.querySelector('.save-status');
        
        if (saveBtn) {
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sauvegarde...';
        }
        
        try {
            const response = await fetch(form.action || '../ajax/autosave.php', {
                method: 'POST',
                body: formData
            });
            
            if (saveBtn) {
                saveBtn.innerHTML = '<i class="fas fa-check me-1"></i>Sauvegardé';
                setTimeout(() => {
                    saveBtn.innerHTML = '';
                }, 2000);
            }
        } catch (error) {
            if (saveBtn) {
                saveBtn.innerHTML = '<i class="fas fa-times me-1"></i>Erreur';
            }
        }
    }
    
    initModals() {
        // Initialize all modals
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('shown.bs.modal', () => {
                const firstInput = modal.querySelector('input, textarea, select');
                if (firstInput) firstInput.focus();
            });
        });
    }
    
    initTooltips() {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(el => {
            new bootstrap.Tooltip(el);
        });
    }
    
    initDataTables() {
        // Initialize simple data tables
        const tables = document.querySelectorAll('.data-table:not(.no-init)');
        tables.forEach(table => {
            this.initTableSearch(table);
            this.initTableSort(table);
        });
    }
    
    initTableSearch(table) {
        const searchInput = table.querySelector('.table-search');
        if (!searchInput) return;
        
        searchInput.addEventListener('input', debounce(() => {
            const searchTerm = searchInput.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }, 300));
    }
    
    initTableSort(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(table, header.dataset.sort);
            });
        });
    }
    
    sortTable(table, columnIndex) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAsc = table.dataset.sortDirection !== 'desc';
        
        rows.sort((a, b) => {
            const aValue = a.children[columnIndex].textContent;
            const bValue = b.children[columnIndex].textContent;
            
            if (isAsc) {
                return aValue.localeCompare(bValue);
            } else {
                return bValue.localeCompare(aValue);
            }
        });
        
        rows.forEach(row => tbody.appendChild(row));
        table.dataset.sortDirection = isAsc ? 'desc' : 'asc';
    }
    
    initCharts() {
        // Initialize charts if Chart.js is loaded
        if (typeof Chart !== 'undefined') {
            this.initStatsCharts();
        }
    }
    
    initStatsCharts() {
        const chartElements = document.querySelectorAll('[data-chart]');
        chartElements.forEach(element => {
            const type = element.dataset.chartType || 'line';
            const data = JSON.parse(element.dataset.chartData || '{}');
            const options = JSON.parse(element.dataset.chartOptions || '{}');
            
            new Chart(element, {
                type: type,
                data: data,
                options: options
            });
        });
    }
    
    // Utility methods
    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type}`;
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        const container = document.getElementById('toastContainer') || this.createToastContainer();
        container.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }
    
    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
        return container;
    }
    
    confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
    
    exportData(data, filename, type = 'csv') {
        let content, mimeType;
        
        if (type === 'csv') {
            content = this.convertToCSV(data);
            mimeType = 'text/csv;charset=utf-8;';
        } else if (type === 'json') {
            content = JSON.stringify(data, null, 2);
            mimeType = 'application/json';
        }
        
        const blob = new Blob([content], { type: mimeType });
        const link = document.createElement('a');
        
        if (navigator.msSaveBlob) {
            navigator.msSaveBlob(blob, filename);
        } else {
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
    
    convertToCSV(data) {
        if (!data || !data.length) return '';
        
        const headers = Object.keys(data[0]);
        const rows = data.map(row => 
            headers.map(header => 
                JSON.stringify(row[header] || '')
            ).join(',')
        );
        
        return [headers.join(','), ...rows].join('\n');
    }
}

// Initialize application
document.addEventListener('DOMContentLoaded', () => {
    window.medSystem = new MedicalSystem();
});

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatDate(date, format = 'DD/MM/YYYY') {
    const d = new Date(date);
    const day = d.getDate().toString().padStart(2, '0');
    const month = (d.getMonth() + 1).toString().padStart(2, '0');
    const year = d.getFullYear();
    const hours = d.getHours().toString().padStart(2, '0');
    const minutes = d.getMinutes().toString().padStart(2, '0');
    
    switch (format) {
        case 'DD/MM/YYYY': return `${day}/${month}/${year}`;
        case 'DD/MM/YYYY HH:mm': return `${day}/${month}/${year} ${hours}:${minutes}`;
        case 'YYYY-MM-DD': return `${year}-${month}-${day}`;
        default: return date;
    }
}

function calculateAge(birthdate) {
    const birth = new Date(birthdate);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    
    return age;
}


// Authentication Functions
class Auth {
    static init() {
        this.initPasswordToggle();
        this.initPasswordStrength();
        this.initFormValidation();
        this.initAutoFocus();
    }
    
    static initPasswordToggle() {
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.closest('.input-group').querySelector('.password-input');
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }
    
    static initPasswordStrength() {
        const passwordInputs = document.querySelectorAll('.password-strength-input');
        
        passwordInputs.forEach(input => {
            input.addEventListener('input', function() {
                const strength = this.calculatePasswordStrength(this.value);
                const strengthBar = this.closest('.form-group').querySelector('.strength-bar');
                
                if (strengthBar) {
                    strengthBar.className = 'strength-bar';
                    strengthBar.classList.add(`strength-${strength}`);
                }
            });
        });
    }
    
    static calculatePasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        return Math.min(strength, 4);
    }
    
    static initFormValidation() {
        const forms = document.querySelectorAll('.auth-form form');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Show validation errors
                    const invalidFields = this.querySelectorAll(':invalid');
                    invalidFields.forEach(field => {
                        field.closest('.form-group')?.classList.add('was-validated');
                    });
                    
                    // Scroll to first error
                    if (invalidFields.length > 0) {
                        invalidFields[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                
                this.classList.add('was-validated');
            });
        });
    }
    
    static initAutoFocus() {
        // Auto-focus on first input field
        const firstInput = document.querySelector('.auth-form input:not([type="hidden"])');
        if (firstInput) firstInput.focus();
    }
    
    static async validateEmail(email) {
        const response = await fetch(`../ajax/validate_email.php?email=${encodeURIComponent(email)}`);
        return await response.json();
    }
    
    static showRecaptcha() {
        // Load reCAPTCHA if needed
        if (typeof grecaptcha !== 'undefined') {
            grecaptcha.render('recaptcha-container', {
                sitekey: 'YOUR_RECAPTCHA_SITE_KEY'
            });
        }
    }
}

// Initialize auth when DOM is loaded
document.addEventListener('DOMContentLoaded', () => Auth.init());

// Expose utilities globally
window.debounce = debounce;
window.formatDate = formatDate;
window.calculateAge = calculateAge;