import { defineConfig } from "vitest/config"

/**
 * Minimal Vitest setup (KOZ-14 rework, round 4) — this project had no
 * frontend unit-test infrastructure before this. Added narrowly to cover
 * `lib/backend-request.test.ts`'s timeout behaviour (see that file's
 * docstring for why it exists); not a general-purpose test harness
 * build-out.
 */
export default defineConfig({
  test: {
    environment: "node",
    include: ["**/*.test.ts"],
  },
  resolve: {
    alias: {
      "@": new URL(".", import.meta.url).pathname,
    },
  },
})
