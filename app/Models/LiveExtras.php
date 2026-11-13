<?php
/**
 * LiveExtras: additive features for the live studio.
 *
 * Everything lives in its own tables (created automatically on first use) and one nullable column
 * on live_podcasts (scheduled_at, stored in UTC), so existing live-room code is untouched.
 *
 *  - Scheduling, countdown, calendar and "remind me"
 *  - "Now talking about" banner, pinned message, shout-outs
 *  - Live polls and audience Q&A (with upvotes)
 *  - Viewer presence (live count, peak, attendance report)
 *  - Floating emoji reactions
 */

require_once __DIR__ . '/../../config/database.php';

class LiveExtras {
    const REACTIONS = ['👏', '🔥', '❤️', '😂', '😮', '🎉'];
    const PRESENCE_SECONDS = 45;

    private $pdo;
    private static $ready = false;

    public function __construct() {
        $this->pdo = getDB();
        $this->ensureSchema();
    }

    /* ------------------------------------------------------------------ schema */

    private function ensureSchema() {
        if (self::$ready) return;
        $tables = [
            "CREATE TABLE IF NOT EXISTS live_stage_state (
                live_id INT NOT NULL PRIMARY KEY,
                topic VARCHAR(255) NULL,
                pinned_message VARCHAR(500) NULL,
                shoutout VARCHAR(255) NULL,
                shoutout_until DATETIME NULL,
                version INT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS live_polls (
                id INT AUTO_INCREMENT PRIMARY KEY,
                live_id INT NOT NULL,
                question VARCHAR(300) NOT NULL,
                status ENUM('open','closed') NOT NULL DEFAULT 'open',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                closed_at DATETIME NULL,
                INDEX idx_live_polls_live (live_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS live_poll_options (
                id INT AUTO_INCREMENT PRIMARY KEY,
                poll_id INT NOT NULL,
                label VARCHAR(160) NOT NULL,
                position INT NOT NULL DEFAULT 0,
                INDEX idx_live_poll_options_poll (poll_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS live_poll_votes (
                poll_id INT NOT NULL,
                user_id INT NOT NULL,
                option_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (poll_id, user_id),
                INDEX idx_live_poll_votes_option (option_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS live_questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                live_id INT NOT NULL,
                user_id INT NOT NULL,
                question VARCHAR(300) NOT NULL,
                status ENUM('open','answered','hidden') NOT NULL DEFAULT 'open',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_live_questions_live (live_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS live_question_votes (
                question_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (question_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS live_presence (
                live_id INT NOT NULL,
                viewer_key VARCHAR(40) NOT NULL,
                user_id INT NULL,
                first_seen DATETIME NOT NULL,
                last_seen DATETIME NOT NULL,
                PRIMARY KEY (live_id, viewer_key),
                INDEX idx_live_presence_seen (live_id, last_seen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS live_reaction_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                live_id INT NOT NULL,
                emoji VARCHAR(16) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_live_reaction_events_live (live_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS live_reminders (
                live_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notified_at DATETIME NULL,
                PRIMARY KEY (live_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        try {
            foreach ($tables as $sql) $this->pdo->exec($sql);
            $col = $this->pdo->query("SHOW COLUMNS FROM live_podcasts LIKE 'scheduled_at'")->fetch();
            if (!$col) $this->pdo->exec("ALTER TABLE live_podcasts ADD COLUMN scheduled_at DATETIME NULL");
        } catch (Throwable $e) {
            error_log('LiveExtras schema setup failed: ' . $e->getMessage());
        }
        self::$ready = true;
    }

    /* ----------------------------------------------------------------- helpers */

    /** Tidy user text, cap its length and mask a short list of abusive words. */
    public static function cleanText($text, $max = 300) {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$text);
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if (function_exists('mb_substr')) $text = mb_substr($text, 0, $max); else $text = substr($text, 0, $max);
        $bad = ['fuck', 'shit', 'bitch', 'asshole', 'bastard', 'dick', 'pussy', 'slut', 'nigger', 'cunt'];
        foreach ($bad as $w) {
            $text = preg_replace_callback('/\b' . preg_quote($w, '/') . '\w*/iu', function ($m) {
                return str_repeat('*', strlen($m[0]));
            }, $text);
        }
        return $text;
    }

    public static function toIsoUtc($mysqlUtc) {
        if (!$mysqlUtc) return null;
        $ts = strtotime($mysqlUtc . ' UTC');
        return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
    }

    /** Parse an ISO-8601 string from the browser into a UTC "Y-m-d H:i:s". Returns null when invalid. */
    public static function parseIsoToUtc($iso) {
        $iso = trim((string)$iso);
        if ($iso === '') return null;
        try {
            $dt = new DateTime($iso);
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    /* ---------------------------------------------------------------- schedule */

    public function liveRow($liveId) {
        $s = $this->pdo->prepare("SELECT id, title, description, status, access_type, cover_image_path, scheduled_at, started_at, ended_at, viewer_peak FROM live_podcasts WHERE id = ?");
        $s->execute([(int)$liveId]);
        return $s->fetch() ?: null;
    }

    public function setSchedule($liveId, $utc) {
        $s = $this->pdo->prepare("UPDATE live_podcasts SET scheduled_at = ? WHERE id = ?");
        return $s->execute([$utc, (int)$liveId]);
    }

    public function upcoming($limit = 8) {
        $limit = max(1, min(30, (int)$limit));
        return $this->pdo->query("SELECT id, title, description, cover_image_path, access_type, price, currency, status, scheduled_at
            FROM live_podcasts WHERE status = 'scheduled'
            ORDER BY scheduled_at IS NULL, scheduled_at ASC, created_at DESC LIMIT $limit")->fetchAll();
    }

    public function past($limit = 8) {
        $limit = max(1, min(30, (int)$limit));
        return $this->pdo->query("SELECT id, title, description, cover_image_path, access_type, ended_at, viewer_peak
            FROM live_podcasts WHERE status = 'ended' ORDER BY ended_at DESC, id DESC LIMIT $limit")->fetchAll();
    }

    /* ---------------------------------------------------------------- reminders */

    public function hasReminder($liveId, $userId) {
        $s = $this->pdo->prepare("SELECT 1 FROM live_reminders WHERE live_id = ? AND user_id = ?");
        $s->execute([(int)$liveId, (int)$userId]);
        return (bool)$s->fetchColumn();
    }

    /** Toggle a reminder. Returns true when the reminder is now set. */
    public function toggleReminder($liveId, $userId) {
        if ($this->hasReminder($liveId, $userId)) {
            $s = $this->pdo->prepare("DELETE FROM live_reminders WHERE live_id = ? AND user_id = ?");
            $s->execute([(int)$liveId, (int)$userId]);
            return false;
        }
        $s = $this->pdo->prepare("INSERT IGNORE INTO live_reminders (live_id, user_id) VALUES (?, ?)");
        $s->execute([(int)$liveId, (int)$userId]);
        return true;
    }

    public function reminderCount($liveId) {
        $s = $this->pdo->prepare("SELECT COUNT(*) FROM live_reminders WHERE live_id = ?");
        $s->execute([(int)$liveId]);
        return (int)$s->fetchColumn();
    }

    /**
     * Send in-site notifications for reminders whose show starts within 15 minutes (or is already live).
     * $force = true sends to everyone who asked for a reminder on $onlyLiveId straight away.
     * Returns how many notifications were created.
     */
    public function dispatchDueReminders($force = false, $onlyLiveId = 0) {
        $sent = 0;
        try {
            if ($force && $onlyLiveId) {
                $q = $this->pdo->prepare("SELECT r.live_id, r.user_id, p.title FROM live_reminders r JOIN live_podcasts p ON p.id = r.live_id
                    WHERE r.notified_at IS NULL AND r.live_id = ? LIMIT 500");
                $q->execute([(int)$onlyLiveId]);
            } else {
                $q = $this->pdo->query("SELECT r.live_id, r.user_id, p.title FROM live_reminders r JOIN live_podcasts p ON p.id = r.live_id
                    WHERE r.notified_at IS NULL AND p.status IN ('scheduled','live') AND p.scheduled_at IS NOT NULL
                      AND p.scheduled_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) LIMIT 500");
            }
            $rows = $q->fetchAll();
            if (!$rows) return 0;
            require_once __DIR__ . '/SiteNotification.php';
            $notes = new SiteNotification();
            $mark = $this->pdo->prepare("UPDATE live_reminders SET notified_at = NOW() WHERE live_id = ? AND user_id = ?");
            foreach ($rows as $r) {
                try {
                    $notes->create('user', (int)$r['user_id'], 'live', 'Starting soon: ' . $r['title'],
                        'Your reminder: this live show is about to start. Tap to join.', '/live.php?live_id=' . (int)$r['live_id']);
                    $mark->execute([(int)$r['live_id'], (int)$r['user_id']]);
                    $sent++;
                } catch (Throwable $e) {
                    error_log('Live reminder failed: ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            error_log('Live reminders dispatch failed: ' . $e->getMessage());
        }
        return $sent;
    }

    /* ------------------------------------------------------------------- stage */

    public function stage($liveId) {
        $s = $this->pdo->prepare("SELECT topic, pinned_message, shoutout, shoutout_until, version FROM live_stage_state WHERE live_id = ?");
        $s->execute([(int)$liveId]);
        $row = $s->fetch() ?: ['topic' => null, 'pinned_message' => null, 'shoutout' => null, 'shoutout_until' => null, 'version' => 0];
        // Shout-outs expire on their own.
        if (!empty($row['shoutout']) && !empty($row['shoutout_until'])) {
            $q = $this->pdo->prepare("SELECT ? < NOW()");
            $q->execute([$row['shoutout_until']]);
            if ($q->fetchColumn()) { $row['shoutout'] = null; $row['shoutout_until'] = null; }
        }
        return $row;
    }

    public function setStage($liveId, $topic, $pinned, $shoutout, $shoutoutMinutes = 1) {
        $minutes = max(1, min(30, (int)$shoutoutMinutes));
        $until = null;
        if ($shoutout !== '') {
            // Use the database clock so expiry matches NOW() in stage().
            $t = $this->pdo->prepare("SELECT DATE_ADD(NOW(), INTERVAL ? MINUTE)");
            $t->execute([$minutes]);
            $until = $t->fetchColumn();
        }
        $s = $this->pdo->prepare("INSERT INTO live_stage_state (live_id, topic, pinned_message, shoutout, shoutout_until, version)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE topic = VALUES(topic), pinned_message = VALUES(pinned_message),
                shoutout = VALUES(shoutout), shoutout_until = VALUES(shoutout_until), version = version + 1");
        return $s->execute([(int)$liveId, $topic !== '' ? $topic : null, $pinned !== '' ? $pinned : null, $shoutout !== '' ? $shoutout : null, $until]);
    }

    /* ------------------------------------------------------------------- polls */

    public function createPoll($liveId, $question, array $options) {
        $options = array_values(array_filter(array_map(function ($o) { return self::cleanText($o, 160); }, $options), function ($o) { return $o !== ''; }));
        if ($question === '' || count($options) < 2 || count($options) > 6) return 0;
        $this->pdo->beginTransaction();
        try {
            $close = $this->pdo->prepare("UPDATE live_polls SET status = 'closed', closed_at = NOW() WHERE live_id = ? AND status = 'open'");
            $close->execute([(int)$liveId]);
            $ins = $this->pdo->prepare("INSERT INTO live_polls (live_id, question) VALUES (?, ?)");
            $ins->execute([(int)$liveId, $question]);
            $pollId = (int)$this->pdo->lastInsertId();
            $opt = $this->pdo->prepare("INSERT INTO live_poll_options (poll_id, label, position) VALUES (?, ?, ?)");
            foreach ($options as $i => $label) $opt->execute([$pollId, $label, $i]);
            $this->pdo->commit();
            return $pollId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Poll creation failed: ' . $e->getMessage());
            return 0;
        }
    }

    public function closePoll($liveId, $pollId) {
        $s = $this->pdo->prepare("UPDATE live_polls SET status = 'closed', closed_at = NOW() WHERE id = ? AND live_id = ? AND status = 'open'");
        $s->execute([(int)$pollId, (int)$liveId]);
        return $s->rowCount() === 1;
    }

    /** Poll with results: open poll first, otherwise the most recently closed one (shown for 10 minutes). */
    public function activePoll($liveId, $userId = 0) {
        $s = $this->pdo->prepare("SELECT * FROM live_polls WHERE live_id = ? AND (status = 'open' OR closed_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE))
            ORDER BY status = 'open' DESC, id DESC LIMIT 1");
        $s->execute([(int)$liveId]);
        $poll = $s->fetch();
        return $poll ? $this->withResults($poll, $userId) : null;
    }

    public function listPolls($liveId, $limit = 10) {
        $limit = max(1, min(30, (int)$limit));
        $s = $this->pdo->prepare("SELECT * FROM live_polls WHERE live_id = ? ORDER BY id DESC LIMIT $limit");
        $s->execute([(int)$liveId]);
        $out = [];
        foreach ($s->fetchAll() as $p) $out[] = $this->withResults($p, 0);
        return $out;
    }

    private function withResults(array $poll, $userId) {
        $o = $this->pdo->prepare("SELECT o.id, o.label, COUNT(v.user_id) AS votes
            FROM live_poll_options o LEFT JOIN live_poll_votes v ON v.option_id = o.id
            WHERE o.poll_id = ? GROUP BY o.id, o.label, o.position ORDER BY o.position ASC, o.id ASC");
        $o->execute([(int)$poll['id']]);
        $options = $o->fetchAll();
        $total = 0;
        foreach ($options as $opt) $total += (int)$opt['votes'];
        $mine = 0;
        if ($userId) {
            $m = $this->pdo->prepare("SELECT option_id FROM live_poll_votes WHERE poll_id = ? AND user_id = ?");
            $m->execute([(int)$poll['id'], (int)$userId]);
            $mine = (int)$m->fetchColumn();
        }
        return [
            'id' => (int)$poll['id'],
            'question' => $poll['question'],
            'status' => $poll['status'],
            'total' => $total,
            'my_vote' => $mine,
            'options' => array_map(function ($x) { return ['id' => (int)$x['id'], 'label' => $x['label'], 'votes' => (int)$x['votes']]; }, $options),
        ];
    }

    public function vote($liveId, $pollId, $optionId, $userId) {
        $p = $this->pdo->prepare("SELECT 1 FROM live_polls WHERE id = ? AND live_id = ? AND status = 'open'");
        $p->execute([(int)$pollId, (int)$liveId]);
        if (!$p->fetchColumn()) return false;
        $o = $this->pdo->prepare("SELECT 1 FROM live_poll_options WHERE id = ? AND poll_id = ?");
        $o->execute([(int)$optionId, (int)$pollId]);
        if (!$o->fetchColumn()) return false;
        $v = $this->pdo->prepare("INSERT IGNORE INTO live_poll_votes (poll_id, user_id, option_id) VALUES (?, ?, ?)");
        $v->execute([(int)$pollId, (int)$userId, (int)$optionId]);
        return $v->rowCount() === 1;
    }

    /* ----------------------------------------------------------------- Q & A */

    public function ask($liveId, $userId, $text) {
        if ($text === '') return 0;
        $s = $this->pdo->prepare("INSERT INTO live_questions (live_id, user_id, question) VALUES (?, ?, ?)");
        $s->execute([(int)$liveId, (int)$userId, $text]);
        return (int)$this->pdo->lastInsertId();
    }

    public function questions($liveId, $userId = 0, $includeHidden = false, $limit = 40) {
        $limit = max(1, min(100, (int)$limit));
        $where = $includeHidden ? "" : " AND q.status <> 'hidden'";
        $s = $this->pdo->prepare("SELECT q.id, q.question, q.status, q.created_at, u.username,
                (SELECT COUNT(*) FROM live_question_votes v WHERE v.question_id = q.id) AS votes,
                (SELECT COUNT(*) FROM live_question_votes v2 WHERE v2.question_id = q.id AND v2.user_id = ?) AS mine
            FROM live_questions q LEFT JOIN users u ON u.id = q.user_id
            WHERE q.live_id = ?$where
            ORDER BY (q.status = 'answered') ASC, votes DESC, q.id ASC LIMIT $limit");
        $s->execute([(int)$userId, (int)$liveId]);
        return array_map(function ($r) {
            return ['id' => (int)$r['id'], 'text' => $r['question'], 'status' => $r['status'], 'author' => $r['username'] ?: 'Member',
                    'votes' => (int)$r['votes'], 'mine' => (int)$r['mine'] > 0];
        }, $s->fetchAll());
    }

    /** Toggle an upvote. Returns true when the vote is now set, false when removed, null when the question is unavailable. */
    public function toggleUpvote($liveId, $questionId, $userId) {
        $q = $this->pdo->prepare("SELECT user_id FROM live_questions WHERE id = ? AND live_id = ? AND status <> 'hidden'");
        $q->execute([(int)$questionId, (int)$liveId]);
        if ($q->fetchColumn() === false) return null;
        $c = $this->pdo->prepare("SELECT 1 FROM live_question_votes WHERE question_id = ? AND user_id = ?");
        $c->execute([(int)$questionId, (int)$userId]);
        if ($c->fetchColumn()) {
            $d = $this->pdo->prepare("DELETE FROM live_question_votes WHERE question_id = ? AND user_id = ?");
            $d->execute([(int)$questionId, (int)$userId]);
            return false;
        }
        $i = $this->pdo->prepare("INSERT IGNORE INTO live_question_votes (question_id, user_id) VALUES (?, ?)");
        $i->execute([(int)$questionId, (int)$userId]);
        return true;
    }

    public function setQuestionStatus($liveId, $questionId, $status) {
        if (!in_array($status, ['open', 'answered', 'hidden'], true)) return false;
        $s = $this->pdo->prepare("UPDATE live_questions SET status = ? WHERE id = ? AND live_id = ?");
        $s->execute([$status, (int)$questionId, (int)$liveId]);
        return $s->rowCount() === 1;
    }

    public function lastQuestionTime($liveId, $userId) {
        $s = $this->pdo->prepare("SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM live_questions WHERE live_id = ? AND user_id = ?");
        $s->execute([(int)$liveId, (int)$userId]);
        return (int)$s->fetchColumn();
    }

    /* ---------------------------------------------------------------- presence */

    /** Record a heartbeat and return [current viewers, peak]. */
    public function touchPresence($liveId, $viewerKey, $userId = null) {
        $s = $this->pdo->prepare("INSERT INTO live_presence (live_id, viewer_key, user_id, first_seen, last_seen)
            VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW(), user_id = COALESCE(VALUES(user_id), user_id)");
        $s->execute([(int)$liveId, $viewerKey, $userId ? (int)$userId : null]);
        $c = $this->pdo->prepare("SELECT COUNT(*) FROM live_presence WHERE live_id = ? AND last_seen > DATE_SUB(NOW(), INTERVAL " . (int)self::PRESENCE_SECONDS . " SECOND)");
        $c->execute([(int)$liveId]);
        $now = (int)$c->fetchColumn();
        $p = $this->pdo->prepare("UPDATE live_podcasts SET viewer_peak = GREATEST(viewer_peak, ?) WHERE id = ?");
        $p->execute([$now, (int)$liveId]);
        $peak = $this->pdo->prepare("SELECT viewer_peak FROM live_podcasts WHERE id = ?");
        $peak->execute([(int)$liveId]);
        return [$now, (int)$peak->fetchColumn()];
    }

    public function currentViewers($liveId) {
        $c = $this->pdo->prepare("SELECT COUNT(*) FROM live_presence WHERE live_id = ? AND last_seen > DATE_SUB(NOW(), INTERVAL " . (int)self::PRESENCE_SECONDS . " SECOND)");
        $c->execute([(int)$liveId]);
        return (int)$c->fetchColumn();
    }

    public function attendance($liveId) {
        $s = $this->pdo->prepare("SELECT p.viewer_key, p.user_id, u.username, p.first_seen, p.last_seen,
                TIMESTAMPDIFF(SECOND, p.first_seen, p.last_seen) AS seconds
            FROM live_presence p LEFT JOIN users u ON u.id = p.user_id
            WHERE p.live_id = ? ORDER BY p.first_seen ASC LIMIT 5000");
        $s->execute([(int)$liveId]);
        return $s->fetchAll();
    }

    public function engagement($liveId) {
        $one = function ($sql, $liveId) {
            $s = $this->pdo->prepare($sql);
            $s->execute([(int)$liveId]);
            return (int)$s->fetchColumn();
        };
        return [
            'chat_messages' => $one("SELECT COUNT(*) FROM live_comments WHERE live_id = ?", $liveId),
            'questions' => $one("SELECT COUNT(*) FROM live_questions WHERE live_id = ?", $liveId),
            'poll_votes' => $one("SELECT COUNT(*) FROM live_poll_votes v JOIN live_polls p ON p.id = v.poll_id WHERE p.live_id = ?", $liveId),
            'speaker_requests' => $one("SELECT COUNT(*) FROM live_speaker_requests WHERE live_id = ?", $liveId),
            'reminders' => $one("SELECT COUNT(*) FROM live_reminders WHERE live_id = ?", $liveId),
        ];
    }

    /* --------------------------------------------------------------- reactions */

    public function addReaction($liveId, $emoji) {
        if (!in_array($emoji, self::REACTIONS, true)) return false;
        $s = $this->pdo->prepare("INSERT INTO live_reaction_events (live_id, emoji) VALUES (?, ?)");
        $s->execute([(int)$liveId, $emoji]);
        if (mt_rand(1, 25) === 1) {
            $this->pdo->exec("DELETE FROM live_reaction_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)");
        }
        return true;
    }

    /** Returns [latestId, [emoji,...]]. With $since = 0 only the latest id is returned, never a backlog. */
    public function reactionsSince($liveId, $since) {
        $m = $this->pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM live_reaction_events WHERE live_id = ?");
        $m->execute([(int)$liveId]);
        $latest = (int)$m->fetchColumn();
        if ((int)$since <= 0) return [$latest, []];
        $s = $this->pdo->prepare("SELECT emoji FROM live_reaction_events WHERE live_id = ? AND id > ? ORDER BY id ASC LIMIT 40");
        $s->execute([(int)$liveId, (int)$since]);
        return [$latest, array_map(function ($r) { return $r['emoji']; }, $s->fetchAll())];
    }
}
?>
