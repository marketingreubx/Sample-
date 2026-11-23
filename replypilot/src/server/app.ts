import express, { type NextFunction, type Request, type Response } from "express";
import { existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { config } from "../config.js";
import { loadAuth, parseCookies, requireCsrfHeader } from "./auth.js";
import { legalRouter } from "./legal.js";
import { accountsRouter } from "./routes/accounts.js";
import { authRouter } from "./routes/auth.js";
import { campaignsRouter } from "./routes/campaigns.js";
import { apiRouter, redirectRouter, webhookRouter } from "./routes/misc.js";

export function createApp() {
  const cfg = config();
  const app = express();
  app.disable("x-powered-by");
  if (cfg.trustProxy) app.set("trust proxy", 1);

  app.use((_req, res, next) => {
    res.setHeader("X-Content-Type-Options", "nosniff");
    res.setHeader("X-Frame-Options", "DENY");
    res.setHeader("Referrer-Policy", "strict-origin-when-cross-origin");
    res.setHeader(
      "Content-Security-Policy",
      "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'"
    );
    if (cfg.appUrl.startsWith("https://")) res.setHeader("Strict-Transport-Security", "max-age=31536000; includeSubDomains");
    next();
  });

  // Webhook first: it needs the raw, unparsed body to verify Meta's signature.
  app.use(webhookRouter);
  app.use(redirectRouter);
  app.use(legalRouter);

  app.use(express.json({ limit: "200kb" }));
  app.use(parseCookies);
  app.use(loadAuth);
  app.use("/api", requireCsrfHeader, authRouter, accountsRouter, campaignsRouter, apiRouter);
  app.use("/api", (_req, res) => res.status(404).json({ error: "Not found" }));

  // Built frontend (npm run build). In dev, Vite serves it on :5173 and proxies /api here.
  const webDist = join(dirname(fileURLToPath(import.meta.url)), "../../web/dist");
  if (existsSync(webDist)) {
    app.use(express.static(webDist, { index: false, maxAge: "1h" }));
    app.get(/^\/(?!api\/).*/, (_req, res) => res.sendFile(join(webDist, "index.html")));
  }

  app.use((err: Error, _req: Request, res: Response, _next: NextFunction) => {
    console.error("[http]", err);
    res.status(500).json({ error: "Internal server error" });
  });
  return app;
}
