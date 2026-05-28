import React, { useState, useEffect } from "react";
import * as api from "../api";

const STATUS_CODES = [301, 302, 307, 308];

const EMPTY_FORM = {
  source_url: "",
  destination_url: "",
  status_code: 301,
  is_regex: 0,
};

export default function RedirectManager() {
  const [redirects, setRedirects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");

  useEffect(() => {
    load();
  }, []);

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

  function startEdit(r) {
    setEditing(r.id);
    setForm({
      source_url: r.source_url,
      destination_url: r.destination_url,
      status_code: Number(r.status_code),
      is_regex: Number(r.is_regex),
    });
    setShowForm(true);
  }

  function startAdd() {
    setEditing(null);
    setForm(EMPTY_FORM);
    setShowForm(true);
  }

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      const payload = { ...form };
      if (editing) payload.id = editing;
      await api.post("/redirects", payload);
      setMessage("Redirect saved.");
      setShowForm(false);
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
            Manage URL redirects with 301/302 status codes.
          </p>
        </div>
        <button
          onClick={startAdd}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Add Redirect
        </button>
      </div>

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
                onChange={(e) =>
                  setForm({ ...form, destination_url: e.target.value })
                }
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
              {redirects.map((r) => (
                <tr key={r.id}>
                  <td className="px-4 py-3 text-sm font-mono text-gray-900">
                    {r.source_url}
                  </td>
                  <td className="px-4 py-3 text-sm font-mono text-gray-600">
                    {r.destination_url}
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
                      <span className="text-gray-400">—</span>
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
              ))}
            </tbody>
          </table>
        </div>
      )}

      {message && <p className="mt-3 text-sm text-green-600">{message}</p>}
    </div>
  );
}
