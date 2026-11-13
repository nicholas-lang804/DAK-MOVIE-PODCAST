# TMZ Podcast — Oracle Media Server (ARM64) Deployment Pack

Purpose: replaceable media-storage/delivery server. PHP/MySQL stay on Hostinger.
Nothing here runs until you approve deployment. Driver stays `local` by default.

## 1. Server setup (Ubuntu 22.04/24.04 ARM64)

```bash
sudo apt update && sudo apt install -y nginx ffmpeg ufw certbot python3-certbot-nginx rsync
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## 2. Storage layout

```
/srv/tmz-media/
  originals/     # untouched uploads (never modified)
  renditions/    # 720p etc.
  hls/           # future adaptive playlists + segments
```

```bash
sudo mkdir -p /srv/tmz-media/{originals,renditions,hls}
sudo chown -R www-data:www-data /srv/tmz-media
sudo chmod -o-rwx /srv/tmz-media
```

## 3. Nginx — media site + signed URLs

Use `nginx-tmz.conf` in this folder as the server block. It:
- serves only signed requests (`secure_link_md5` with a shared secret),
- supports HTTP Range (MP4 seeking) via `mp4`/`slice` or direct file serving,
- denies directory listing, blocks dotfiles, sets long cache for renditions,
- rate-limits per IP.

The signing secret lives in `/etc/nginx/tmz_media_secret` (chmod 600) and the
matching value is stored in Hostinger `site_settings.media_remote_token` —
never in browser JS.

## 4. Upload API (media-api)

`RemoteMediaClient` expects a small HTTPS JSON API matching the contract in
`app/Services/RemoteMediaClient.php`. Two options:
- **v1 (recommended): SSH/rsync only** — Hostinger pushes files via
  `tools/media-migrate.php` using `ssh`/`rsync`; browser uploads stay local
  until the API exists. Playback can still use signed Nginx URLs.
- **v2: media-api** — small PHP/Node service implementing
  `/api/uploads`, `/api/uploads/{id}/part`, `/api/uploads/{id}/complete`,
  `/api/sign`, `/api/objects/head`, `/api/objects`, `/api/usage`
  (see contract comments in RemoteMediaClient.php).

## 5. HTTPS

```bash
sudo certbot --nginx -d media.yourdomain.com
```

## 6. Hostinger side (when approved)

Admin → Settings → Media storage:
- `media_driver` = `remote`
- `media_remote_base` = `https://media.yourdomain.com`
- `media_remote_token` = shared signing secret (write-only)

Rollback: set `media_driver` back to `local` — all local files and code paths
remain intact; remote server can be wiped without data loss (it holds copies).

## 7. Backups (Phase 4, required before production)

- DB: nightly `mysqldump` on Hostinger → download offsite.
- Media: `rsync` originals+renditions to a second location (never same disk).
- `tools/backup.php` produces timestamped bundles + checksums (Phase 4).
