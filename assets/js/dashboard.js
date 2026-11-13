// Dashboard Interactive Features

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
    
    // Media purchase functionality
    window.purchaseMedia = function(mediaId, accessType) {
        // TODO: Implement payment integration
        // This would redirect to payment page or open payment modal
        alert('Payment integration required. Media ID: ' + mediaId + ', Access: ' + accessType);
        // Example: window.location.href = 'payment.php?type=media&id=' + mediaId + '&access=' + accessType;
    };
    
    // Podcast purchase functionality
    window.purchasePodcast = function(podcastId) {
        // TODO: Implement payment integration
        alert('Payment integration required. Podcast ID: ' + podcastId);
        // Example: window.location.href = 'payment.php?type=podcast&id=' + podcastId;
    };
    
    // Watch media functionality
    window.watchMedia = function(mediaId) {
        // TODO: Implement video player
        alert('Opening video player for Media ID: ' + mediaId);
        // Example: Open modal with video player
    };
    
    // Download media functionality
    window.downloadMedia = function(mediaId) {
        // TODO: Implement download functionality
        alert('Downloading Media ID: ' + mediaId);
        // Example: window.location.href = 'download.php?id=' + mediaId;
    };
    
    // Watch podcast stream functionality
    window.watchPodcast = function(podcastId) {
        // TODO: Implement stream player
        alert('Opening stream player for Podcast ID: ' + podcastId);
        // Example: Open modal with stream player
    };
    
    // Auto-refresh adverts every 5 minutes
    setInterval(function() {
        // TODO: Implement AJAX refresh of adverts
        console.log('Refreshing adverts...');
    }, 300000);
    
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
    
    // Lazy load images and videos
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src], video[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Video player enhancements
    const videos = document.querySelectorAll('video');
    videos.forEach(video => {
        // Add custom controls if needed
        video.addEventListener('play', function() {
            // Pause other videos when one starts playing
            videos.forEach(v => {
                if (v !== video && !v.paused) {
                    v.pause();
                }
            });
        });
    });
});
