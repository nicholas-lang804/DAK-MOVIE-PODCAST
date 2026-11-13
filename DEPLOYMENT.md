# TMZ Podcast 256 — Hosting & Migration Guide

Goal: keep full control (no paid storage middlemen), serve heavy videos efficiently, keep your domain.

## 1. Compress videos (biggest win — do this first)

Every uploaded video (movies, episodes, music videos, media library) is now auto-compressed to
**720p H.264** in the background — typically **70–80% smaller** with no visible quality loss.

**To activate on this PC:**
1. Download FFmpeg: https://www.gyan.dev/ffmpeg/builds/ → `ffmpeg-release-essentials.zip`
2. Extract and copy `ffmpeg.exe` + `ffprobe.exe` into `C:\ffmpeg\bin\`
   (or into `<project>\bin\` — the code auto-detects both locations)
3. Admin → Settings → **Video compression** → tick "Compress uploaded videos automatically" → Save.

Already-uploaded files are NOT retroactively compressed. To compress existing videos, re-upload them
or run manually: `php tools\compress-video.php "C:\xampp\htdocs\TMZ PODCAST\storage\uploads\movie.mp4"`

Progress log: `storage/logs/compress.log`. A `.compressing` marker file sits next to a video while it works.

## 2. Recommended production architecture (all self-hosted, ~$0)

```
Visitors ──► yourdomain.com (DNS)
                │
                ▼
        Cloudflare free plan (optional but recommended — free CDN + hides server IP + free SSL)
                │
                ▼
   Oracle Cloud Always Free server (4 CPU / 24GB RAM / 200GB disk — free forever)
     ├── Apache/Nginx + PHP 8.2 + MySQL
     ├── TMZ Podcast site (this codebase)
     └── storage/uploads/  ← all videos live here
```

- **Storage:** 200 GB ≈ 100–200 compressed movies. Add a block volume later if needed (cheap, still yours).
- **Bandwidth:** Oracle's free tier includes 10 TB/month outbound — plenty for a growing audience.
- **Domain:** keep it registered at Hostinger (or move free to Cloudflare). Only the **DNS A record** changes to point at the new server's IP.

## 3. Deploy to Hostinger (simple option, good for starting)

1. Hostinger → hPanel → **Websites → Add website** (or use your existing hosting).
2. Upload this project via **File Manager** or **hPanel → Git** — everything except `node_modules`, `_backups`, `.git`.
3. In hPanel → **Databases → MySQL Databases**: create a DB + user, note the 4 values.
4. Edit `config/database.php` on the server with the Hostinger DB credentials (or set env vars if supported).
5. Point your domain's DNS (Hostinger → Domains → DNS) to the hosting — usually already done if the domain is on the same account.
6. **Limit:** shared plans (~100–200 GB) + bandwidth caps. Fine for dozens of compressed movies, not hundreds.

## 4. Deploy to Oracle Always Free (recommended for scale)

1. Sign up at https://cloud.oracle.com → choose **Always Free** resources only.
2. Create an **Ampere A1** instance (ARM, up to 4 OCPU / 24 GB) — Ubuntu 22.04.
3. Open ports 80/443 in the Security List; attach/expand block storage to ~200 GB.
4. Install: `sudo apt install apache2 php libapache2-mod-php mysql-server ffmpeg`
   (or use the "Oracle Cloud Free Tier LAMP" marketplace image).
5. Upload the site to `/var/www/html/TMZ PODCAST/` (or set the docroot), import the database
   (export from local phpMyAdmin → import in server MySQL), update `config/database.php`.
6. Install SSL: `sudo apt install certbot python3-certbot-apache && sudo certbot --apache`.
7. DNS: point your domain's **A record** to the instance's public IP.
8. Set `pesapal_public_base_url` or rely on auto-detection (both work once DNS is live).

## 5. Cloudflare free CDN (optional, free)

1. Add your domain to a free Cloudflare account → change nameservers at your registrar.
2. Proxy the A record (orange cloud) → free SSL, DDoS protection, bandwidth offloading.
3. Page Rules / Cache Rules: cache images/fonts aggressively; **never cache** `payments/`, `admin/`, or video files
   unless you want viewers hitting your server directly anyway (videos stream fine through it).

## 6. Pre-flight checklist before going live

- [ ] FFmpeg installed → compression enabled (Admin → Settings)
- [ ] `storage/` folder writable by the web server
- [ ] `config/database.php` points to the production DB
- [ ] PHP limits raised: `upload_max_filesize`/`post_max_size` ≥ largest video (or rely on compression to keep files small)
- [ ] HTTPS everywhere (certbot) — required for Pesapal IPN + camera/mic in live rooms
- [ ] Admin → Pesapal Payments → **Register / refresh IPN** after DNS is live
- [ ] Test one full payment + one movie stream + one live room
- [ ] Remove `_backups/`, `node_modules/`, dev files from production upload
