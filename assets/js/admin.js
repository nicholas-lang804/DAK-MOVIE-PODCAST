// Admin Dashboard Interactive Features

document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Remove active class from all links
            sidebarLinks.forEach(l => l.classList.remove('active'));
            // Add active class to clicked link
            this.classList.add('active');
        });
    });
    
    // Toggle advert file input based on type
    window.toggleAdvertFile = function() {
        const type = document.getElementById('advert_type').value;
        const fileGroup = document.getElementById('advert_file_group');
        const textGroup = document.getElementById('advert_text_group');
        const fileInput = document.getElementById('advert_file');
        
        if (type === 'text') {
            fileGroup.style.display = 'none';
            textGroup.style.display = 'block';
            fileInput.removeAttribute('required');
        } else {
            fileGroup.style.display = 'block';
            textGroup.style.display = 'none';
            fileInput.setAttribute('required', 'required');
        }
    };
    
    // Form validation for media upload
    const mediaForm = document.querySelector('form[action*="upload_media"]');
    if (mediaForm) {
        mediaForm.addEventListener('submit', function(e) {
            const accessType = document.getElementById('access_type').value;
            const price = parseFloat(document.getElementById('price').value);
            const downloadFee = parseFloat(document.getElementById('download_fee').value);
            
            if (accessType !== 'free' && price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0 for paid content');
                return false;
            }
            
            if (accessType === 'pay_to_download' && downloadFee <= 0) {
                e.preventDefault();
                alert('Download fee must be greater than 0 for pay-to-download content');
                return false;
            }
        });
    }
    
    // Form validation for podcast
    const podcastForm = document.querySelector('form[action*="add_podcast"]');
    if (podcastForm) {
        podcastForm.addEventListener('submit', function(e) {
            const accessType = document.getElementById('podcast_access_type').value;
            const price = parseFloat(document.getElementById('podcast_price').value);
            
            if (accessType === 'paid' && price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0 for paid podcasts');
                return false;
            }
        });
    }
    
    // Form validation for discounts
    const discountForm = document.querySelector('form[action*="create_discount"]');
    if (discountForm) {
        discountForm.addEventListener('submit', function(e) {
            const discountPercentage = parseFloat(document.getElementById('discount_percentage').value);
            const startDate = new Date(document.getElementById('discount_start_date').value);
            const endDate = new Date(document.getElementById('discount_end_date').value);
            
            if (discountPercentage <= 0 || discountPercentage > 100) {
                e.preventDefault();
                alert('Discount percentage must be between 0 and 100');
                return false;
            }
            
            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date');
                return false;
            }
        });
    }
    
    // JSON validation for sport updates
    const sportUpdatesField = document.getElementById('sport_updates');
    if (sportUpdatesField) {
        sportUpdatesField.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) {
                try {
                    JSON.parse(value);
                    this.style.borderColor = 'var(--success-color)';
                } catch (e) {
                    this.style.borderColor = 'var(--danger-color)';
                    alert('Invalid JSON format for sport updates');
                }
            }
        });
    }
    
    // File upload preview for images
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Create preview if needed
                    console.log('Image loaded:', file.name);
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Auto-hide success messages
    const successAlerts = document.querySelectorAll('.alert-success');
    successAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 3000);
    });
    
    // Table row hover effects
    const tableRows = document.querySelectorAll('.admin-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'var(--bg-color)';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Character counter for textareas
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        if (maxLength) {
            const counter = document.createElement('div');
            counter.className = 'char-counter';
            counter.style.fontSize = '0.75rem';
            counter.style.color = 'var(--text-muted)';
            counter.style.textAlign = 'right';
            counter.style.marginTop = '0.25rem';
            textarea.parentNode.insertBefore(counter, textarea.nextSibling);
            
            const updateCounter = () => {
                const remaining = maxLength - textarea.value.length;
                counter.textContent = `${remaining} characters remaining`;
            };
            
            textarea.addEventListener('input', updateCounter);
            updateCounter();
        }
    });
    
    // Date picker enhancements
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        // Set minimum date to today for start dates
        if (input.id.includes('start')) {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('min', today);
        }
        
        // Ensure end date is after start date
        if (input.id.includes('end')) {
            const startDateInput = document.getElementById(input.id.replace('end', 'start'));
            if (startDateInput) {
                startDateInput.addEventListener('change', function() {
                    input.setAttribute('min', this.value);
                });
            }
        }
    });
});
