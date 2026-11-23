import { useState, type FormEvent } from "react";
import { api, type Me } from "../api";
import { ErrorNote } from "../ui";

export function AuthPage({ me, onDone }: { me: Me; onDone: () => void }) {
  const [mode, setMode] = useState<"login" | "signup">("login");
  const [form, setForm] = useState({ email: "", password: "", name: "", workspaceName: "" });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api(mode === "login" ? "/auth/login" : "/auth/signup", { body: form });
      onDone();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }
  const set = (k: keyof typeof form) => (e: { target: { value: string } }) => setForm({ ...form, [k]: e.target.value });

  return (
    <div className="auth-wrap">
      <form className="card auth-card" onSubmit={submit}>
        <div className="brand big">
          <span className="logo">↩</span> ReplyPilot
        </div>
        <p className="muted">Instagram comment-to-DM and keyword automation, self-hosted.</p>
        {me.demoMode && <div className="alert alert-info">Demo mode is on: create any account to try it without Meta credentials.</div>}
        {mode === "signup" && (
          <>
            <label>Your name<input value={form.name} onChange={set("name")} autoComplete="name" /></label>
            <label>Workspace name<input value={form.workspaceName} onChange={set("workspaceName")} placeholder="e.g. Glow Studio" /></label>
          </>
        )}
        <label>Email<input type="email" required value={form.email} onChange={set("email")} autoComplete="email" /></label>
        <label>
          Password
          <input type="password" required minLength={8} value={form.password} onChange={set("password")} autoComplete={mode === "login" ? "current-password" : "new-password"} />
        </label>
        <ErrorNote error={error} />
        <button className="btn btn-primary" disabled={busy}>
          {busy ? "Please wait…" : mode === "login" ? "Sign in" : "Create account"}
        </button>
        {me.allowSignup !== false && (
          <button type="button" className="btn btn-ghost" onClick={() => setMode(mode === "login" ? "signup" : "login")}>
            {mode === "login" ? "New here? Create an account" : "Have an account? Sign in"}
          </button>
        )}
      </form>
    </div>
  );
}
