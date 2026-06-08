import React, { useState } from "react";
import * as api from "../../api";

export default function ConnectButton({ onConnected }) {
  const [loading, setLoading] = useState(false);

  async function handleClick() {
    setLoading(true);
    try {
      const { url } = await api.get("/gmc/auth/url");
      window.location.href = url;
    } catch {
      setLoading(false);
    }
  }

  return (
    <button
      onClick={handleClick}
      disabled={loading}
      className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
    >
      {loading ? "Redirecting..." : "Connect Google Account"}
    </button>
  );
}
