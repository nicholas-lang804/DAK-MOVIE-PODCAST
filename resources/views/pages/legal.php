<?php
/**
 * Privacy Policy, Terms of Use and Cookie Notice.
 * NOTE: These are plain-language starting templates written from how this site works.
 * Have them reviewed by a qualified lawyer for your country (e.g. Uganda's Data Protection and
 * Privacy Act, 2019) before relying on them, and edit the sections below to match your business.
 */
$site = 'THE DAK FILM PODCAST MEDIA';
$updated = 'November 2026';
?>
<article class="legal-page">
    <header class="cine-page-hero">
        <span class="cine-eyebrow">Legal</span>
        <h1><?php echo htmlspecialchars($legalTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Last updated: <?php echo $updated; ?></p>
    </header>
    <nav class="cine-pills legal-nav" aria-label="Legal pages">
        <a href="/TMZ%20PODCAST/privacy.php" class="<?php echo $legalDoc === 'privacy' ? 'is-active' : ''; ?>">Privacy</a>
        <a href="/TMZ%20PODCAST/terms.php" class="<?php echo $legalDoc === 'terms' ? 'is-active' : ''; ?>">Terms</a>
        <a href="/TMZ%20PODCAST/cookies.php" class="<?php echo $legalDoc === 'cookies' ? 'is-active' : ''; ?>">Cookies</a>
    </nav>

    <div class="legal-body">
    <?php if ($legalDoc === 'privacy'): ?>
        <h2>What we collect</h2>
        <p>When you create an account we collect your name or username, email address and password (stored securely hashed). If you sign in with Google we receive your name, email and profile picture from Google. If you buy a movie, event ticket or membership, payment is handled by our payment provider; we keep a record of the purchase but not your full card or mobile-money details.</p>
        <h2>Live shows</h2>
        <p>Joining a live show does not turn on your camera or microphone. Devices are only used if the host invites you to speak and you allow it in your browser. The host may record a live show, and live captions and transcripts may be saved. Chat messages, questions, poll votes and reactions you send during a show are stored with the show. We count how many people are watching and how long they stay to understand audience size.</p>
        <h2>How we use it</h2>
        <p>To run your account, deliver content you have access to, process payments and tickets, send notices you ask for (for example show reminders), keep the site secure, and improve the service. We do not sell your personal information.</p>
        <h2>Who we share it with</h2>
        <p>Only with service providers that help us run the site, such as payment processing, live video delivery, email and hosting, and where the law requires it. Videos from YouTube or Vimeo may set their own cookies when you play them.</p>
        <h2>Your choices</h2>
        <p>You can view and update your profile in your dashboard, ask us to correct or delete your account and personal data, and unsubscribe from the newsletter at any time. Contact us through the <a href="/TMZ%20PODCAST/contact.php">contact page</a>.</p>
        <h2>Keeping data safe and how long we keep it</h2>
        <p>We use access controls and encryption in transit where available. We keep account data while your account is active and payment records for as long as the law requires.</p>
        <h2>Children</h2>
        <p>This service is not directed at children under 13, and we do not knowingly collect their personal information.</p>
        <h2>Changes</h2>
        <p>We may update this policy and will change the date above when we do.</p>

    <?php elseif ($legalDoc === 'terms'): ?>
        <h2>Using <?php echo $site; ?></h2>
        <p>By using this site you agree to these terms. You must provide accurate account details and keep your password private. You are responsible for activity on your account.</p>
        <h2>Free and paid content</h2>
        <p>Some movies, shows and events are free and some are paid. A purchase gives you personal, non-transferable access for the period or title shown at checkout. All-access membership gives access for the plan period. Prices and currencies are shown before you pay. If something is not delivered as described, contact us and we will work with you to put it right.</p>
        <h2>Live shows and community conduct</h2>
        <p>Be respectful. Do not post unlawful, hateful, harassing, sexual or spam content, and do not share other people's personal information. Hosts and moderators may delete messages, mute, remove or ban participants, and live shows may be recorded. Do not record, copy or redistribute paid streams or downloads.</p>
        <h2>Our content and yours</h2>
        <p>Movies, podcasts, images, logos and text are owned by us or our licensors and protected by copyright. You keep ownership of what you post, and you give us permission to display it on the site and in show replays.</p>
        <h2>Events and tickets</h2>
        <p>Tickets are for the named event and may be checked on entry. Event details can change; if an event is cancelled we will tell you how refunds or credits will work.</p>
        <h2>Availability and liability</h2>
        <p>We work to keep the service running but cannot promise it will always be uninterrupted or error-free. To the extent the law allows, we are not liable for indirect losses arising from use of the site.</p>
        <h2>Ending access</h2>
        <p>We may suspend accounts that break these terms. You can stop using the service at any time.</p>
        <h2>Contact</h2>
        <p>Questions about these terms: use our <a href="/TMZ%20PODCAST/contact.php">contact page</a>.</p>

    <?php else: ?>
        <h2>What are cookies?</h2>
        <p>Cookies are small files saved by your browser. We use a few to make the site work.</p>
        <h2>The cookies we use</h2>
        <ul>
            <li><strong>Essential:</strong> a session cookie that keeps you signed in and protects forms from forgery. The site cannot work without it.</li>
            <li><strong>Preferences:</strong> your browser remembers small settings on your device, for example that you have seen this notice or your caption and audio choices in a live show.</li>
            <li><strong>Third-party media:</strong> if a page shows a YouTube or Vimeo video, those services may set their own cookies when you press play.</li>
        </ul>
        <p>We do not use advertising-tracking cookies from third-party ad networks. Adverts on this site are placed by us.</p>
        <h2>Your choices</h2>
        <p>You can block or delete cookies in your browser settings, but sign-in will stop working if you block the essential cookie.</p>
        <p>More detail is in our <a href="/TMZ%20PODCAST/privacy.php">Privacy Policy</a>.</p>
    <?php endif; ?>
    </div>
</article>
