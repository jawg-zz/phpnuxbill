// DOM event listeners
document.addEventListener('DOMContentLoaded', () => {
    // Session validation for protected pages
    if(!window.location.pathname.includes('login.html')) {
        const lastLogin = localStorage.getItem('lastLoginAttempt');
        if(!lastLogin || (Date.now() - new Date(lastLogin).getTime()) > 3600000) { // 1 hour
            window.location.href = 'login.html';
        }
    }

    // Initialize package selection
    document.querySelectorAll('.package-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.package-option').forEach(el =>
                el.classList.remove('ring-2', 'ring-blue-500'));
            this.classList.add('ring-2', 'ring-blue-500');
            document.getElementById('selectedPlan').value = this.dataset.plan;
        });
    });
});