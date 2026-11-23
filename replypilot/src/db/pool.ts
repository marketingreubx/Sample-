import pg from "pg";
import { config } from "../config.js";

let pool: pg.Pool | null = null;

export function db(): pg.Pool {
  if (!pool) {
    pool = new pg.Pool({ connectionString: config().databaseUrl, max: 10 });
    pool.on("error", (err) => console.error("[db] idle client error", err.message));
  }
  return pool;
}

export async function query<T extends pg.QueryResultRow = any>(
  text: string,
  params: unknown[] = []
): Promise<T[]> {
  const res = await db().query<T>(text, params);
  return res.rows;
}

export async function one<T extends pg.QueryResultRow = any>(
  text: string,
  params: unknown[] = []
): Promise<T | null> {
  const rows = await query<T>(text, params);
  return rows[0] ?? null;
}

export async function closeDb() {
  if (pool) {
    await pool.end();
    pool = null;
  }
}

export async function logOp(
  level: "info" | "warning" | "error",
  source: string,
  message: string,
  payload?: unknown
) {
  try {
    await query(
      `INSERT INTO operational_events(level, source, message, payload) VALUES ($1,$2,$3,$4)`,
      [level, source, message, payload === undefined ? null : JSON.stringify(payload)]
    );
  } catch (e) {
    console.error("[ops] failed to record event", (e as Error).message);
  }
}
