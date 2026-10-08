import type { NextConfig } from "next";

const apiUrl = new URL(process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000");

const nextConfig: NextConfig = {
  // allowedDevOrigins: ["10.20.181.143"],
  allowedDevOrigins: ["192.168.88.253"],
  images: {
    remotePatterns: [
      {
        protocol: apiUrl.protocol.replace(":", "") as "http" | "https",
        hostname: apiUrl.hostname,
        port: apiUrl.port,
        pathname: "/storage/**",
      },
    ],
    // This project's offline-classroom setup (docs/02) always runs the
    // Laravel backend on localhost next to this frontend, which Next.js's
    // image optimizer treats as an SSRF risk by default. Safe here since
    // the "remote" host is never anything but our own backend.
    dangerouslyAllowLocalIP: true,
  },
};

export default nextConfig;
