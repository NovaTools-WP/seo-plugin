import React, { useState, useEffect } from "react";
import * as api from "../api";

const STATUS_CODES = [301, 302, 307, 308];

const EMPTY_FORM = {
  source_url: "",
  destination_url: "",
  status_code: 301,
  is_regex: 0,
};

function getSiteHost() {
  try {
    return new URL(
      window.novaToolsSEO?.siteUrl || window.location.origin,
    ).hostname.toLowerCase();
  } catch {
    return window.location.hostname.toLowerCase();
  }
}

function getDestinationHost(url) {
  if (!url) return null;
  const withProto = /^[a-z][a-z0-9+\-.]*:\/\//i.test(url)
    ? url
    : "https://" + url;
  try {
    return new URL(withProto).hostname.toLowerCase();
  } catch {
    return null;
  }
}

export default function RedirectManager() {
  const [tab, setTab] = useState("redirects");
  const [redirects, setRedirects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");
  const [warning, setWarning] = useState("");
  const [allowedDomains, setAllowedDomains] = useState([]);
  const [showSettings, setShowSettings] = useState(false);
  const [domainsText, setDomainsText] = useState("");
  const [savingDomains, setSavingDomains] = useState(false);

  const [logs, setLogs] = useState([]);
  const [logsLoading, setLogsLoading] = useState(false);
  const [suggestions, setSuggestions] = useState({});
  const [loadingSuggestions, setLoadingSuggestions] = useState({});
  const [suggestingFor, setSuggestingFor] = useState(null);

  useEffect(() => {
    load();
    loadSettings();
  }, []);

  useEffect(() => {
    if (tab === "404-logs") {
      loadLogs();
    }
  }, [tab]);

  async function load() {
    setLoading(true);
    try {
      const data = await api.get("/redirects");
      setRedirects(data);
    } catch {
      setMessage("Error loading redirects.");
    }
    setLoading(false);
  }

  async function loadSettings() {
    try {
      const data = await api.get("/settings");
      const domains = data.wseo_redirect_allowed_domains || [];
      setAllowedDomains(domains);
      setDomainsText(domains.join("\n"));
    } catch {
      // Settings load failure is non-critical
    }
  }

  async function loadLogs() {
    setLogsLoading(true);
    try {
      const data = await api.get("/404-logs");
      setLogs(data);
    } catch {
      setMessage("Error loading 404 logs.");
    }
    setLogsLoading(false);
  }

  async function loadSuggestions(url, logId) {
    setLoadingSuggestions((s) => ({ ...s, [logId]: true }));
    setSuggestingFor(logId);
    try {
      const data = await api.get(
        "/404-suggestions?url=" + encodeURIComponent(url),
      );
      setSuggestions((s) => ({ ...s, [logId]: data }));
    } catch {
      setSuggestions((s) => ({ ...s, [logId]: [] }));
    }
    setLoadingSuggestions((s) => ({ ...s, [logId]: false }));
  }

  async function createRedirectFromSuggestion(logEntry, suggestion) {
    setSaving(true);
    setMessage("");
    try {
      await api.post("/redirects", {
        source_url: logEntry.url,
        destination_url: suggestion.url,
        status_code: 301,
        is_regex: 0,
      });
      setMessage(`Redirect created: ${logEntry.url} → ${suggestion.url}`);
      await load();
      await deleteLogEntry(logEntry.id);
    } catch {
      setMessage("Error creating redirect.");
    }
    setSaving(false);
  }

  function startRedirectFromLog(logEntry) {
    setTab("redirects");
    setEditing(null);
    setForm({
      source_url: logEntry.url,
      destination_url: "",
      status_code: 301,
      is_regex: 0,
    });
    setWarning("");
    setShowForm(true);
  }

  async function deleteLogEntry(id) {
    try {
      await api.del("/404-logs/" + id);
      setLogs((l) => l.filter((x) => x.id !== id));
    } catch {
      setMessage("Error deleting 404 log entry.");
    }
  }

  async function clearAllLogs() {
    if (!confirm("Clear all 404 log entries?")) return;
    try {
      await api.del("/404-logs");
      setLogs([]);
      setMessage("All 404 logs cleared.");
    } catch {
      setMessage("Error clearing 404 logs.");
    }
  }

  async function saveDomains() {
    setSavingDomains(true);
    try {
      const domains = domainsText
        .split(/[\n,]+/)
        .map((d) =>
          d
            .trim()
            .toLowerCase()
            .replace(/^https?:\/\//, "")
            .replace(/\/+$/, ""),
        )
        .filter(Boolean);
      await api.post("/settings", { wseo_redirect_allowed_domains: domains });
      setAllowedDomains(domains);
      setShowSettings(false);
      setMessage("Allowed domains saved.");
    } catch {
      setMessage("Error saving allowed domains.");
    }
    setSavingDomains(false);
  }

  function startEdit(r) {
    setEditing(r.id);
    setForm({
      source_url: r.source_url,
      destination_url: r.destination_url,
      status_code: Number(r.status_code),
      is_regex: Number(r.is_regex),
    });
    setWarning("");
    setShowForm(true);
  }

  function startAdd() {
    setEditing(null);
    setForm(EMPTY_FORM);
    setWarning("");
    setShowForm(true);
  }

  function checkExternalDomain(dest) {
    const host = getDestinationHost(dest);
    if (!host) {
      setWarning("");
      return;
    }
    const siteHost = getSiteHost();
    if (host === siteHost) {
      setWarning("");
      return;
    }
    if (allowedDomains.length > 0 && !allowedDomains.includes(host)) {
      setWarning(
        `Domain "${host}" is not in the allowed domains list. This redirect will be blocked at runtime.`,
      );
    } else if (allowedDomains.length === 0) {
      setWarning(
        `Redirecting to external domain "${host}". Consider configuring an allowed domains list.`,
      );
    } else {
      setWarning("");
    }
  }

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      const payload = { ...form };
      if (editing) payload.id = editing;
      const result = await api.post("/redirects", payload);
      if (result.warning) {
        setMessage("");
        setWarning(result.warning);
      } else {
        setMessage("Redirect saved.");
        setWarning("");
        setShowForm(false);
      }
      await load();
    } catch {
      setMessage("Error saving redirect.");
    }
    setSaving(false);
  }

  async function deleteRedirect(id) {
    if (!confirm("Delete this redirect?")) return;
    setMessage("");
    try {
      await api.del("/redirects/" + id);
      setRedirects((r) => r.filter((x) => x.id !== id));
      setMessage("Redirect deleted.");
    } catch {
      setMessage("Error deleting redirect.");
    }
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-gray-900">
            Redirect Manager
          </h2>
          <p className="mt-1 text-sm text-gray-500">
            Manage URL redirects and review 404 errors.
          </p>
        </div>
      </div>

      <div className="mb-6 flex gap-1 border-b border-gray-200">
        <button
          onClick={() => setTab("redirects")}
          className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
            tab === "redirects"
              ? "border-blue-600 text-blue-600"
              : "border-transparent text-gray-500 hover:text-gray-700"
          }`}
        >
          Redirects
        </button>
        <button
          onClick={() => setTab("404-logs")}
          className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
            tab === "404-logs"
              ? "border-blue-600 text-blue-600"
              : "border-transparent text-gray-500 hover:text-gray-700"
          }`}
        >
          404 Logs
          {logs.length > 0 && (
            <span className="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">
              {logs.length}
            </span>
          )}
        </button>
      </div>

      {tab === "redirects" && (
        <div>
          <div className="mb-4 flex gap-2">
            <button
              onClick={() => setShowSettings(!showSettings)}
              className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
              Allowed Domains
            </button>
            <button
              onClick={startAdd}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              Add Redirect
            </button>
          </div>

          {showSettings && (
            <div className="mb-6 rounded-lg border border-gray-200 bg-white p-4">
              <h3 className="mb-1 text-sm font-medium text-gray-900">
                Allowed Redirect Domains
              </h3>
              <p className="mb-3 text-xs text-gray-500">
                One domain per line. When configured, redirects to domains not
                on this list will be blocked. Leave empty to allow all domains
                (a warning will still be shown for external domains).
              </p>
              <textarea
                value={domainsText}
                onChange={(e) => setDomainsText(e.target.value)}
                rows={4}
                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono"
                placeholder={"example.com\nblog.example.com"}
              />
              <div className="mt-3 flex gap-2">
                <button
                  onClick={saveDomains}
                  disabled={savingDomains}
                  className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {savingDomains ? "Saving..." : "Save Domains"}
                </button>
                <button
                  onClick={() => setShowSettings(false)}
                  className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                >
                  Close
                </button>
              </div>
            </div>
          )}

          {showForm && (
            <div className="mb-6 rounded-lg border border-gray-200 bg-white p-4">
              <h3 className="mb-3 text-sm font-medium text-gray-900">
                {editing ? "Edit Redirect" : "New Redirect"}
              </h3>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label className="block text-xs font-medium text-gray-600">
                    Source URL
                  </label>
                  <input
                    type="text"
                    value={form.source_url}
                    onChange={(e) =>
                      setForm({ ...form, source_url: e.target.value })
                    }
                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    placeholder="/old-page/"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600">
                    Destination URL
                  </label>
                  <input
                    type="text"
                    value={form.destination_url}
                    onChange={(e) => {
                      setForm({ ...form, destination_url: e.target.value });
                      checkExternalDomain(e.target.value);
                    }}
                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    placeholder="/new-page/"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600">
                    Status Code
                  </label>
                  <select
                    value={form.status_code}
                    onChange={(e) =>
                      setForm({ ...form, status_code: Number(e.target.value) })
                    }
                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  >
                    {STATUS_CODES.map((c) => (
                      <option key={c} value={c}>
                        {c}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="flex items-center gap-2 pt-5">
                  <input
                    type="checkbox"
                    checked={form.is_regex === 1}
                    onChange={(e) =>
                      setForm({ ...form, is_regex: e.target.checked ? 1 : 0 })
                    }
                    className="h-4 w-4 rounded border-gray-300"
                  />
                  <label className="text-sm text-gray-700">Regex pattern</label>
                </div>
              </div>
              {warning && (
                <div className="mt-3 rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                  {warning}
                </div>
              )}
              <div className="mt-3 flex gap-2">
                <button
                  onClick={save}
                  disabled={saving}
                  className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {saving ? "Saving..." : "Save"}
                </button>
                <button
                  onClick={() => setShowForm(false)}
                  className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}

          {loading ? (
            <p className="text-sm text-gray-500">Loading...</p>
          ) : redirects.length === 0 ? (
            <p className="text-sm text-gray-500">No redirects configured.</p>
          ) : (
            <div className="overflow-x-auto rounded-lg border border-gray-200">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                      Source
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                      Destination
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                      Status
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                      Regex
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 bg-white">
                  {redirects.map((r) => {
                    const destHost = getDestinationHost(r.destination_url);
                    const siteHost = getSiteHost();
                    const isExternal = destHost && destHost !== siteHost;
                    const isBlocked =
                      isExternal &&
                      allowedDomains.length > 0 &&
                      !allowedDomains.includes(destHost);
                    return (
                      <tr key={r.id}>
                        <td className="px-4 py-3 text-sm font-mono text-gray-900">
                          {r.source_url}
                        </td>
                        <td className="px-4 py-3 text-sm font-mono text-gray-600">
                          {r.destination_url}
                          {isBlocked && (
                            <span className="ml-2 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">
                              Blocked
                            </span>
                          )}
                          {isExternal && !isBlocked && (
                            <span className="ml-2 rounded bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-800">
                              External
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-sm text-gray-600">
                          {r.status_code}
                        </td>
                        <td className="px-4 py-3 text-sm">
                          {r.is_regex == 1 ? (
                            <span className="rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">
                              Regex
                            </span>
                          ) : (
                            <span className="text-gray-400">&mdash;</span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-right text-sm">
                          <button
                            onClick={() => startEdit(r)}
                            className="text-blue-600 hover:underline"
                          >
                            Edit
                          </button>
                          <button
                            onClick={() => deleteRedirect(r.id)}
                            className="ml-3 text-red-600 hover:underline"
                          >
                            Delete
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {tab === "404-logs" && (
        <div>
          <div className="mb-4 flex items-center justify-between">
            <p className="text-sm text-gray-500">
              URLs that returned 404 errors, sorted by hit count.
            </p>
            <div className="flex gap-2">
              <button
                onClick={loadLogs}
                className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
              >
                Refresh
              </button>
              {logs.length > 0 && (
                <button
                  onClick={clearAllLogs}
                  className="rounded-md border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50"
                >
                  Clear All
                </button>
              )}
            </div>
          </div>

          {logsLoading ? (
            <p className="text-sm text-gray-500">Loading...</p>
          ) : logs.length === 0 ? (
            <p className="text-sm text-gray-500">No 404 errors logged yet.</p>
          ) : (
            <div className="space-y-3">
              {logs.map((log) => (
                <div
                  key={log.id}
                  className="rounded-lg border border-gray-200 bg-white p-4"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-sm text-gray-900 break-all">
                          {log.url}
                        </span>
                        <span className="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
                          {log.hit_count} {log.hit_count === 1 ? "hit" : "hits"}
                        </span>
                      </div>
                      <div className="mt-1 flex gap-4 text-xs text-gray-400">
                        {log.referer && (
                          <span>
                            Referrer:{" "}
                            <span className="text-gray-500">{log.referer}</span>
                          </span>
                        )}
                        <span>
                          Last hit:{" "}
                          <span className="text-gray-500">{log.last_hit}</span>
                        </span>
                      </div>

                      {suggestingFor === log.id &&
                        (loadingSuggestions[log.id] ? (
                          <p className="mt-2 text-xs text-gray-400">
                            Finding suggestions...
                          </p>
                        ) : suggestions[log.id]?.length > 0 ? (
                          <div className="mt-3 rounded-md border border-blue-200 bg-blue-50 p-3">
                            <p className="mb-2 text-xs font-medium text-blue-800">
                              Suggested redirects:
                            </p>
                            <div className="space-y-2">
                              {suggestions[log.id].map((s, i) => (
                                <div
                                  key={i}
                                  className="flex items-center justify-between gap-2"
                                >
                                  <div className="min-w-0 flex-1">
                                    <span className="font-mono text-sm text-blue-900">
                                      {s.url}
                                    </span>
                                    {s.title && (
                                      <span className="ml-2 text-xs text-blue-600">
                                        ({s.title})
                                      </span>
                                    )}
                                    <span className="ml-2 rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-700">
                                      {s.score}% match
                                    </span>
                                    <span className="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">
                                      {s.type}
                                    </span>
                                  </div>
                                  <button
                                    onClick={() =>
                                      createRedirectFromSuggestion(log, s)
                                    }
                                    disabled={saving}
                                    className="shrink-0 rounded-md bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                                  >
                                    Create Redirect
                                  </button>
                                </div>
                              ))}
                            </div>
                          </div>
                        ) : (
                          <p className="mt-2 text-xs text-gray-400">
                            No similar pages found.
                          </p>
                        ))}
                    </div>

                    <div className="flex shrink-0 gap-2">
                      <button
                        onClick={() => loadSuggestions(log.url, log.id)}
                        disabled={loadingSuggestions[log.id]}
                        className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                      >
                        {loadingSuggestions[log.id]
                          ? "..."
                          : "Suggest Redirect"}
                      </button>
                      <button
                        onClick={() => startRedirectFromLog(log)}
                        className="rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
                      >
                        Create Redirect
                      </button>
                      <button
                        onClick={() => deleteLogEntry(log.id)}
                        className="rounded-md px-3 py-1.5 text-xs text-red-600 hover:bg-red-50"
                      >
                        Dismiss
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {message && <p className="mt-3 text-sm text-green-600">{message}</p>}
    </div>
  );
}
