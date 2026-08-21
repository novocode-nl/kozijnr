# Nginx dev-proxy, API-first frontend, afscheid van Valet

Datum: 2026-08-21

## Doel

1. De frontend (Next.js) wordt een pure REST-client van de backend op
   `api.<base>`. Geen API-endpoints of backend-pass-throughs meer in de
   frontend. Later kunnen andere clients (apps) op dezelfde manier aansluiten.
2. Laravel Valet verdwijnt volledig uit het project. Eén gedeelde nginx in
   Docker routeert op hostnaam naar de juiste stack (main of worktree).
3. Worktrees houden een geïsoleerde omgeving, met schone URLs zonder poort:
   `admin.koz-16.kozijnr.localhost`.

## Domeinen

| Context          | main                      | worktree KOZ-n                   |
| ---------------- | ------------------------- | -------------------------------- |
| backend (REST)   | `api.kozijnr.localhost`   | `api.koz-<n>.kozijnr.localhost`  |
| admin-frontend   | `admin.kozijnr.localhost` | `admin.koz-<n>.kozijnr.localhost`|
| tenant-frontend  | `<t>.kozijnr.localhost`   | `<t>.koz-<n>.kozijnr.localhost`  |

`APP_BASE_DOMAIN` = `kozijnr.localhost` resp. `koz-<n>.kozijnr.localhost`.
`*.localhost` lost in Chrome/Firefox/Safari naar 127.0.0.1 op zonder
DNS-configuratie. `curl` doet dat niet: gebruik `--resolve host:80:127.0.0.1`
(README documenteert dit). Valet wordt door de gebruiker zelf uitgezet
(`valet stop`), zodat poort 80 vrij is. `PROXY_PORT` (default 80) is
configureerbaar voor als 80 toch bezet is; dan staat de poort in de URL.

## Componenten

### 1. Gedeelde proxy-stack — `docker/proxy/`

- `docker/proxy/docker-compose.yml`, compose-project `kozijnr-proxy`, één
  service `nginx` (`nginx:alpine`), publiceert `${PROXY_PORT:-80}:80`,
  aangesloten op extern netwerk `kozijnr-proxy` (aangemaakt door
  `make proxy-up` als het nog niet bestaat).
- `docker/proxy/nginx.conf` (gemount read-only):
  - `resolver 127.0.0.11 valid=5s ipv6=off;` + variabele `proxy_pass`, zodat
    stacks die later starten zonder nginx-herstart gevonden worden en een
    niet-draaiende stack een 502 geeft in plaats van nginx te laten falen.
  - Server-blocks (regex `server_name`):
    - `~^api\.(?<project>koz-\d+)\.kozijnr\.localhost$` → `http://$project-backend:8000`
    - `~^api\.kozijnr\.localhost$` → `http://kozijnr-backend:8000`
    - `~^[^.]+\.(?<project>koz-\d+)\.kozijnr\.localhost$` → `http://$project-frontend:3000`
    - `~^[^.]+\.kozijnr\.localhost$` → `http://kozijnr-frontend:3000`
    - default-server → 404 met korte uitleg.
  - Elke proxy geeft `Host`, `X-Forwarded-*` door en ondersteunt
    websocket-upgrade (Next HMR).
- Makefile: `proxy-up` (netwerk aanmaken + `docker compose -f docker/proxy/docker-compose.yml up -d`),
  `proxy-down`, `proxy-logs`. `up` hangt af van `proxy-up`.

### 2. App-stacks — `docker-compose.yml`

- `frontend` en `backend` joinen naast het default-netwerk ook het externe
  netwerk `kozijnr-proxy` met aliases `${COMPOSE_PROJECT_NAME}-frontend` /
  `${COMPOSE_PROJECT_NAME}-backend`. Default `COMPOSE_PROJECT_NAME=kozijnr`.
- Geen hostpoorten meer voor frontend/backend. `database` houdt
  `${DATABASE_PORT:-5432}:5432` voor DB-clients.
- Weg: `NEXT_PUBLIC_API_URL`, `FRONTEND_PORT`, `BACKEND_PORT`.
- Backend-env: `APP_BASE_DOMAIN=${APP_BASE_DOMAIN:-kozijnr.localhost}`.
- `scripts/setup-worktree-env.sh <n>` schrijft alleen nog
  `COMPOSE_PROJECT_NAME=koz-<n>`, `DATABASE_PORT=$((5432+n))`,
  `APP_BASE_DOMAIN=koz-<n>.kozijnr.localhost`; geen Valet-stappen. Makefile
  `ensure-env` blijft zoals het is.

### 3. Backend (Symfony)

