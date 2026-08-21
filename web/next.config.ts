import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Dev origins: this app is served on admin.kozijnr.localhost,
  // <tenant>.kozijnr.localhost and their koz-<n>.kozijnr.localhost worktree
  // variants (see README.md "Local domains via nginx"). Next.js allows
  // "**.localhost" as a dev origin by default, so no allowedDevOrigins
  // entry is needed.
};

export default nextConfig;
