import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  async redirects() {
    return [
      {
        source: "/book/:slug",
        destination: "/auraflowstudio/:slug",
        permanent: true,
      },
    ];
  },
  async rewrites() {
    return {
      fallback: [
        {
          source: "/auraflowstudio/:slug",
          destination: "/book/:slug",
        },
      ],
    };
  },
};

export default nextConfig;
