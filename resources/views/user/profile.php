<?php
/**
 * User Profile View
 */
?>

<section class="user-profile">
    <div class="section-header">
        <h1>Profile Settings</h1>
        <p>Manage your account information</p>
    </div>

    <?php if (isset($_SESSION['profile_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['profile_success']); ?></div>
        <?php unset($_SESSION['profile_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['profile_error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['profile_error']); ?></div>
        <?php unset($_SESSION['profile_error']); ?>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" action="/TMZ%20PODCAST/user/profile.php" class="admin-form" enctype="multipart/form-data">
            <?php echo CSRF::getField(); ?>
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>

            <div class="form-group profile-photo-field">
                <label for="profile_image">Profile picture</label>
                <?php if (!empty($user['profile_image_path'])): ?><img class="profile-photo-preview" src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($user['profile_image_path'], '/')); ?>" alt="Your profile picture"><?php endif; ?>
                <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp">
                <small class="form-help">Use a clear JPG, PNG or WebP image up to 5 MB. This picture appears beside your live messages.</small>
            </div>
            
            <div class="form-group">
                <label for="current_password">Current Password (for password change)</label>
                <input type="password" id="current_password" name="current_password">
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" minlength="8">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Profile</button>
                <a href="/TMZ%20PODCAST/user/dashboard.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</section>
