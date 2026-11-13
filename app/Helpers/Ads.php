<?php
/**
 * Advert slots for the public site and member dashboard.
 *
 * tmz_ad_slot('homepage', 'leaderboard')  -> wide strip of adverts (grid fills the width)
 * tmz_ad_slot('sidebar',  'rail', 3)      -> stacked adverts for side columns
 *
 * Real adverts from the admin (Advertisements) always come first. If fewer than $min are active,
 * the remaining space is filled with TMZ house promos, so no area is ever left blank.
 */

if (!function_exists('tmz_safe_ad_url')) {
    function tmz_safe_ad_url($url) {
        $url = trim((string)$url);
        if ($url === '') return '';
        if (preg_match('~^https?://~i', $url) || ($url[0] === '/' && substr($url, 0, 2) !== '//')) return $url;
        return '';
    }
}

if (!function_exists('tmz_house_promos')) {
    function tmz_house_promos() {
        return [
            ['kicker' => 'All Access', 'title' => 'Unlimited movies from UGX 500', 'cta' => 'See plans', 'url' => '/membership.php', 'tone' => 'blue'],
            ['kicker' => 'Live events', 'title' => 'Get your tickets before they sell out', 'cta' => 'Book now', 'url' => '/events.php', 'tone' => 'red'],
            ['kicker' => 'Advertise', 'title' => 'Put your brand in front of movie lovers', 'cta' => 'Talk to us', 'url' => '/contact.php', 'tone' => 'blue'],
            ['kicker' => 'New this week', 'title' => 'Fresh movies added to the Zone', 'cta' => 'Watch now', 'url' => '/movies.php', 'tone' => 'red'],
        ];
    }
}

if (!function_exists('tmz_ad_slot')) {
    function tmz_ad_slot($placement = 'homepage', $variant = 'leaderboard', $min = 3) {
        $ads = [];
        try {
            require_once __DIR__ . '/../Models/Advertisement.php';
            $ads = (new Advertisement())->getActiveByPlacement($placement);
        } catch (Throwable $e) {
            error_log('Ad slot: ' . $e->getMessage());
        }
        $max = $variant === 'rail' ? max(1, (int)$min) : 6;
        $ads = array_slice($ads, 0, $max);
        $promos = tmz_house_promos();
        $offset = abs(crc32($placement . $variant)) % count($promos);
        $items = [];
        foreach ($ads as $ad) { $items[] = ['real' => true, 'ad' => $ad]; }
        for ($i = 0; count($items) < (int)$min; $i++) {
            $items[] = ['real' => false, 'promo' => $promos[($offset + $i) % count($promos)]];
        }
        $h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
        ?>
        <aside class="tmz-ads tmz-ads--<?php echo $h($variant); ?>" aria-label="Adverts">
            <span class="tmz-ads-label">Adverts</span>
            <div class="tmz-ads-grid">
                <?php foreach ($items as $item): ?>
                    <?php if ($item['real']):
                        $ad = $item['ad'];
                        $url = tmz_safe_ad_url($ad['target_url'] ?? '');
                        $img = !empty($ad['image_path']) ? '/' . ltrim($ad['image_path'], '/') : '';
                        $title = $ad['title'] ?: ($ad['sponsor_name'] ?? 'Advertisement'); ?>
                        <<?php echo $url ? 'a href="' . $h($url) . '" target="_blank" rel="sponsored noopener"' : 'div'; ?> class="tmz-ad tmz-ad--real<?php echo $img ? ' has-image' : ''; ?>">
                            <?php if ($img): ?>
                                <img src="<?php echo $h($img); ?>" alt="<?php echo $h($title); ?>" loading="lazy">
                            <?php else: ?>
                                <strong><?php echo $h($title); ?></strong>
                                <?php if (!empty($ad['sponsor_name'])): ?><small><?php echo $h($ad['sponsor_name']); ?></small><?php endif; ?>
                            <?php endif; ?>
                        </<?php echo $url ? 'a' : 'div'; ?>>
                    <?php else: $p = $item['promo']; ?>
                        <a class="tmz-ad tmz-ad--promo tone-<?php echo $h($p['tone']); ?>" href="<?php echo $h($p['url']); ?>">
                            <small><?php echo $h($p['kicker']); ?></small>
                            <strong><?php echo $h($p['title']); ?></strong>
                            <span><?php echo $h($p['cta']); ?> &rarr;</span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php
    }
}
?>
