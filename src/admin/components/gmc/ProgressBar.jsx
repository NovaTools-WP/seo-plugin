import React, { useState, useEffect, useRef } from "react";
import * as api from "../../api";

export default function ProgressBar({ syncActive, onStatusUpdate }) {
  const [status, setStatus] = useState(null);
  const intervalRef = useRef(null);

  useEffect(() => {
    if (syncActive) {
      fetchStatus();
      intervalRef.current = setInterval(fetchStatus, 3000);
    } else {
      if (intervalRef.current) clearInterval(intervalRef.current);
      fetchStatus();
    }
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, [syncActive]);

  async function fetchStatus() {
    try {
      const s = await api.get("/gmc/sync-status");
      setStatus(s);
      if (onStatusUpdate) onStatusUpdate(s);
    } catch {}
  }

  if (!status) return null;

  const { percentage, processed, total, errors, status: state } = status;
  const isComplete = state === "complete" || state === "cancelled";

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between text-sm">
        <span className="font-medium text-gray-700">
          {state === "active" && "Syncing..."}
          {state === "paused" && "Sync Paused"}
          {state === "complete" &&
            errors > 0 &&
            `Sync Complete with ${errors} error(s)`}
          {state === "complete" && errors === 0 && "Sync Complete"}
          {state === "cancelled" && "Sync Cancelled"}
          {state === "idle" && "No sync in progress"}
        </span>
        <span className="text-gray-500">
          {processed} / {total} products
        </span>
      </div>

      <div className="h-3 w-full overflow-hidden rounded-full bg-gray-200">
        <div
          className={`h-full rounded-full transition-all duration-500 ${
            state === "paused"
              ? "bg-yellow-500"
              : isComplete && errors > 0
              ? "bg-yellow-500"
              : isComplete
              ? "bg-green-500"
              : "bg-blue-500"
          }`}
          style={{ width: `${Math.min(percentage, 100)}%` }}
        />
      </div>

      <div className="flex justify-between text-xs text-gray-500">
        <span>{percentage}%</span>
        {errors > 0 && <span className="text-red-500">{errors} error(s)</span>}
      </div>
    </div>
  );
}
