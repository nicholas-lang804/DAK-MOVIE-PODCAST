/* THE MOVIE ZONE PODCAST 256 - JavaScript Utilities */

// CSRF Token Management
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// AJAX Helper
async function ajaxRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken()
        }
    };

    const mergedOptions = { ...defaultOptions, ...options };

    try {
        const response = await fetch(url, mergedOptions);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('AJAX request failed:', error);
        throw error;
    }
}

// Form Validation
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });

    return isValid;
}

// Show/Hide Loading Spinner
function showLoading() {
    const spinner = document.getElementById('loading-spinner');
    if (spinner) {
        spinner.style.display = 'flex';
    }
}

function hideLoading() {
    const spinner = document.getElementById('loading-spinner');
    if (spinner) {
        spinner.style.display = 'none';
    }
}

// Toast Notifications
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Confirm Dialog
function confirmAction(message) {
    return confirm(message);
}

// Date Formatting
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Time Formatting
function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// File Upload Preview
function previewImage(input, previewId) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    }
}

function previewMedia(input, previewId = 'media-preview') {
    const file = input.files && input.files[0];
    const preview = document.getElementById(previewId);
    if (!file || !preview) return;
    preview.hidden = false;
    preview.innerHTML = '';
    const label = document.createElement('p');
    label.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    preview.appendChild(label);
    const url = URL.createObjectURL(file);
    if (file.type.startsWith('image/')) {
        const image = document.createElement('img');
        image.src = url; image.alt = 'Selected media preview';
        preview.appendChild(image);
    } else if (file.type.startsWith('video/')) {
        const video = document.createElement('video');
        video.src = url; video.controls = true; video.preload = 'metadata';
        preview.appendChild(video);
    } else if (file.type.startsWith('audio/')) {
        const audio = document.createElement('audio');
        audio.src = url; audio.controls = true;
        preview.appendChild(audio);
    }
}

// Initialize on DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add CSRF token to all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const csrfInput = form.querySelector('input[name="csrf_token"]');
        if (!csrfInput) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = getCSRFToken();
            form.appendChild(input);
        }
    });

    document.querySelectorAll('[data-media-preview]').forEach(input => {
        input.addEventListener('change', () => previewMedia(input));
    });

    const showcase = document.querySelector('.movie-showcase');
    if (showcase) {
        const slides = Array.from(document.querySelectorAll('.movie-showcase-item'));
        if (slides.length) {
            const poster = showcase.querySelector('.movie-showcase-poster');
            const title = showcase.querySelector('.movie-showcase-content h2');
            const tag = showcase.querySelector('.movie-showcase-tag');
            const year = showcase.querySelector('.movie-showcase-content .movie-showcase-meta span:nth-child(2)');
            const runtime = showcase.querySelector('.movie-showcase-content .movie-showcase-meta span:last-child');
            const description = showcase.querySelector('.movie-showcase-content p');
            const action = showcase.querySelector('.movie-showcase-actions .btn-primary');
            const secondaryAction = showcase.querySelector('.movie-showcase-actions .btn-outline');
            const prevButton = showcase.querySelector('.movie-showcase-arrow.prev');
            const nextButton = showcase.querySelector('.movie-showcase-arrow.next');
            let currentIndex = 0;
            let autoRotate = null;

            const renderSlide = (index) => {
                const slide = slides[index];
                if (!slide || !poster || !title || !tag || !year || !description || !action || !secondaryAction) return;

                const imageUrl = slide.dataset.image || '';
                const link = slide.dataset.link || '#';
                const label = slide.dataset.buttonLabel || 'Watch now';
                const synopsis = slide.dataset.synopsis || 'A featured movie from the latest screening room lineup.';

                if (imageUrl) {
                    poster.innerHTML = '<img src="' + imageUrl + '" alt="' + (slide.dataset.title || 'Featured movie') + '">';
                } else {
                    poster.innerHTML = '<div class="movie-showcase-fallback"><span>TMZ</span><strong>' + (slide.dataset.title || 'Featured Movie') + '</strong></div>';
                }

                title.textContent = slide.dataset.title || 'Featured movie';
                tag.textContent = slide.dataset.genre || 'Adventure';
                year.textContent = slide.dataset.year || '2025';
                if (runtime) {
                    runtime.textContent = slide.dataset.runtime || '120 min';
                }
                description.textContent = synopsis;
                action.textContent = label;
                action.href = link;
                secondaryAction.href = '/TMZ%20PODCAST/membership.php';
                secondaryAction.textContent = 'Plans from UGX 500';
            };

            const goToSlide = (index) => {
                currentIndex = (index + slides.length) % slides.length;
                renderSlide(currentIndex);
            };

            const startAutoRotate = () => {
                clearInterval(autoRotate);
                autoRotate = setInterval(() => goToSlide(currentIndex + 1), 5000);
            };

            prevButton?.addEventListener('click', () => {
                goToSlide(currentIndex - 1);
                startAutoRotate();
            });

            nextButton?.addEventListener('click', () => {
                goToSlide(currentIndex + 1);
                startAutoRotate();
            });

            renderSlide(currentIndex);
            startAutoRotate();
        }
    }

    // Add loading spinner if not exists
    if (!document.getElementById('loading-spinner')) {
        const spinner = document.createElement('div');
        spinner.id = 'loading-spinner';
        spinner.className = 'loading-spinner';
        spinner.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(spinner);
    }

    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const text = this.getAttribute('data-tooltip');
            showToast(text, 'info');
        });
    });
});
