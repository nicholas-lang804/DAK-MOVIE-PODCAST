<?php
/**
 * Admin Movies Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Movies</h1>
        <a href="/admin/dashboard.php?tab=movies&action=create" class="btn btn-primary">Add Movie</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if ($action === 'create' || $action === 'edit'): ?>
        <div class="form-container">
            <h2><?php echo $action === 'create' ? 'Create Movie' : 'Edit Movie'; ?></h2>
            <form method="POST" action="/admin/dashboard.php?tab=movies" class="admin-form" enctype="multipart/form-data">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $movie['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($movie['title'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="synopsis">Synopsis</label>
                    <textarea id="synopsis" name="synopsis" rows="6"><?php echo htmlspecialchars($movie['synopsis'] ?? ''); ?></textarea>
                </div>

                <div class="form-group movie-cover-select">
                    <label for="movie_cover_file">Movie cover / poster</label>
                    <input type="file" id="movie_cover_file" name="movie_cover_file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small>Movie posters stay with the movie and are not added to the shared media library.</small>
                    <?php if (!empty($movie['poster_path'])): ?>
                        <img class="admin-cover-preview" src="<?php echo htmlspecialchars('/' . ltrim($movie['poster_path'], '/')); ?>" alt="Current movie cover">
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="director">Director</label>
                    <input type="text" id="director" name="director" value="<?php echo htmlspecialchars($movie['director'] ?? ''); ?>">
                </div>

                <div class="form-group movie-genre-select">
                    <label for="genre_id">Movie genre *</label>
                    <select id="genre_id" name="genre_id" required>
                        <option value="">Choose a genre</option>
                        <?php foreach (($genres ?? []) as $genre): ?>
                            <option value="<?php echo (int) $genre['id']; ?>" <?php echo in_array((int)$genre['id'], $selectedGenreIds ?? [], true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($genre['name']); ?> (<?php echo (int)($genre['movie_count'] ?? 0); ?> movies)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Every published movie must belong to a genre so visitors can find it in the catalogue.</small>
                </div>
                
                <div class="form-group">
                    <label for="release_date">Release Date</label>
                    <input type="date" id="release_date" name="release_date" value="<?php echo $movie['release_date'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="runtime_minutes">Runtime (minutes)</label>
                    <input type="number" id="runtime_minutes" name="runtime_minutes" value="<?php echo $movie['runtime_minutes'] ?? ''; ?>">
                </div>

                <div class="form-group movie-video-select">
                    <label for="video_media_id">Movie video</label>
                    <div id="ruMovieVideo" class="ru-upload" data-ru-upload data-target-select="video_media_id">
                        <p class="form-help"><b>Upload a new movie video</b> — sent in 8 MB chunks with a live progress bar and automatic resume. When it finishes, the video is selected automatically below. Keep this page open until it reaches 100%.</p>
                        <div class="ru-picker"><input type="file" id="movie_video_file_pick" accept="video/*,.mp4,.webm,.mov,.mkv,.avi,.ogv,.3gp"></div>
                        <div class="ru-list"></div>
                    </div>
                    <label for="video_media_id" class="form-help">Or choose an existing video from Media Library</label>
                    <select id="video_media_id" name="video_media_id">
                        <option value="">Choose existing video</option>
                        <?php foreach (($videos ?? []) as $video): ?>
                            <option value="<?php echo (int)$video['id']; ?>" <?php echo ((int)($movie['video_media_id'] ?? 0) === (int)$video['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($video['original_filename']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Upload a new full movie (chunked, up to 5 GB) or select an existing video. The upload must reach 100% before you save the movie.</small>
                    <?php if (!empty($movie['video_path'])): ?>
                        <video class="admin-video-preview" controls preload="metadata" src="/video-stream.php?movie_id=<?php echo (int)($movie['id'] ?? 0); ?>"></video>
                    <?php endif; ?>
                </div>
                <script src="/assets/js/resumable-upload.js?v=ru-2"></script>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo ($movie['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo ($movie['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo ($movie['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>

                <div class="movie-access-panel">
                    <div class="form-group">
                        <label for="access_type">Viewing access</label>
                        <select id="access_type" name="access_type">
                            <option value="free" <?php echo ($movie['access_type'] ?? 'free') === 'free' ? 'selected' : ''; ?>>Free to watch (stream only)</option>
                            <option value="paid" <?php echo ($movie['access_type'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid access</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="video_display">Public video placement</label>
                        <select id="video_display" name="video_display">
                            <option value="none" <?php echo (($movie['video_display'] ?? 'video_centre') === 'none') ? 'selected' : ''; ?>>Do not show this movie in public videos</option>
                            <option value="homepage" <?php echo (($movie['video_display'] ?? 'video_centre') === 'homepage') ? 'selected' : ''; ?>>Homepage videos only</option>
                            <option value="video_centre" <?php echo (($movie['video_display'] ?? 'video_centre') === 'video_centre') ? 'selected' : ''; ?>>Video Centre only</option>
                            <option value="both" <?php echo (($movie['video_display'] ?? 'video_centre') === 'both') ? 'selected' : ''; ?>>Homepage and Video Centre</option>
                        </select>
                        <small>Paid movies are hidden from free users in public video feeds regardless of placement.</small>
                    </div>
                    <div class="form-group">
                        <small>Movies are stream-only — downloads are available for music tracks only (see Music → per-track download access).</small>
                    </div>
                    <div id="paid_movie_options" class="paid-movie-options" <?php echo ($movie['access_type'] ?? 'free') === 'paid' ? '' : 'hidden'; ?>>
                        <div class="form-group">
                            <label for="price">Price</label>
                            <input type="number" id="price" name="price" min="0.01" step="0.01" value="<?php echo htmlspecialchars($movie['price'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="price_currency">Currency</label>
                            <select id="price_currency" name="price_currency">
                                <?php foreach (['DZD'=>'Algerian Dinar','AOA'=>'Angolan Kwanza','BWP'=>'Botswana Pula','BIF'=>'Burundian Franc','CVE'=>'Cape Verde Escudo','XAF'=>'Central African CFA Franc','XOF'=>'West African CFA Franc','KMF'=>'Comorian Franc','CDF'=>'Congolese Franc','DJF'=>'Djiboutian Franc','EGP'=>'Egyptian Pound','ERN'=>'Eritrean Nakfa','ETB'=>'Ethiopian Birr','SZL'=>'Eswatini Lilangeni','GMD'=>'Gambian Dalasi','GHS'=>'Ghanaian Cedi','GNF'=>'Guinean Franc','KES'=>'Kenyan Shilling','LSL'=>'Lesotho Loti','LRD'=>'Liberian Dollar','LYD'=>'Libyan Dinar','MGA'=>'Malagasy Ariary','MWK'=>'Malawian Kwacha','MRU'=>'Mauritanian Ouguiya','MUR'=>'Mauritian Rupee','MAD'=>'Moroccan Dirham','MZN'=>'Mozambican Metical','NAD'=>'Namibian Dollar','NGN'=>'Nigerian Naira','RWF'=>'Rwandan Franc','STN'=>'São Tomé Dobra','SCR'=>'Seychellois Rupee','SLE'=>'Sierra Leone Leone','SOS'=>'Somali Shilling','ZAR'=>'South African Rand','SSP'=>'South Sudanese Pound','SDG'=>'Sudanese Pound','TZS'=>'Tanzanian Shilling','TND'=>'Tunisian Dinar','UGX'=>'Ugandan Shilling','ZMW'=>'Zambian Kwacha','USD'=>'US Dollar','EUR'=>'Euro','GBP'=>'Pound Sterling','CHF'=>'Swiss Franc','JPY'=>'Japanese Yen','CNY'=>'Chinese Yuan','INR'=>'Indian Rupee','AUD'=>'Australian Dollar','CAD'=>'Canadian Dollar','NZD'=>'New Zealand Dollar','SGD'=>'Singapore Dollar','HKD'=>'Hong Kong Dollar','AED'=>'UAE Dirham','SAR'=>'Saudi Riyal','QAR'=>'Qatari Riyal','KWD'=>'Kuwaiti Dinar','BHD'=>'Bahraini Dinar','OMR'=>'Omani Rial','ILS'=>'Israeli New Shekel','TRY'=>'Turkish Lira','RUB'=>'Russian Ruble','BRL'=>'Brazilian Real','MXN'=>'Mexican Peso','ARS'=>'Argentine Peso','CLP'=>'Chilean Peso','COP'=>'Colombian Peso','PEN'=>'Peruvian Sol','KRW'=>'South Korean Won','THB'=>'Thai Baht','MYR'=>'Malaysian Ringgit','IDR'=>'Indonesian Rupiah','PHP'=>'Philippine Peso','VND'=>'Vietnamese Dong','PKR'=>'Pakistani Rupee','BDT'=>'Bangladeshi Taka','LKR'=>'Sri Lankan Rupee','NPR'=>'Nepalese Rupee','PLN'=>'Polish Zloty','SEK'=>'Swedish Krona','NOK'=>'Norwegian Krone','DKK'=>'Danish Krone','CZK'=>'Czech Koruna','HUF'=>'Hungarian Forint','RON'=>'Romanian Leu','UAH'=>'Ukrainian Hryvnia'] as $code => $label): ?>
                                    <option value="<?php echo $code; ?>" <?php echo ($movie['price_currency'] ?? 'USD') === $code ? 'selected' : ''; ?>><?php echo $code . ' — ' . $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="form-help">Paid movies require completed Pesapal payment before watching. Movies are stream-only and cannot be downloaded.</p>
                        <p class="form-help movie-access-note"><strong>Important:</strong> this price belongs to the movie product only. The movie poster stays attached to the movie, while the shared media library remains for standalone uploads and public media.</p>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Movie</button>
                    <a href="/admin/dashboard.php?tab=movies" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Director</th>
                        <th>Release Date</th>
                        <th>Cover</th>
                        <th>Video</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movies as $movie): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($movie['title']); ?></td>
                            <td><?php echo htmlspecialchars($movie['director'] ?? 'N/A'); ?></td>
                            <td><?php echo $movie['release_date'] ? date('M d, Y', strtotime($movie['release_date'])) : 'N/A'; ?></td>
                            <td><span class="status-badge <?php echo !empty($movie['poster_path']) || !empty($movie['poster_media_id']) ? 'status-published' : 'status-draft'; ?>"><?php echo !empty($movie['poster_path']) || !empty($movie['poster_media_id']) ? 'Added' : 'Not added'; ?></span></td>
                            <td><span class="status-badge <?php echo !empty($movie['video_media_id']) ? 'status-published' : 'status-draft'; ?>"><?php echo !empty($movie['video_media_id']) ? 'Attached' : 'Not attached'; ?></span></td>
                            <td><span class="status-badge status-<?php echo $movie['status']; ?>"><?php echo ucfirst($movie['status']); ?></span></td>
                            <td>
                                <a href="/admin/dashboard.php?tab=movies&action=edit&id=<?php echo $movie['id']; ?>" class="btn btn-small">Edit</a>
                                <form method="POST" action="/admin/dashboard.php?tab=movies" style="display:inline" onsubmit="return confirm('Delete this movie, its video file and poster? This cannot be undone.')">
                                    <?php echo CSRF::getField(); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo (int)$movie['id']; ?>">
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const access = document.getElementById('access_type');
    const paid = document.getElementById('paid_movie_options');
    const placement = document.getElementById('video_display');
    if (!access || !paid) return;
    const syncPaidFields = () => {
        paid.hidden = access.value !== 'paid';
        if (placement) {
            if (access.value === 'paid') {
                placement.value = 'none';
                placement.disabled = true;
            } else {
                placement.disabled = false;
            }
        }
    };
    access.addEventListener('change', syncPaidFields);
    syncPaidFields();
});
</script>
