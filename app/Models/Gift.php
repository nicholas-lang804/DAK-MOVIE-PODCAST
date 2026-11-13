<?php
/**
 * Gift Model — virtual gifts users can send to the host or fellow live participants.
 * Each gift has a USD base price; senders pay in their chosen currency (converted via gift_currency_rates).
 * Tables: gifts, gift_currency_rates, gift_orders
 */
require_once __DIR__ . '/../../config/database.php';

class Gift {
    private $pdo;
    private static $ready = false;

    public function __construct() {
        $this->pdo = getDB();
        $this->ensureSchema();
    }

    private function ensureSchema() {
        if (self::$ready) return;
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS gifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            emoji VARCHAR(16) NOT NULL DEFAULT '🎁',
            price_usd DECIMAL(12,2) NOT NULL DEFAULT 1.00,
            category VARCHAR(40) NOT NULL DEFAULT 'fun',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS gift_currency_rates (
            code VARCHAR(8) NOT NULL PRIMARY KEY,
            name VARCHAR(60) NOT NULL DEFAULT '',
            rate_to_usd DECIMAL(16,6) NOT NULL DEFAULT 1.000000
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS gift_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(80) NOT NULL,
            payment_id INT NULL,
            live_id INT NOT NULL DEFAULT 0,
            sender_id INT NOT NULL,
            sender_name VARCHAR(120) NOT NULL DEFAULT '',
            recipient_type VARCHAR(20) NOT NULL DEFAULT 'host',
            recipient_user_id INT NULL,
            recipient_name VARCHAR(120) NOT NULL DEFAULT 'The Host',
            gift_id INT NOT NULL,
            gift_name VARCHAR(120) NOT NULL,
            emoji VARCHAR(16) NOT NULL DEFAULT '🎁',
            amount DECIMAL(14,2) NOT NULL,
            currency VARCHAR(8) NOT NULL,
            message VARCHAR(300) NOT NULL DEFAULT '',
            status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            UNIQUE KEY uq_gift_orders_reference (reference),
            INDEX idx_gift_orders_live (live_id, status),
            INDEX idx_gift_orders_sender (sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->seedDefaults();
        self::$ready = true;
    }

    /* ------------------------------------------------------------ seed data */

    private function seedDefaults() {
        try {
            if ((int)$this->pdo->query("SELECT COUNT(*) FROM gifts")->fetchColumn() === 0) {
                $gifts = $this->giftCatalog();
                $ins = $this->pdo->prepare("INSERT INTO gifts (name, emoji, price_usd, category, sort_order) VALUES (?,?,?,?,?)");
                $i = 0;
                foreach ($gifts as $g) { $ins->execute([$g[0], $g[1], $g[2], $g[3], $i++]); }
            }
            if ((int)$this->pdo->query("SELECT COUNT(*) FROM gift_currency_rates")->fetchColumn() === 0) {
                $rates = $this->currencyRates();
                $ins = $this->pdo->prepare("INSERT INTO gift_currency_rates (code, name, rate_to_usd) VALUES (?,?,?)");
                foreach ($rates as $r) { $ins->execute([$r[0], $r[1], $r[2]]); }
            }
        } catch (Throwable $e) { error_log('Gift seed failed: ' . $e->getMessage()); }
    }

    /** 100+ gifts: [name, emoji, price_usd, category] */
    private function giftCatalog() {
        return [
            // Flowers & romance
            ['Rose', '🌹', 0.50, 'romance'], ['Red Rose Bouquet', '💐', 2.00, 'romance'], ['Tulips', '🌷', 1.50, 'romance'],
            ['Sunflowers', '🌻', 1.20, 'romance'], ['Orchids', '🌺', 3.00, 'romance'], ['Blossom', '🌸', 0.80, 'romance'],
            ['Love Knot', '💞', 1.00, 'romance'], ['Kiss', '💋', 0.75, 'romance'], ['Heart Balloon', '🎈', 1.50, 'romance'],
            ['Love You', '💖', 1.00, 'romance'], ['Sparkling Heart', '💗', 2.50, 'romance'], ['Beating Heart', '💓', 1.20, 'romance'],
            ['Heart Arrow', '💕', 0.90, 'romance'], ['Revolving Hearts', '💘', 4.00, 'romance'], ['Heart Decoration', '💝', 1.80, 'romance'],
            ['Couple', '👫', 2.00, 'romance'], ['Handshake Love', '🤝', 1.50, 'romance'], ['Ring', '💍', 25.00, 'romance'],
            ['Gem Stone', '💎', 50.00, 'romance'], ['Crown', '👑', 75.00, 'luxury'],
            // Food & drink
            ['Coffee', '☕', 0.50, 'food'], ['Tea', '🍵', 0.40, 'food'], ['Chocolate', '🍫', 0.60, 'food'],
            ['Cake Slice', '🍰', 1.00, 'food'], ['Birthday Cake', '🎂', 5.00, 'food'], ['Cupcake', '🧁', 0.80, 'food'],
            ['Ice Cream', '🍦', 0.70, 'food'], ['Donut', '🍩', 0.50, 'food'], ['Pizza', '🍕', 1.50, 'food'],
            ['Burger', '🍔', 1.20, 'food'], ['Taco', '🌮', 0.90, 'food'], ['Sushi', '🍣', 2.50, 'food'],
            ['Popcorn', '🍿', 0.60, 'food'], ['Fruit', '🍇', 0.80, 'food'], ['Watermelon', '🍉', 0.70, 'food'],
            ['Wine Glass', '🍷', 3.00, 'food'], ['Champagne', '🍾', 8.00, 'food'], ['Cocktail', '🍸', 4.00, 'food'],
            ['Beer Mug', '🍺', 1.50, 'food'], ['Juice', '🧃', 0.50, 'food'], ['Milk', '🥛', 0.40, 'food'],
            ['Bread', '🍞', 0.50, 'food'], ['Cheese', '🧀', 0.90, 'food'], ['Honey Pot', '🍯', 0.70, 'food'],
            // Celebration & fun
            ['Party Popper', '🎉', 0.50, 'fun'], ['Confetti Ball', '🎊', 1.00, 'fun'], ['Balloon', '🎈', 0.40, 'fun'],
            ['Gift Box', '🎁', 1.50, 'fun'], ['Ribbon', '🎀', 0.50, 'fun'], ['Tada', '🎉', 0.80, 'fun'],
            ['Star', '⭐', 0.60, 'fun'], ['Glowing Star', '🌟', 2.00, 'fun'], ['Sparkles', '✨', 1.20, 'fun'],
            ['Fireworks', '🎆', 5.00, 'fun'], ['Sparkler', '🎇', 1.00, 'fun'], ['Circus Tent', '🎪', 4.00, 'fun'],
            ['Ferris Wheel', '🎡', 3.00, 'fun'], ['Roller Coaster', '🎢', 6.00, 'fun'], ['Carousel', '🎠', 3.50, 'fun'],
            ['Magic Wand', '🪄', 4.00, 'fun'], ['Crystal Ball', '🔮', 6.00, 'fun'], ['Jackpot', '🎰', 10.00, 'fun'],
            ['Clap', '👏', 0.30, 'fun'], ['Thumbs Up', '👍', 0.30, 'fun'], ['Fire', '🔥', 1.00, 'fun'],
            ['Hundred Points', '💯', 1.00, 'fun'], ['Trophy', '🏆', 8.00, 'fun'], ['First Place', '🥇', 10.00, 'fun'],
            ['Medal', '🏅', 5.00, 'fun'], ['Ribbon Award', '🎖️', 3.00, 'fun'], ['Party Face', '🥳', 1.50, 'fun'],
            ['Smiling Face', '😄', 0.50, 'fun'], ['Star Eyes', '🤩', 0.80, 'fun'], ['Cool Face', '😎', 0.80, 'fun'],
            ['Laughing', '😂', 0.50, 'fun'], ['Wink', '😉', 0.50, 'fun'], ['Sunglasses', '🕶️', 1.20, 'fun'],
            ['Crown Party', '🤴', 2.50, 'fun'], ['Princess', '👸', 2.50, 'fun'], ['Superhero', '🦸', 5.00, 'fun'],
            ['Robot', '🤖', 4.00, 'fun'], ['Alien', '👽', 4.00, 'fun'], ['Ghost', '👻', 1.50, 'fun'],
            // Animals & nature
            ['Puppy', '🐶', 2.00, 'animals'], ['Kitten', '🐱', 2.00, 'animals'], ['Lion', '🦁', 8.00, 'animals'],
            ['Tiger', '🐯', 8.00, 'animals'], ['Elephant', '🐘', 10.00, 'animals'], ['Panda', '🐼', 6.00, 'animals'],
            ['Penguin', '🐧', 3.00, 'animals'], ['Dolphin', '🐬', 5.00, 'animals'], ['Whale', '🐳', 12.00, 'animals'],
            ['Eagle', '🦅', 6.00, 'animals'], ['Butterfly', '🦋', 1.50, 'animals'], ['Turtle', '🐢', 2.50, 'animals'],
            ['Dragon', '🐉', 15.00, 'animals'], ['Unicorn', '🦄', 20.00, 'animals'], ['Rabbit', '🐰', 1.80, 'animals'],
            ['Bear', '🐻', 3.50, 'animals'], ['Fox', '🦊', 3.00, 'animals'], ['Owl', '🦉', 3.00, 'animals'],
            ['Bee', '🐝', 0.80, 'animals'], ['Ladybug', '🐞', 0.60, 'animals'], ['Fish', '🐠', 1.00, 'animals'],
            ['Clownfish', '🐡', 2.00, 'animals'], ['Octopus', '🐙', 4.00, 'animals'], ['Shrimp', '🦐', 1.20, 'animals'],
            // Music & tech
            ['Microphone', '🎤', 10.00, 'tech'], ['Headphones', '🎧', 12.00, 'tech'], ['Speaker', '🔊', 15.00, 'tech'],
            ['Music Notes', '🎵', 1.00, 'tech'], ['Musical Score', '🎼', 2.00, 'tech'], ['Saxophone', '🎷', 15.00, 'tech'],
            ['Trumpet', '🎺', 15.00, 'tech'], ['Guitar', '🎸', 20.00, 'tech'], ['Piano', '🎹', 25.00, 'tech'],
            ['Violin', '🎻', 18.00, 'tech'], ['Drum', '🥁', 12.00, 'tech'], ['Camera', '📷', 40.00, 'tech'],
            ['Video Camera', '📹', 60.00, 'tech'], ['Film', '🎬', 30.00, 'tech'], ['Clapper', '🎬', 8.00, 'tech'],
            ['Game Controller', '🎮', 35.00, 'tech'], ['Joystick', '🕹️', 25.00, 'tech'], ['Computer', '💻', 80.00, 'tech'],
            ['Phone', '📱', 50.00, 'tech'], ['Tablet', '📱', 90.00, 'tech'], ['Watch', '⌚', 60.00, 'tech'],
            ['Television', '📺', 120.00, 'tech'], ['Radio', '📻', 25.00, 'tech'], ['Light Bulb', '💡', 5.00, 'tech'],
            ['Rocket', '🚀', 30.00, 'tech'], ['Satellite', '🛰️', 100.00, 'tech'], ['Robot Dance', '🕺', 5.00, 'tech'],
            // Luxury & travel
            ['Money Bag', '💰', 100.00, 'luxury'], ['Dollar', '💵', 50.00, 'luxury'], ['Credit Card', '💳', 30.00, 'luxury'],
            ['Gold Bar', '🧱', 200.00, 'luxury'], ['Coins', '🪙', 5.00, 'luxury'], ['Gem', '💠', 40.00, 'luxury'],
            ['Diamond Ring', '💍', 150.00, 'luxury'], ['Necklace', '📿', 60.00, 'luxury'], ['Lipstick', '💄', 10.00, 'luxury'],
            ['Perfume', '🧴', 20.00, 'luxury'], ['High Heels', '👠', 25.00, 'luxury'], ['Handbag', '👜', 80.00, 'luxury'],
            ['Suitcase', '🧳', 50.00, 'luxury'], ['Airplane', '✈️', 120.00, 'luxury'], ['Helicopter', '🚁', 200.00, 'luxury'],
            ['Car', '🚗', 500.00, 'luxury'], ['Sports Car', '🏎️', 1000.00, 'luxury'], ['Motorcycle', '🏍️', 300.00, 'luxury'],
            ['Yacht', '🛥️', 800.00, 'luxury'], ['Ship', '🚢', 600.00, 'luxury'], ['House', '🏠', 1000.00, 'luxury'],
            ['Castle', '🏰', 1500.00, 'luxury'], ['Tent', '⛺', 10.00, 'luxury'], ['Beach Umbrella', '🏖️', 15.00, 'luxury'],
            ['Palm Tree', '🌴', 5.00, 'luxury'], ['Mountain', '⛰️', 20.00, 'luxury'], ['Hot Air Balloon', '🎈', 150.00, 'luxury'],
            ['Map', '🗺️', 3.00, 'luxury'], ['Compass', '🧭', 8.00, 'luxury'], ['Anchor', '⚓', 4.00, 'luxury'],
            // Hearts & symbols
            ['Red Heart', '❤️', 0.80, 'hearts'], ['Orange Heart', '🧡', 0.80, 'hearts'], ['Yellow Heart', '💛', 0.80, 'hearts'],
            ['Green Heart', '💚', 0.80, 'hearts'], ['Blue Heart', '💙', 0.80, 'hearts'], ['Purple Heart', '💜', 0.80, 'hearts'],
            ['Black Heart', '🖤', 1.00, 'hearts'], ['White Heart', '🤍', 0.80, 'hearts'], ['Brown Heart', '🤎', 0.80, 'hearts'],
            ['Broken Heart', '💔', 0.50, 'hearts'], ['Mending Heart', '❤️‍🩹', 2.00, 'hearts'], ['Heart Eyes', '😍', 1.00, 'hearts'],
            ['Two Hearts', '💑', 3.00, 'hearts'], ['Family', '👪', 5.00, 'hearts'], ['People Hug', '🫂', 2.00, 'hearts'],
            ['Hands Together', '🙏', 0.50, 'hearts'], ['Raised Hands', '🙌', 0.80, 'hearts'], ['Clapping', '👏', 0.40, 'hearts'],
            ['OK Hand', '👌', 0.40, 'hearts'], ['Victory', '✌️', 0.40, 'hearts'], ['Flexed Biceps', '💪', 1.50, 'hearts'],
            ['Writing Hand', '✍️', 0.60, 'hearts'], ['Love Letter', '💌', 2.00, 'hearts'], ['Envelope', '✉️', 0.60, 'hearts'],
            ['Love Letter Open', '💌', 1.50, 'hearts'], ['Bookmark', '🔖', 0.50, 'hearts'], ['Memo', '📝', 0.60, 'hearts'],
            ['Megaphone', '📣', 4.00, 'hearts'], ['Speakerphone', '📢', 6.00, 'hearts'], ['Bell', '🔔', 2.00, 'hearts'],
            ['Alarm Clock', '⏰', 3.00, 'hearts'], ['Hourglass', '⌛', 2.50, 'hearts'], ['Timer', '⏱️', 2.00, 'hearts'],
            ['Stopwatch', '⏲️', 3.00, 'hearts'], ['Calendar', '📅', 2.00, 'hearts'], ['Clock', '🕐', 3.00, 'hearts'],
            ['New Moon', '🌑', 1.00, 'hearts'], ['Full Moon', '🌕', 2.00, 'hearts'], ['Sun', '☀️', 1.50, 'hearts'],
            ['Rainbow', '🌈', 3.00, 'hearts'], ['Cloud', '☁️', 0.80, 'hearts'], ['Snowflake', '❄️', 1.00, 'hearts'],
            ['Umbrella', '☂️', 2.00, 'hearts'], ['Candle', '🕯️', 1.00, 'hearts'], ['Dove', '🕊️', 8.00, 'hearts'],
            ['Lips', '👄', 1.50, 'hearts'], ['Kiss Mark', '💋', 1.00, 'hearts'], ['Bouquet', '💐', 3.50, 'hearts'],
        ];
    }

    /** code, name, units per 1 USD (approximate; admin can edit) */
    private function currencyRates() {
        return [
            ['USD', 'US Dollar', 1], ['UGX', 'Ugandan Shilling', 3750], ['KES', 'Kenyan Shilling', 130],
            ['TZS', 'Tanzanian Shilling', 2500], ['RWF', 'Rwandan Franc', 1300], ['BIF', 'Burundian Franc', 2900],
            ['NGN', 'Nigerian Naira', 1500], ['GHS', 'Ghanaian Cedi', 15], ['ZAR', 'South African Rand', 18],
            ['EUR', 'Euro', 0.92], ['GBP', 'British Pound', 0.79], ['CAD', 'Canadian Dollar', 1.36],
            ['AUD', 'Australian Dollar', 1.52], ['AED', 'UAE Dirham', 3.67], ['SAR', 'Saudi Riyal', 3.75],
            ['INR', 'Indian Rupee', 83], ['CNY', 'Chinese Yuan', 7.1], ['JPY', 'Japanese Yen', 149],
            ['EGP', 'Egyptian Pound', 48], ['MAD', 'Moroccan Dirham', 10], ['ETB', 'Ethiopian Birr', 57],
            ['ZMW', 'Zambian Kwacha', 19], ['MWK', 'Malawian Kwacha', 1740], ['SLL', 'Sierra Leonean Leone', 22],
            ['CDF', 'Congolese Franc', 2800], ['SOS', 'Somali Shilling', 571], ['SSP', 'South Sudanese Pound', 1300],
            ['SDG', 'Sudanese Pound', 600], ['TND', 'Tunisian Dinar', 3.1], ['DZD', 'Algerian Dinar', 134],
        ];
    }

    /* ------------------------------------------------------------ queries */

    public function gifts($activeOnly = false) {
        $sql = "SELECT * FROM gifts" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY sort_order ASC, price_usd ASC, id ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function giftById($id) {
        $s = $this->pdo->prepare("SELECT * FROM gifts WHERE id = ?");
        $s->execute([(int)$id]);
        return $s->fetch() ?: null;
    }

    public function rates() {
        return $this->pdo->query("SELECT * FROM gift_currency_rates ORDER BY code ASC")->fetchAll();
    }

    public function rateFor($code) {
        $s = $this->pdo->prepare("SELECT rate_to_usd FROM gift_currency_rates WHERE code = ?");
        $s->execute([strtoupper(substr((string)$code, 0, 8))]);
        $r = $s->fetchColumn();
        return $r !== false ? (float)$r : 1.0;
    }

    /** Convert a USD price into the sender's currency. */
    public function convert($usd, $currency) {
        return round((float)$usd * $this->rateFor($currency), 2);
    }

    /** Unique recent commenters for a live show (recipient candidates). */
    public function recentCommenters($liveId, $limit = 20) {
        $s = $this->pdo->prepare("SELECT user_id, username, MAX(created_at) AS last_at FROM live_comments WHERE live_id = ? AND user_id IS NOT NULL AND user_id > 0 GROUP BY user_id, username ORDER BY last_at DESC LIMIT " . max(1, min(50, (int)$limit)));
        $s->execute([(int)$liveId]);
        return $s->fetchAll();
    }

    public function recentOrders($limit = 50) {
        $limit = max(1, min(200, (int)$limit));
        return $this->pdo->query("SELECT o.*, g.price_usd FROM gift_orders o LEFT JOIN gifts g ON g.id = o.gift_id ORDER BY o.id DESC LIMIT $limit")->fetchAll();
    }

    /* ------------------------------------------------------------ orders */

    public function createOrder($data) {
        $reference = 'TMZ-GIFT-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $s = $this->pdo->prepare("INSERT INTO gift_orders (reference, live_id, sender_id, sender_name, recipient_type, recipient_user_id, recipient_name, gift_id, gift_name, emoji, amount, currency, message)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $s->execute([
            $reference, (int)$data['live_id'], (int)$data['sender_id'], substr((string)$data['sender_name'], 0, 120),
            $data['recipient_type'] === 'user' ? 'user' : 'host',
            !empty($data['recipient_user_id']) ? (int)$data['recipient_user_id'] : null,
            substr((string)($data['recipient_name'] ?? 'The Host'), 0, 120),
            (int)$data['gift_id'], substr((string)$data['gift_name'], 0, 120), substr((string)$data['emoji'], 0, 16),
            (float)$data['amount'], strtoupper(substr((string)$data['currency'], 0, 8)),
            substr((string)($data['message'] ?? ''), 0, 300)
        ]);
        $id = (int)$this->pdo->lastInsertId();
        return ['id' => $id, 'reference' => $reference];
    }

    public function attachPayment($orderId, $paymentId) {
        $s = $this->pdo->prepare("UPDATE gift_orders SET payment_id = ? WHERE id = ?");
        return $s->execute([(int)$paymentId, (int)$orderId]);
    }

    public function orderByReference($reference) {
        $s = $this->pdo->prepare("SELECT * FROM gift_orders WHERE reference = ? LIMIT 1");
        $s->execute([$reference]);
        return $s->fetch() ?: null;
    }

    /** Mark a gift order completed and deliver it: float the emoji in the live room + notify the recipient. */
    public function fulfill($reference, $trackingId = '') {
        $order = $this->orderByReference($reference);
        if (!$order || $order['status'] === 'completed') return (bool)$order;
        $this->pdo->prepare("UPDATE gift_orders SET status = 'completed', completed_at = NOW() WHERE id = ? AND status <> 'completed'")->execute([(int)$order['id']]);

        // Burst of the gift emoji into the live room's floating reactions.
        try {
            $ins = $this->pdo->prepare("INSERT INTO live_reaction_events (live_id, emoji) VALUES (?, ?)");
            for ($i = 0; $i < 8; $i++) $ins->execute([(int)$order['live_id'], $order['emoji']]);
        } catch (Throwable $e) { /* reactions are cosmetic */ }

        // Notify the recipient user (not for the host pseudo-recipient).
        if ($order['recipient_type'] === 'user' && !empty($order['recipient_user_id'])) {
            try {
                require_once __DIR__ . '/SiteNotification.php';
                (new SiteNotification())->create('user', (int)$order['recipient_user_id'], 'gift',
                    '🎁 You received a gift!',
                    $order['sender_name'] . ' sent you ' . $order['gift_name'] . ' (' . $order['emoji'] . ') on the live show' . ($order['message'] !== '' ? ': “' . $order['message'] . '”' : '.') . ' — ' . $order['currency'] . ' ' . number_format((float)$order['amount'], 2),
                    '/live.php?live_id=' . (int)$order['live_id']);
            } catch (Throwable $e) { error_log('Gift notify failed: ' . $e->getMessage()); }
        }
        return true;
    }

    public function fail($reference) {
        $s = $this->pdo->prepare("UPDATE gift_orders SET status = 'failed' WHERE reference = ? AND status = 'pending'");
        return $s->execute([$reference]);
    }

    /* ------------------------------------------------------------ admin */

    public function saveGift($data) {
        if (!empty($data['id'])) {
            $s = $this->pdo->prepare("UPDATE gifts SET name=?, emoji=?, price_usd=?, category=?, is_active=?, sort_order=? WHERE id=?");
            return $s->execute([substr((string)$data['name'],0,120), substr((string)$data['emoji'],0,16), max(0.01,(float)$data['price_usd']), substr((string)$data['category'],0,40), !empty($data['is_active'])?1:0, (int)$data['sort_order'], (int)$data['id']]);
        }
        $s = $this->pdo->prepare("INSERT INTO gifts (name, emoji, price_usd, category, sort_order) VALUES (?,?,?,?,?)");
        return $s->execute([substr((string)$data['name'],0,120), substr((string)$data['emoji'],0,16), max(0.01,(float)$data['price_usd']), substr((string)$data['category'],0,40), (int)$data['sort_order']]);
    }

    public function deleteGift($id) {
        return $this->pdo->prepare("DELETE FROM gifts WHERE id = ?")->execute([(int)$id]);
    }

    public function saveRate($code, $name, $rate) {
        $s = $this->pdo->prepare("INSERT INTO gift_currency_rates (code, name, rate_to_usd) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE name = VALUES(name), rate_to_usd = VALUES(rate_to_usd)");
        return $s->execute([strtoupper(substr((string)$code,0,8)), substr((string)$name,0,60), max(0.000001,(float)$rate)]);
    }
}
