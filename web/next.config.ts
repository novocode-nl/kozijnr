import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /* config options here */
  // Allow the dev server to be reached via the project's tenant/admin/api
  // *.test domains (KOZ-12's Valet setup — main checkout AND every
  // per-worktree kozijnr-koz-<n>.test base domain) — without this,
  // Next.js's dev-origin check (see block-cross-site-dev.ts) 403s every
  // cross-origin chunk/HMR request whose Host doesn't match "localhost",
  // which breaks client-side hydration and silently falls back to a plain
  // HTML form submit. "**.localhost" (the Valet-less equivalent, e.g.
  // acme.localhost) is already allowed by Next.js by default, no entry
  // needed here. Only a leading "*"/"**" wildcard segment is supported
  // (see next/dist/server/app-render/csrf-protection.js) — a pattern like
  // "*.kozijnr-koz-*.test" would NOT match, since the wildcard can't sit
  // inside a label — hence the broader "**.test" rather than trying to
  // enumerate every worktree domain.
  allowedDevOrigins: ["**.test"],
};

export default nextConfig;