- **CORS** — nieuwe `App\Tenancy\Infrastructure\CorsListener` (alle
  omgevingen, geregistreerd in `services.yaml`): origin toegestaan als de
  host van `Origin` gelijk is aan `APP_BASE_DOMAIN` of eindigt op
  `.<APP_BASE_DOMAIN>`. Zet `Access-Control-Allow-Origin: <origin>`,
  `Allow-Credentials: true`, `Allow-Methods: GET, POST, PUT, PATCH, DELETE,
  OPTIONS`, `Allow-Headers: Content-Type, Authorization`, `Vary: Origin`;
  beantwoordt preflight (`OPTIONS` + toegestane origin) met 204 vóór
  routing. Niet-toegestane origins krijgen geen CORS-headers.
- **Tenant-/admin-context op `api.<base>`** — `TenantResolverListener`: als
  het `Host`-subdomein `api` is, wordt de context afgeleid van de host in de
  `Origin`-header (bijv. `http://acme.kozijnr.localhost` → tenant `acme`,
  `http://admin.kozijnr.localhost` → admin). Geen `Origin` → public schema,
  zoals nu. Ongeldige/andere-domein `Origin` → genegeerd (public). Geldt in
  elke omgeving (prod werkt identiek: `admin.kozijnr.nl` → `api.kozijnr.nl`).
  Het dev-only `X-Tenant-Host`-experiment vervalt.
- **Cookies** — `api.` en `admin.`/`<t>.` zijn same-site, dus
  `SameSite=Lax`-cookies werken met `credentials: "include"`. Maar: de
  cookies worden door `api.<base>` gezet en moeten óók de frontend-hosts
  bereiken (de route-guard in `web/proxy.ts` checkt de tenant-cookie op
  aanwezigheid en stuurt de admin-sessiecookie door naar `/api/admin/me`).
  Host-only cookies komen daar nooit aan, dus beide cookies krijgen
  `Domain=<APP_BASE_DOMAIN>` (`TenantApiTokenCookie::issue($token, $domain)`
  / `clear($domain)`, `framework.session.cookie_domain`). Tenant-isolatie
  blijft een server-side garantie (token-lookup in het tenant-schema).
- **Valet-code weg** — `Tenancy/Infrastructure/Valet/*` (listener, queue,
  interface, DevCorsListener) + tests, `services(_dev).yaml`-wiring en de
  parameters `valet_frontend_port` / `valet_queue_directory`; `FRONTEND_PORT`
  / `BACKEND_PORT` uit `api/.env`. `DEFAULT_URI=http://api.kozijnr.localhost`.
  `APP_BASE_DOMAIN`-default in `api/.env` → `kozijnr.localhost`.

### 4. Frontend (Next.js)

- `web/app/api/**` route handlers verwijderd.
- `lib/api.ts` wordt de service-laag: `apiBaseUrl(host)` leidt `api.<base>`
  af uit de huidige host (client: `window.location.host`; server: request
  `Host`): eerste label vervangen door `api`, protocol overnemen. Alle
  fetches gaan naar `${apiBaseUrl()}/api/...` met `credentials: "include"`.
  `login`, `adminLogin`, `logout`, `adminLogout` zitten hier; `nav-user.tsx`
  gebruikt ze.
- `proxy.ts` (route-guard): de admin-sessiecheck gaat server-side via
  `lib/backend-request.ts` naar `backend:8000` met `Host: api.<base>` en
  `Origin: http://admin.<base>` (zelfde mechanisme als de browser).
  `PUBLIC_API_PATHS` vervalt. Tenant-check (cookie aanwezig) blijft.
- `next.config.ts`: `allowedDevOrigins` kan weg (`**.localhost` is default).
  Comment vervangen.

### 5. Scripts, Makefile, docs, skills

- Weg: `scripts/valet-sync.sh`, `valet-watch.sh`, `valet-watch.test.sh`,
  `teardown-worktree-valet.sh`; Makefile-targets `valet-sync`,
  `valet-watch`, `worktree-valet-teardown`.
- README: alle Valet-secties vervangen door "Local domains via nginx"
  (proxy-stack, domeinschema, worktrees, curl-hint, `PROXY_PORT`).
  Verify-voorbeelden herschreven naar `api.<base>` + `Origin`.
- `.claude/skills/asana-user-review/SKILL.md`: Valet-teardown-stappen weg.

### 6. Testen / verificatie

- Backend: unit-/functionele tests voor `CorsListener` (toegestane origin,
  afgewezen origin, preflight) en `TenantResolverListener` (Origin →
  tenant/admin op `api.`, geen Origin → public, Origin genegeerd op andere
  hosts). `AdminRouteGuardListenerTest` bijgewerkt. Hele suite groen.
- Frontend: tests voor `apiBaseUrl()`; bestaande `backend-request.test.ts`
  blijft; lint + tsc groen.
- Live: `make up` op main → login op `admin.kozijnr.localhost`; tenant
  provisionen → `acme.kozijnr.localhost`; worktree `koz-16` `make up` →
  `admin.koz-16.kozijnr.localhost`; beide tegelijk bereikbaar.
