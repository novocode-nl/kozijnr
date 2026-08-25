# Shared proxy settings for every server block in nginx.conf.
# 6M mirrors the PHP upload_max_filesize/post_max_size fix from KOZ-32, so the
# proxy's own default (1M) doesn't reject uploads before the app ever sees
# them (e.g. POST /api/me/profile-photo with a normal >1MB photo).
client_max_body_size 6M;
proxy_http_version 1.1;
proxy_set_header Host $host;
proxy_set_header X-Forwarded-Host $host;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
# WebSocket upgrade (Next.js dev HMR).
proxy_set_header Upgrade $http_upgrade;
proxy_set_header Connection $connection_upgrade;
proxy_read_timeout 300s;
proxy_buffering off;
