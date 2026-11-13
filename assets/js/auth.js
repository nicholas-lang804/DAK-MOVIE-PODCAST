// Authentication Form Validation and Interactions

document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const passwordFields = document.querySelectorAll('#password, #confirm_password, #new_password');
    const confirmPasswordField = document.querySelector('#confirm_password');
    
    if (confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function() {
            const password = document.querySelector('#password')?.value || document.querySelector('#new_password')?.value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    // Form validation on submit
    const authForms = document.querySelectorAll('.auth-form');
    authForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const password = this.querySelector('#password')?.value || this.querySelector('#new_password')?.value;
            const confirmPassword = this.querySelector('#confirm_password')?.value;
            
            if (confirmPasswordField && password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            if (password && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters');
                return false;
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});
