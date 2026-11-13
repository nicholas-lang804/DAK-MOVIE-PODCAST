<?php
/**
 * Contact Page View
 * Contact form
 */
?>

<section class="page-header contact-hero">
    <span class="cine-eyebrow">We'd love to hear from you</span>
    <h1>Let's start a conversation.</h1>
    <p>Questions, ideas, partnerships or feedback? Send our team a message and we'll be glad to connect.</p>
</section>

<section class="contact-content contact-page">
    <div class="contact-info contact-panel">
        <span class="cine-eyebrow">Get in touch</span>
        <h2>Contact information</h2>
        <p class="contact-intro">Reach out using the details below, or send us a note using the form.</p>
        <div class="contact-details">
            <div class="contact-item">
                <span class="contact-item-label">Email</span>
                <?php if (!empty($contactEmail)): ?>
                    <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endif; ?>
            </div>
            <div class="contact-item">
                <span class="contact-item-label">Phone</span>
                <?php if (!empty($contactPhone)): ?>
                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+*#,;]/', '', $contactPhone), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endif; ?>
            </div>
            <div class="contact-item">
                <span class="contact-item-label">Location</span>
                <?php if (!empty($contactLocation)): ?>
                    <p><?php echo htmlspecialchars($contactLocation, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <a class="contact-about-link" href="/TMZ%20PODCAST/about.php">More about THE DAK FILM PODCAST MEDIA <span aria-hidden="true">&rarr;</span></a>
    </div>

    <div class="contact-form-container contact-panel">
        <span class="cine-eyebrow">Your message</span>
        <h2>Send us a message</h2>
        <p class="contact-intro">Tell us a little about how we can help.</p>
        <?php if (isset($_SESSION['contact_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['contact_success']); ?></div>
            <?php unset($_SESSION['contact_success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['contact_error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['contact_error']); ?></div>
            <?php unset($_SESSION['contact_error']); ?>
        <?php endif; ?>
        
        <form method="POST" action="/TMZ%20PODCAST/contact.php" class="contact-form">
            <?php echo CSRF::getField(); ?>
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject">
            </div>
            <div class="form-group">
                <label for="enquiry_type">Enquiry Type</label>
                <select id="enquiry_type" name="enquiry_type">
                    <option value="general">General Inquiry</option>
                    <option value="business">Business Inquiry</option>
                    <option value="interview">Interview Request</option>
                    <option value="sponsorship">Sponsorship</option>
                    <option value="collaboration">Collaboration</option>
                </select>
            </div>
            <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message" name="message" rows="6" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send message</button>
        </form>
    </div>
</section>
