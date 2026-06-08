import React, { useState, useEffect, useRef } from "react";
import * as api from "../../api";

export default function SyncLog({ syncActive }) {
  const [logs, setLogs] = useState([]);
  const bottomRef = useRef(null);

  useEffect(() => {
    fetchLogs();
  }, []);

  useEffect(() => {
    if (!syncActive) return;
    const interval = setInterval(fetchLogs, 3000);
    return () => clearInterval(interval);
  }, [syncActive]);

  useEffect(() => {
    if (syncActive && bottomRef.current) {
      bottomRef.current.scrollIntoView({ behavior: "smooth" });
    }
  }, [logs, syncActive]);

  async function fetchLogs() {
    try {
      const data = await api.get("/gmc/logs?limit=50");
      setLogs(Array.isArray(data) ? data : []);
    } catch {}
  }

  return (
    <div>
      <h4 className="mb-2 text-sm font-medium text-gray-700">Sync Log</h4>
      <div className="h-64 overflow-y-auto rounded-md border border-gray-200 bg-gray-900 p-3 font-mono text-xs text-gray-300">
        {logs.length === 0 ? (
          <div className="text-gray-500">No sync logs yet.</div>
        ) : (
          logs.map((log) => (
            <div
              key={log.id}
              className={`border-b border-gray-800 py-1 ${
                log.type === "gmc_error" ? "text-red-400" : "text-gray-400"
              }`}
            >
              <span className="text-gray-600">[{log.created_at}]</span>{" "}
              {log.message}
              {log.context && (
                <span className="text-gray-600">
                  {" "}
                  {JSON.stringify(JSON.parse(log.context))}
                </span>
              )}
            </div>
          ))
        )}
        <div ref={bottomRef} />
      </div>
    </div>
  );
}
