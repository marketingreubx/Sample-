import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    include: ["tests/**/*.test.ts"],
    setupFiles: ["tests/setup.ts"],
    fileParallelism: false, // integration tests share one database
    testTimeout: 20_000,
    hookTimeout: 30_000,
  },
});
