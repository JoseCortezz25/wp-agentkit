import React from "react";
import ReactDOM from "react-dom/client";

declare global {
  interface Window {
    agentkitAdmin?: {
      restUrl: string;
      nonce: string;
      settings: Record<string, unknown>;
    };
  }
}

function App() {
  const [settings] = React.useState<Record<string, unknown>>(
    window.agentkitAdmin?.settings ?? {},
  );
  const [saving, setSaving] = React.useState(false);

  const save = async () => {
    setSaving(true);
    await fetch(`${window.agentkitAdmin?.restUrl}/settings`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.agentkitAdmin?.nonce ?? "",
      },
      body: JSON.stringify(settings),
    });
    setSaving(false);
  };

  return (
    <div style={{ maxWidth: 960, padding: 24 }}>
      <h1>AgentKit</h1>
      <p>
        Base funcional del MVP: configuracion, reindexacion y endpoint de chat.
      </p>
      <pre
        style={{
          padding: 16,
          background: "#0f172a",
          color: "#e2e8f0",
          borderRadius: 12,
          overflow: "auto",
        }}
      >
        {JSON.stringify(settings, null, 2)}
      </pre>
      <div style={{ display: "flex", gap: 12 }}>
        <button onClick={save} disabled={saving}>
          {saving ? "Guardando..." : "Guardar configuracion"}
        </button>
        <button
          onClick={() => {
            void fetch(`${window.agentkitAdmin?.restUrl}/index`, {
              method: "POST",
              headers: { "X-WP-Nonce": window.agentkitAdmin?.nonce ?? "" },
            });
          }}
        >
          Reindexar contenido
        </button>
      </div>
    </div>
  );
}

const root = document.getElementById("agentkit-admin-root");

if (root) {
  ReactDOM.createRoot(root).render(<App />);
}
