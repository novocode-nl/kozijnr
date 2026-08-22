import { defineConfig } from "vitest/config"

/** Minimal Vitest setup, not a general-purpose test harness build-out. */
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
