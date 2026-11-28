import { defineConfig } from "vite";

export default defineConfig({
  base: "./",
  build: {
    outDir: "assets/build",
    manifest: true,
    rollupOptions: { input: "frontend/main.jsx" },
  },
});
