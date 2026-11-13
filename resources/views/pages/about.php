<?php
/**
 * About Page View
 * About the podcast
 */
?>

<section class="page-header about-hero">
    <span class="cine-eyebrow">Stories worth staying for</span>
    <h1>Entertainment brings us together.</h1>
    <p>THE DAK FILM PODCAST MEDIA is your place for fresh perspectives on film, culture and the stories behind the screen.</p>
    <a class="btn btn-primary" href="/contact.php">Get in touch</a>
</section>

<section class="about-content about-page">
    <div class="about-section about-mission">
        <span class="cine-eyebrow">Who we are</span>
        <h2>A home for movie lovers</h2>
        <p>THE DAK FILM PODCAST MEDIA brings audiences closer to the films, people and ideas shaping entertainment. We share thoughtful conversations, useful recommendations and stories that make every watch a little more meaningful.</p>
        <?php if (!empty($contactLocation)): ?>
            <p class="about-location"><span>Based in</span> <?php echo htmlspecialchars($contactLocation, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>

    <div class="about-section about-offers">
        <span class="cine-eyebrow">Explore the Zone</span>
        <h2>What you'll find here</h2>
        <div class="features-grid about-features">
            <div class="feature-item">
                <span class="about-feature-number">01</span>
                <h3>Podcast episodes</h3>
                <p>Regular episodes featuring interviews, discussions, and insights into the entertainment industry.</p>
            </div>
            <div class="feature-item">
                <span class="about-feature-number">02</span>
                <h3>Movie reviews</h3>
                <p>Professional reviews and ratings for the latest films and classic movies.</p>
            </div>
            <div class="feature-item">
                <span class="about-feature-number">03</span>
                <h3>Entertainment news</h3>
                <p>Breaking news, celebrity updates, and industry trends.</p>
            </div>
            <div class="feature-item">
                <span class="about-feature-number">04</span>
                <h3>Live events</h3>
                <p>Live events, premieres, and special screenings.</p>
            </div>
        </div>
    </div>

    <div class="about-section about-connect">
        <div>
            <span class="cine-eyebrow">Stay connected</span>
            <h2>Join the conversation</h2>
            <p>Follow along for new stories, episodes and moments from the world of entertainment.</p>
        </div>
        <div class="social-links">
            <a href="https://twitter.com/tmzpodcast" target="_blank" rel="noopener noreferrer" class="social-link">Twitter <span aria-hidden="true">&rarr;</span></a>
            <a href="https://instagram.com/tmzpodcast" target="_blank" rel="noopener noreferrer" class="social-link">Instagram <span aria-hidden="true">&rarr;</span></a>
            <a href="https://youtube.com/tmzpodcast" target="_blank" rel="noopener noreferrer" class="social-link">YouTube <span aria-hidden="true">&rarr;</span></a>
            <a href="https://facebook.com/tmzpodcast" target="_blank" rel="noopener noreferrer" class="social-link">Facebook <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
</section>
