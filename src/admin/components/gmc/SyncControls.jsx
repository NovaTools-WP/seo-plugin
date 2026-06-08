import React, { useState } from "react";
import * as api from "../../api";

export default function SyncControls({ syncActive, syncPaused, onStarted, onCancelled, onPaused, onResumed }) {
  const [starting, setStarting] = useState(false);

  async function startSync() {
    setStarting(true);
    try {
      await api.post("/gmc/sync", {});
      onStarted();
    } catch (err) {
      alert(err.message || "Failed to start sync");
    }
    setStarting(false);
  }

  async function pauseSync() {
    try {
      await api.post("/gmc/sync/pause", {});
      onPaused();
    } catch (err) {
      alert(err.message || "Failed to pause sync");
    }
  }

  async function resumeSync() {
    try {
      await api.post("/gmc/sync/resume", {});
      onResumed();
    } catch (err) {
      alert(err.message || "Failed to resume sync");
    }
  }

  async function cancelSync() {
    try {
      await api.post("/gmc/sync/cancel", {});
      onCancelled();
    } catch (err) {
      alert(err.message || "Failed to cancel sync");
    }
  }

  return (
    <div className="flex items-center gap-3">
      <button
        onClick={startSync}
        disabled={syncActive || syncPaused || starting}
        className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
      >
        {starting ? "Starting..." : "Start Full Catalog Sync"}
      </button>
      {(syncActive || syncPaused) && (
        <>
          {syncActive && (
            <button
              onClick={pauseSync}
              className="rounded-md border border-yellow-300 px-4 py-2 text-sm font-medium text-yellow-700 hover:bg-yellow-50"
            >
              Pause
            </button>
          )}
          {syncPaused && (
            <button
              onClick={resumeSync}
              className="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
            >
              Resume
            </button>
          )}
          <button
            onClick={cancelSync}
            className="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
          >
            Cancel Sync
          </button>
        </>
      )}
    </div>
  );
}
