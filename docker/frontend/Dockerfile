# Kozijnr frontend — local development image.
# Pinned explicitly to the latest stable Node LTS so the toolchain doesn't
# silently drift.
FROM node:24-alpine

WORKDIR /app

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 3000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["npm", "run", "dev"]
