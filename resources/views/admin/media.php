<?php
/**
 * Admin Media Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Media Library</h1>
        <a href="/admin/dashboard.php?tab=media&action=upload" class="btn btn-primary">Upload Media</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div id="resumableUpload" class="ru-upload">
        <h2>Upload video (resumable — desktop &amp; mobile)</h2>
        <p class="form-help">8 MB chunks with automatic retry and resume. Works on Android Chrome, iPhone Safari and desktop. Keep the screen awake on phones.</p>
        <div class="ru-warnings alert alert-error" hidden></div>
        <div class="ru-picker"><input type="file" accept="video/*,.mp4,.webm,.mov,.mkv,.avi,.ogv,.3gp" multiple></div>
        <div class="ru-list"></div>
    </div>
    <script src="/assets/js/resumable-upload.js?v=ru-5"></script>

    <?php if ($action === 'upload'): ?>
        <div class="form-container">
            <h2>Upload Media</h2>
            <form method="POST" action="/admin/dashboard.php?tab=media" enctype="multipart/form-data" class="admin-form">
                <?php echo CSRF::getField(); ?>
                
                <div class="form-group">
                    <label for="file">Select File *</label>
                    <input type="file" id="media_file" name="media_file" accept="image/*,audio/*,video/*,application/pdf" required data-media-preview>
                    <small>Allowed types: JPG, PNG, GIF, MP3, MP4, PDF. Max size: 50MB</small>
                    <div id="media-preview" class="media-upload-preview" hidden></div>
                </div>
                
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="media_type">Media Type</label>
                    <select id="media_type" name="media_type">
                        <option value="image">Image</option>
                        <option value="audio">Audio</option>
                        <option value="video">Video</option>
                        <option value="document">Document</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="alt_text">Alt text</label>
                    <input type="text" id="alt_text" name="alt_text" maxlength="255" placeholder="Describe the media for accessibility">
                </div>
                
                <div class="form-actions">
                    <input type="hidden" name="action" value="upload">
                    <button type="submit" class="btn btn-primary">Upload</button>
                    <a href="/admin/dashboard.php?tab=media" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($media as $item): ?>
                        <tr>
                            <td>
                                <?php $mediaUrl = '/' . ltrim($item['storage_path'], '/'); ?>
                                <?php if ($item['media_type'] === 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($mediaUrl); ?>" alt="<?php echo htmlspecialchars($item['alt_text'] ?? $item['original_filename']); ?>" class="media-thumbnail" loading="lazy">
                                <?php elseif ($item['media_type'] === 'video'): ?>
                                    <video src="/video-stream.php?media_id=<?php echo (int)$item['id']; ?>" class="media-thumbnail" controls preload="metadata"></video>
                                <?php elseif ($item['media_type'] === 'audio'): ?>
                                    <audio src="<?php echo htmlspecialchars($mediaUrl); ?>" controls></audio>
                                <?php else: ?>
                                    <span class="media-icon"><?php echo strtoupper($item['media_type']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['title'] ?? 'Untitled'); ?></td>
                            <td><?php echo ucfirst($item['media_type']); ?></td>
                            <td><?php echo number_format($item['file_size'] / 1024 / 1024, 2); ?> MB</td>
                            <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                            <td>
                                <a href="<?php echo $item['media_type'] === 'video' ? '/video-stream.php?media_id=' . (int)$item['id'] : htmlspecialchars($mediaUrl); ?>" target="_blank" class="btn btn-small">View</a>
                                <form method="POST" action="/admin/dashboard.php?tab=media" style="display:inline" onsubmit="return confirm('Delete this file permanently?')">
                                    <?php echo CSRF::getField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="media_id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                                <form method="POST" action="/admin/dashboard.php?tab=media" style="display:inline" onsubmit="return confirm('This media is used somewhere. Delete it anyway? Pages using it may break.')">
                                    <?php echo CSRF::getField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="media_id" value="<?php echo (int) $item['id']; ?>">
                                    <input type="hidden" name="force" value="1">
                                    <button type="submit" class="btn btn-small btn-danger">Delete anyway</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 style="margin-top:2rem">Video processing jobs</h2>
        <form method="POST" action="/admin/dashboard.php?tab=media" style="margin:.5rem 0" onsubmit="return confirm('Queue 720p renditions for all library videos that do not have one yet? Originals are preserved.')"><?php echo CSRF::getField(); ?>
            <input type="hidden" name="action" value="bulk_renditions"><button class="btn btn-outline" type="submit">Generate missing 720p renditions (bulk)</button></form>
        <p class="form-help">Non-destructive 720p renditions. Originals are never modified. FFmpeg must be installed (Admin → Settings → Video compression).</p>
        <div class="table-container">
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Kind</th><th>Status</th><th>Progress</th><th>Size</th><th>Attempts</th><th>Error</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach (($uploadJobs ?? []) as $j):
                    $cls = $j['status'] === 'ready' ? 'published' : ($j['status'] === 'failed' ? 'draft' : 'pending');
                ?>
                    <tr>
                        <td><?php echo (int)$j['id']; ?></td>
                        <td><?php echo htmlspecialchars($j['kind']); ?></td>
                        <td><span class="status-badge status-<?php echo $cls; ?>"><?php echo htmlspecialchars($j['status']); ?></span></td>
                        <td><?php echo (int)$j['progress']; ?>%</td>
                        <td><?php echo $j['bytes_total'] ? number_format($j['bytes_total'] / 1048576, 1) . ' MB' : '—'; ?></td>
                        <td><?php echo (int)$j['attempts']; ?>/<?php echo (int)$j['max_attempts']; ?></td>
                        <td style="max-width:260px;font-size:.8rem"><?php echo htmlspecialchars($j['last_error'] ?? ''); ?></td>
                        <td>
                            <?php if (in_array($j['status'], ['failed', 'queued'], true)): ?>
                            <form method="POST" action="/admin/dashboard.php?tab=media" style="display:inline"><?php echo CSRF::getField(); ?>
                                <input type="hidden" name="action" value="retry_job"><input type="hidden" name="job_id" value="<?php echo (int)$j['id']; ?>">
                                <button class="btn btn-small btn-outline" type="submit">Retry</button></form>
                            <?php endif; ?>
                            <?php if (in_array($j['status'], ['queued', 'failed'], true)): ?>
                            <form method="POST" action="/admin/dashboard.php?tab=media" style="display:inline"><?php echo CSRF::getField(); ?>
                                <input type="hidden" name="action" value="cancel_job"><input type="hidden" name="job_id" value="<?php echo (int)$j['id']; ?>">
                                <button class="btn btn-small" type="submit">Cancel</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($uploadJobs)): ?><tr><td colspan="8">No processing jobs yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php
    require_once __DIR__ . '/../../../app/Services/BackupManager.php';
    $backupList = BackupManager::list();
    $lastBackup = BackupManager::lastBackupAt();
    $backupAge = $lastBackup ? time() - strtotime($lastBackup) : PHP_INT_MAX;
    ?>
    <h2 style="margin-top:2rem">Backups</h2>
    <?php if ($backupAge > 7 * 86400): ?>
        <div class="alert alert-error">No backup in the last 7 days<?php echo $lastBackup ? ' (last: ' . htmlspecialchars($lastBackup) . ')' : '' ?>. Create one now — and copy it offsite.</div>
    <?php else: ?>
        <p class="form-help">Last backup: <?php echo $lastBackup ? htmlspecialchars($lastBackup) : 'never'; ?>. A backup on this server alone is <b>not</b> safe — download bundles and keep a copy on another machine or drive.</p>
    <?php endif; ?>
    <form method="POST" action="/admin/dashboard.php?tab=media" style="margin:.5rem 0" onsubmit="return confirm('Create a database dump + media manifest backup now?')"><?php echo CSRF::getField(); ?>
        <input type="hidden" name="action" value="run_backup">
        <label style="font-size:.85rem"><input type="checkbox" name="backup_checksum" value="1"> Include SHA-256 of every media file (slower, thorough)</label><br>
        <button class="btn btn-primary" type="submit">Create backup now</button></form>
    <p class="form-help">CLI equivalent: <code>php tools/backup.php [--checksum] [--keep=N]</code>. Restore: import <code>database.sql</code> + copy media files back per <code>media-manifest.json</code>.</p>
    <?php if ($backupList): ?>
    <table class="admin-table" style="margin-top:1rem">
        <thead><tr><th>Bundle</th><th>Created</th><th>Contents</th><th>Download</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($backupList, 0, 10) as $b): $st = $b['stats'] ?? []; ?>
            <tr>
                <td><?php echo htmlspecialchars($b['name']); ?></td>
                <td><?php echo htmlspecialchars($b['created']); ?></td>
                <td style="font-size:.8rem"><?php echo (int)($st['tables'] ?? 0); ?> tables · <?php echo (int)($st['media_files'] ?? 0); ?> media files · <?php echo (int)($st['missing_files'] ?? 0); ?> missing</td>
                <td><?php foreach (['database.sql', 'media-manifest.json', 'files-manifest.txt', 'backup-meta.json', 'bundle-checksums.txt'] as $bf): if (in_array($bf, $b['files'], true)): ?>
                    <form method="POST" action="/admin/dashboard.php?tab=media" style="display:inline"><?php echo CSRF::getField(); ?>
                        <input type="hidden" name="action" value="download_backup"><input type="hidden" name="bundle" value="<?php echo htmlspecialchars($b['name']); ?>"><input type="hidden" name="file" value="<?php echo $bf; ?>">
                        <button class="btn btn-small btn-outline" type="submit"><?php echo $bf; ?></button></form>
                <?php endif; endforeach; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
