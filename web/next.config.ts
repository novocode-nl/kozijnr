import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Next.js allows "**.localhost" as a dev origin by default, so no
  // allowedDevOrigins entry is needed for the *.kozijnr.localhost subdomains.
};

export default nextConfig;
