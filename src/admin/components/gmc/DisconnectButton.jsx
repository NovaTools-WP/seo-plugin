import React, { useState } from "react";
import * as api from "../../api";

export default function DisconnectButton({ onDisconnected }) {
  const [loading, setLoading] = useState(false);
  const [confirming, setConfirming] = useState(false);

  async function handleDisconnect() {
    setLoading(true);
    try {
      await api.del("/gmc/auth");
      onDisconnected();
    } finally {
      setLoading(false);
      setConfirming(false);
    }
  }

  if (confirming) {
    return (
      <div className="flex items-center gap-2">
        <span className="text-sm text-red-600">Are you sure?</span>
        <button
          onClick={handleDisconnect}
          disabled={loading}
          className="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
        >
          {loading ? "Disconnecting..." : "Yes, Disconnect"}
        </button>
        <button
          onClick={() => setConfirming(false)}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </button>
      </div>
    );
  }

  return (
    <button
      onClick={() => setConfirming(true)}
      className="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
    >
      Disconnect
    </button>
  );
}
