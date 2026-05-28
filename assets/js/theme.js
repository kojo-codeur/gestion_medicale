// Gestionnaire de thème
class ThemeManager {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.init();
    }

    init() {
        this.applyTheme();
        this.bindEvents();
    }

    applyTheme() {
        document.documentElement.setAttribute('data-theme', this.theme);
        localStorage.setItem('theme', this.theme);
        this.updateThemeToggle();
    }

    bindEvents() {
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }
    }

    toggleTheme() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        this.applyTheme();
    }

    updateThemeToggle() {
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            const text = themeToggle.querySelector('.theme-text');
            
            if (this.theme === 'dark') {
                icon.className = 'fas fa-sun';
                if (text) text.textContent = 'Mode clair';
            } else {
                icon.className = 'fas fa-moon';
                if (text) text.textContent = 'Mode sombre';
            }
        }
    }

    getCurrentTheme() {
        return this.theme;
    }

    setTheme(theme) {
        if (['light', 'dark'].includes(theme)) {
            this.theme = theme;
            this.applyTheme();
        }
    }
}

// Initialiser le gestionnaire de thème
const themeManager = new ThemeManager();

// Exporter pour une utilisation globale
window.ThemeManager = themeManager;