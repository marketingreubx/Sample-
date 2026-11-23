export type MetaErrorKind = "token" | "rate_limit" | "permission" | "window" | "transient" | "unknown";

export class MetaApiError extends Error {
  constructor(
    message: string,
    public kind: MetaErrorKind,
    public code?: number,
    public subcode?: number,
    public httpStatus?: number
  ) {
    super(message);
    this.name = "MetaApiError";
  }
  /** Whether retrying the same request later could succeed. */
  get retryable(): boolean {
    return this.kind === "rate_limit" || this.kind === "transient";
  }
}

/**
 * Maps Graph API error codes to how the worker should react.
 *  190            expired/invalid token     -> mark account, stop
 *  4,17,32,613,80002,368 throttling          -> pause account, retry later
 *  10 + 2534022 / 551 / 2018278 outside the messaging window or user unavailable -> skip
 *  10,100,200,230 permission / invalid       -> fail, no retry
 *  1,2, HTTP 5xx  transient                  -> retry with backoff
 */
export function classifyMetaError(
  body: any,
  httpStatus: number,
  path = ""
): MetaApiError {
  const err = body?.error ?? {};
  const code: number | undefined = typeof err.code === "number" ? err.code : undefined;
  const sub: number | undefined = typeof err.error_subcode === "number" ? err.error_subcode : undefined;
  const msg = `${err.message ?? `HTTP ${httpStatus}`}${path ? ` (${path})` : ""} [code=${code ?? "-"} sub=${sub ?? "-"}]`;
  let kind: MetaErrorKind = "unknown";
  if (code === 190) kind = "token";
  else if ([4, 17, 32, 613, 80002, 368].includes(code ?? -1)) kind = "rate_limit";
  else if (code === 551 || sub === 2534022 || sub === 2018278 || sub === 2018108) kind = "window";
  else if ([10, 100, 200, 230].includes(code ?? -1)) kind = "permission";
  else if (code === 1 || code === 2 || httpStatus >= 500) kind = "transient";
  return new MetaApiError(msg, kind, code, sub, httpStatus);
}
