// Test environment. Integration tests need Postgres + Redis (see README "Run the tests").
process.env.NODE_ENV = "test";
process.env.DATABASE_URL = process.env.TEST_DATABASE_URL ?? "postgresql://postgres@localhost:5432/replypilot_test";
process.env.REDIS_URL = process.env.TEST_REDIS_URL ?? "redis://localhost:6379/15";
process.env.SESSION_SECRET = "test-session-secret";
process.env.ENCRYPTION_KEY = "a".repeat(64);
process.env.WEBHOOK_VERIFY_TOKEN = "test-verify-token";
process.env.FACEBOOK_APP_SECRET = "test-fb-secret";
process.env.INSTAGRAM_APP_SECRET = "test-ig-secret";
process.env.INSTAGRAM_APP_ID = "123456";
process.env.DEMO_MODE = "true";
process.env.APP_URL = "https://replypilot.test";
process.env.PRIVATE_REPLY_MAX_PER_HOUR = "750";
process.env.DM_MAX_PER_HOUR = "200";
