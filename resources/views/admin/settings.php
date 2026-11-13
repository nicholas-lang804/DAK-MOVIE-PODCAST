<?php
/**
 * Admin Settings View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Site Settings</h1>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=settings" class="admin-form">
            <?php echo CSRF::getField(); ?>

            <div class="settings-subsection">
                <h2>All-access subscription prices</h2>
                <p class="form-help">These plans unlock paid movies for a limited period. Prices and discounts are controlled here. No lifetime plan is offered.</p>
                <?php foreach (($subscriptionPlans ?? []) as $plan): ?>
                    <div class="subscription-price-row">
                        <div><strong><?php echo htmlspecialchars($plan['name']); ?></strong><small><?php echo (int)$plan['duration_months']; ?> month<?php echo (int)$plan['duration_months'] === 1 ? '' : 's'; ?></small></div>
                        <input type="number" min="0.01" step="0.01" name="subscription_price[<?php echo htmlspecialchars($plan['plan_key']); ?>]" value="<?php echo htmlspecialchars($plan['price']); ?>" aria-label="<?php echo htmlspecialchars($plan['name']); ?> price">
                        <input type="text" maxlength="3" name="subscription_currency[<?php echo htmlspecialchars($plan['plan_key']); ?>]" value="<?php echo htmlspecialchars($plan['currency']); ?>" aria-label="Currency">
                        <input type="number" min="0" max="80" step="0.01" name="subscription_discount[<?php echo htmlspecialchars($plan['plan_key']); ?>]" value="<?php echo htmlspecialchars($plan['discount_percent']); ?>" aria-label="Discount percentage">
                        <span class="form-help">% discount</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="settings-subsection">
                <h2>Pesapal payment gateway</h2>
                <p class="form-help">Store your Pesapal credentials here. Keep the secret private and use sandbox mode until your account is ready for live payments.</p>
                <div class="form-group">
                    <label for="pesapal_consumer_key">Consumer Key</label>
                    <input type="text" id="pesapal_consumer_key" name="pesapal_consumer_key" value="<?php echo htmlspecialchars($settings['pesapal_consumer_key'] ?? ''); ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="pesapal_consumer_secret">Consumer Secret</label>
                    <input type="password" id="pesapal_consumer_secret" name="pesapal_consumer_secret" value="<?php echo htmlspecialchars($settings['pesapal_consumer_secret'] ?? ''); ?>" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="pesapal_environment">Environment</label>
                    <select id="pesapal_environment" name="pesapal_environment">
                        <option value="sandbox" <?php echo ($settings['pesapal_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>Sandbox / testing</option>
                        <option value="live" <?php echo ($settings['pesapal_environment'] ?? '') === 'live' ? 'selected' : ''; ?>>Live / production</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pesapal_notification_id">Registered notification ID</label>
                    <input type="text" id="pesapal_notification_id" name="pesapal_notification_id" value="<?php echo htmlspecialchars($settings['pesapal_notification_id'] ?? ''); ?>" placeholder="Pesapal IPN notification ID">
                </div>
                <div class="form-group">
                    <label for="pesapal_callback_url">Callback URL</label>
                    <input type="url" id="pesapal_callback_url" name="pesapal_callback_url" value="<?php echo htmlspecialchars($settings['pesapal_callback_url'] ?? ''); ?>" placeholder="https://your-domain/payment-callback.php">
                </div>
                <div class="form-group">
                    <label for="pesapal_ipn_url">IPN / callback URL</label>
                    <input type="url" id="pesapal_ipn_url" name="pesapal_ipn_url" value="<?php echo htmlspecialchars($settings['pesapal_ipn_url'] ?? ''); ?>" placeholder="https://your-domain/pesapal-ipn.php">
                </div>
            </div>

            <div class="settings-subsection livekit-settings">
                <h2>LiveKit live broadcasting</h2>
                <p class="form-help">Add the LiveKit Cloud values from your LiveKit project. They are used only by the server to create secure host and viewer tokens. The API secret is never sent to the browser.</p>
                <div class="form-group">
                    <label for="livekit_url">LiveKit server URL</label>
                    <input type="url" id="livekit_url" name="livekit_url" value="<?php echo htmlspecialchars($settings['livekit_url'] ?? ''); ?>" placeholder="https://your-project.livekit.cloud" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="livekit_api_key">LiveKit API key</label>
                    <input type="text" id="livekit_api_key" name="livekit_api_key" value="<?php echo htmlspecialchars($settings['livekit_api_key'] ?? ''); ?>" placeholder="API key from LiveKit Cloud" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="livekit_api_secret">LiveKit API secret</label>
                    <input type="password" id="livekit_api_secret" name="livekit_api_secret" value="" placeholder="<?php echo !empty($settings['livekit_api_secret']) ? 'Saved — enter a new secret only to replace it' : 'API secret from LiveKit Cloud'; ?>" autocomplete="new-password">
                </div>
                <p class="form-help"><strong>Where to find them:</strong> LiveKit Cloud dashboard → your project → Settings / API keys. Save this page, then open Live Studio and prepare a broadcast.</p>
                <button type="button" class="btn btn-outline" id="testLivekit">Test LiveKit connection</button>
                <span id="livekitTestResult" class="form-help" aria-live="polite"></span>
            </div>

            <div class="settings-subsection livekit-settings">
                <h2>LiveKit recording storage</h2>
                <p class="form-help">Recordings need an S3-compatible bucket that your LiveKit Egress worker can write to. These values are kept server-side and are never sent to the browser. Leave either secret field blank to keep its saved value. Apache/PHP environment values, when set, take precedence over values saved here.</p>
                <div class="form-group">
                    <label for="livekit_recording_s3_access_key">Storage access key</label>
                    <input type="password" id="livekit_recording_s3_access_key" name="livekit_recording_s3_access_key" value="" placeholder="<?php echo !empty($settings['livekit_recording_s3_access_key']) ? 'Saved — enter a new key only to replace it' : 'S3-compatible storage access key'; ?>" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="livekit_recording_s3_secret">Storage secret key</label>
                    <input type="password" id="livekit_recording_s3_secret" name="livekit_recording_s3_secret" value="" placeholder="<?php echo !empty($settings['livekit_recording_s3_secret']) ? 'Saved — enter a new secret only to replace it' : 'S3-compatible storage secret key'; ?>" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="livekit_recording_s3_region">Storage region</label>
                    <input type="text" id="livekit_recording_s3_region" name="livekit_recording_s3_region" value="<?php echo htmlspecialchars($settings['livekit_recording_s3_region'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="For example: us-east-1">
                </div>
                <div class="form-group">
                    <label for="livekit_recording_s3_bucket">Bucket name</label>
                    <input type="text" id="livekit_recording_s3_bucket" name="livekit_recording_s3_bucket" value="<?php echo htmlspecialchars($settings['livekit_recording_s3_bucket'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Private recording bucket">
                </div>
                <div class="form-group">
                    <label for="livekit_recording_s3_endpoint">Custom storage endpoint (optional)</label>
                    <input type="url" id="livekit_recording_s3_endpoint" name="livekit_recording_s3_endpoint" value="<?php echo htmlspecialchars($settings['livekit_recording_s3_endpoint'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://storage.example.com">
                </div>
                <div class="form-group">
                    <label for="livekit_recording_s3_force_path_style">Storage URL style</label>
                    <select id="livekit_recording_s3_force_path_style" name="livekit_recording_s3_force_path_style">
                        <option value="false" <?php echo ($settings['livekit_recording_s3_force_path_style'] ?? 'false') !== 'true' ? 'selected' : ''; ?>>Virtual-hosted style (default)</option>
                        <option value="true" <?php echo ($settings['livekit_recording_s3_force_path_style'] ?? 'false') === 'true' ? 'selected' : ''; ?>>Path style (required by some providers)</option>
                    </select>
                </div>
                <p class="form-help">In your storage provider, create a private bucket and access credentials with permission to upload, read, and delete objects. The LiveKit Egress worker must also be configured with credentials and network access to that bucket. Save these settings, then check the recording status in Live Studio.</p>
            </div>
            
            <div class="settings-subsection">
                <h2>Downloads</h2>
                <p class="form-help">Controls whether visitors can download content (movies, videos, podcast audio, music and images). When this is off, every download button is hidden and download links are blocked. Paid movies still require completed payment before they can be downloaded.</p>
                <div class="form-group">
                    <label style="font-weight:normal"><input type="checkbox" name="downloads_enabled" value="1" <?php echo !empty($settings['downloads_enabled']) && $settings['downloads_enabled'] !== '0' ? 'checked' : ''; ?>> Allow downloads on the site</label>
                </div>
            </div>

            <div class="settings-subsection">
                <h2>Video compression</h2>
                <p class="form-help">Automatically generates a 720p H.264 rendition (roughly 50–70% smaller) for uploaded videos — movies, episodes, music videos and media library — while preserving every original untouched. Renditions are tracked in Admin → Media Library → Video processing jobs, with retry on failure. Requires FFmpeg on the server: leave the path empty to auto-detect, or enter the full path to ffmpeg (e.g. C:\ffmpeg\bin\ffmpeg.exe). If FFmpeg is not installed, uploads work normally and originals are never modified.</p>
                <div class="form-group">
                    <label style="font-weight:normal"><input type="checkbox" name="video_compression_enabled" value="1" <?php echo !isset($settings['video_compression_enabled']) || $settings['video_compression_enabled'] !== '0' ? 'checked' : ''; ?>> Compress uploaded videos automatically</label>
                </div>
                <div class="form-group">
                    <label for="ffmpeg_path">FFmpeg path (optional)</label>
                    <input type="text" id="ffmpeg_path" name="ffmpeg_path" value="<?php echo htmlspecialchars($settings['ffmpeg_path'] ?? ''); ?>" placeholder="Auto-detect (e.g. C:\ffmpeg\bin\ffmpeg.exe)">
                </div>
            </div>

            <div class="form-group">
                <label for="site_name">Site Name</label>
                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="site_description">Site Description</label>
                <textarea id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
            </div>
            
            <div class="settings-subsection">
                <h2>Public contact details</h2>
                <p class="form-help">These details are shown on the Contact page and throughout the public and member areas.</p>
                <div class="form-group">
                    <label for="contact_email">Contact Email</label>
                    <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="contact_phone">Contact Phone Number(s)</label>
                    <input type="text" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="site_location">Location</label>
                    <input type="text" id="site_location" name="site_location" value="<?php echo htmlspecialchars($settings['site_location'] ?? 'Kampala, Uganda'); ?>" placeholder="Kampala, Uganda">
                </div>
            </div>
            
            <div class="form-group">
                <label for="social_twitter">Twitter URL</label>
                <input type="url" id="social_twitter" name="social_twitter" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="social_instagram">Instagram URL</label>
                <input type="url" id="social_instagram" name="social_instagram" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="social_youtube">YouTube URL</label>
                <input type="url" id="social_youtube" name="social_youtube" value="<?php echo htmlspecialchars($settings['social_youtube'] ?? ''); ?>">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</section>
<script>
document.getElementById('testLivekit')?.addEventListener('click', async function(){
  const result=document.getElementById('livekitTestResult'); result.textContent='Testing…'; this.disabled=true;
  try { const response=await fetch('/TMZ%20PODCAST/api/livekit-test.php',{headers:{'Accept':'application/json'}}); const data=await response.json(); result.textContent=data.message; result.dataset.state=data.ok?'success':'error'; }
  catch(e) { result.textContent='Could not run the connection test.'; result.dataset.state='error'; }
  this.disabled=false;
});
</script>
