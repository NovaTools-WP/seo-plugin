import React, { useState, useEffect, useRef } from "react";
import * as api from "../api";

const LOG_TYPES = ["", "yoast_import", "license", "general"];

const hasYoast = window.novaToolsSEO?.hasYoast;

export default function Tools() {
  return (
    <div>
      <h2 className="text-2xl font-semibold text-gray-900">Tools</h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Import/export settings, Yoast migration, and log viewer.
      </p>
      <div className="space-y-8">
        <ExportImportSection />
        {hasYoast && (
          <>
            <hr className="border-gray-200" />
            <YoastImportSection />
          </>
        )}
        <hr className="border-gray-200" />
        <LogViewerSection />
      </div>
    </div>
  );
}

function ExportImportSection() {
  const [exporting, setExporting] = useState(false);
  const [importing, setImporting] = useState(false);
  const [message, setMessage] = useState("");
  const fileRef = useRef(null);

  async function doExport() {
    setExporting(true);
    setMessage("");
    try {
      const data = await api.get("/export");
      const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: "application/json",
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "novatools-seo-export.json";
      a.click();
      URL.revokeObjectURL(url);
      setMessage("Export downloaded.");
    } catch {
      setMessage("Export failed.");
    }
    setExporting(false);
  }

  async function doImport() {
    const file = fileRef.current?.files[0];
    if (!file) return;
    setImporting(true);
    setMessage("");
    try {
      const text = await file.text();
      const data = JSON.parse(text);
      await api.post("/import", data);
      setMessage("Settings imported successfully.");
      fileRef.current.value = "";
    } catch {
      setMessage("Import failed. Make sure the file is valid JSON.");
    }
    setImporting(false);
  }

  return (
    <div>
      <h3 className="text-lg font-medium text-gray-900">
        Export / Import Settings
      </h3>
      <div className="mt-3 flex flex-wrap items-center gap-4">
        <button
          onClick={doExport}
          disabled={exporting}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {exporting ? "Exporting..." : "Export Settings"}
        </button>

        <div className="flex items-center gap-2">
          <input
            ref={fileRef}
            type="file"
            accept=".json"
            className="block text-sm text-gray-600 file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
          />
          <button
            onClick={doImport}
            disabled={importing}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          >
            {importing ? "Importing..." : "Import"}
          </button>
        </div>
      </div>
      {message && <p className="mt-2 text-sm text-green-600">{message}</p>}
    </div>
  );
}

function YoastImportSection() {
  const [starting, setStarting] = useState(false);
  const [migrating, setMigrating] = useState(false);
  const [progress, setProgress] = useState(null);
  const [message, setMessage] = useState("");
  const cancelledRef = useRef(false);

  useEffect(() => {
    return () => {
      cancelledRef.current = true;
    };
  }, []);

  async function startImport() {
    setStarting(true);
    setMessage("");
    cancelledRef.current = false;
    try {
      const res = await api.post("/yoast-import/start", {});
      if (res.total === 0) {
        setMessage(res.message || "No Yoast SEO data found.");
        setStarting(false);
        return;
      }
      setProgress({ total: res.total, progress: 0, percentage: 0 });
      setMessage("Importing...");

      const batchSize = 50;
      let offset = 0;

      while (offset < res.total && !cancelledRef.current) {
        try {
          const batch = await api.post("/yoast-import/process-batch", {
            offset,
            limit: batchSize,
          });
          const done = batch.progress || offset + batch.processed;
          const pct = Math.min(Math.round((done / res.total) * 1000) / 10, 100);
          setProgress({ total: res.total, progress: done, percentage: pct });

          if (batch.processed === 0) break;
          offset += batch.processed;
        } catch {
          setMessage("Error during import batch.");
          break;
        }
      }

      if (!cancelledRef.current) {
        setMessage("Import complete.");
      }
    } catch {
      setMessage("Error starting import.");
    }
    setStarting(false);
  }

  async function migrateSettings() {
    setMigrating(true);
    setMessage("");
    try {
      await api.post("/yoast-import/migrate-settings", {});
      setMessage("Global settings migrated from Yoast.");
    } catch {
      setMessage("Error migrating settings.");
    }
    setMigrating(false);
  }

  return (
    <div>
      <h3 className="text-lg font-medium text-gray-900">
        Yoast SEO Importer
      </h3>
      <p className="mt-1 text-sm text-gray-500">
        Migrate SEO data from Yoast SEO to NovaTools SEO.
      </p>

      <div className="mt-3 flex flex-wrap gap-3">
        <button
          onClick={startImport}
          disabled={starting}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {starting ? "Starting..." : "Start Post Meta Import"}
        </button>
        <button
          onClick={migrateSettings}
          disabled={migrating}
          className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          {migrating ? "Migrating..." : "Migrate Global Settings"}
        </button>
      </div>

      {progress && progress.total > 0 && (
        <div className="mt-4">
          <div className="h-3 w-full max-w-md overflow-hidden rounded-full bg-gray-200">
            <div
              className="h-full rounded-full bg-blue-600 transition-all"
              style={{ width: Math.min(progress.percentage, 100) + "%" }}
            />
          </div>
          <p className="mt-1 text-sm text-gray-600">
            Imported {progress.progress} of {progress.total} posts (
            {progress.percentage}%)
          </p>
        </div>
      )}

      {message && <p className="mt-2 text-sm text-green-600">{message}</p>}
    </div>
  );
}

function LogViewerSection() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [typeFilter, setTypeFilter] = useState("");

  useEffect(() => {
    loadLogs();
  }, [typeFilter]);

  async function loadLogs() {
    setLoading(true);
    try {
      const path = typeFilter ? "/logs?type=" + typeFilter : "/logs";
      setLogs(await api.get(path));
    } catch {
      setLogs([]);
    }
    setLoading(false);
  }

  async function exportCsv() {
    try {
      const data = await api.get("/logs/export");
      if (!data.length) return;
      const keys = Object.keys(data[0]);
      let csv = keys.join(",") + "\n";
      for (const row of data) {
        csv +=
          keys
            .map((k) => {
              const val = String(row[k] ?? "");
              return '"' + val.replace(/"/g, '""') + '"';
            })
            .join(",") + "\n";
      }
      const blob = new Blob([csv], { type: "text/csv" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "novatools-seo-logs.csv";
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // silent
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-medium text-gray-900">Log Viewer</h3>
        <button
          onClick={exportCsv}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
        >
          Export CSV
        </button>
      </div>

      <div className="mt-3 mb-4">
        <select
          value={typeFilter}
          onChange={(e) => setTypeFilter(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        >
          <option value="">All Types</option>
          {LOG_TYPES.filter(Boolean).map((t) => (
            <option key={t} value={t}>
              {t.replace("_", " ")}
            </option>
          ))}
        </select>
      </div>

      {loading ? (
        <p className="text-sm text-gray-500">Loading logs...</p>
      ) : logs.length === 0 ? (
        <p className="text-sm text-gray-500">No log entries found.</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-gray-200">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                  Date
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                  Type
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                  Message
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                  Context
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white">
              {logs.map((log) => (
                <tr key={log.id}>
                  <td className="whitespace-nowrap px-3 py-2 text-xs text-gray-500">
                    {log.created_at}
                  </td>
                  <td className="px-3 py-2">
                    <span className="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                      {log.type}
                    </span>
                  </td>
                  <td className="px-3 py-2 text-sm text-gray-900">
                    {log.message}
                  </td>
                  <td className="max-w-xs truncate px-3 py-2 text-xs text-gray-400">
                    {log.context || "—"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
