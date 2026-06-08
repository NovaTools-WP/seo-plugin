import React, { useState, useEffect } from "react";
import * as api from "../api";

export default function WooFilterParams() {
  const [params, setParams] = useState([]);
  const [input, setInput] = useState("");
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");

  useEffect(() => {
    api.get("/woo/filter-params").then((data) => {
      setParams(data.params || []);
    });
  }, []);

  function addParam() {
    const trimmed = input.trim();
    if (!trimmed || params.includes(trimmed)) return;
    setParams([...params, trimmed]);
    setInput("");
  }

  function removeParam(index) {
    setParams(params.filter((_, i) => i !== index));
  }

  function handleKeyDown(e) {
    if (e.key === "Enter") {
      e.preventDefault();
      addParam();
    }
  }

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      await api.post("/woo/filter-params", { params });
      setMessage("Filter parameters saved.");
    } catch {
      setMessage("Error saving filter parameters.");
    }
    setSaving(false);
  }

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700">
          Filter Parameters to Noindex
        </label>
        <p className="mt-1 text-xs text-gray-400">
          URLs containing these query parameters will receive noindex, nofollow
          meta tags and X-Robots-Tag headers. Use{" "}
          <code className="rounded bg-gray-100 px-1">param_*</code> for
          wildcard matching.
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        {params.map((param, i) => (
          <span
            key={i}
            className="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2.5 py-1 text-sm text-blue-700"
          >
            {param}
            <button
              onClick={() => removeParam(i)}
              className="ml-1 text-blue-400 hover:text-blue-600"
            >
              &times;
            </button>
          </span>
        ))}
      </div>

      <div className="flex gap-2">
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={handleKeyDown}
          className="block w-64 rounded-md border border-gray-300 px-3 py-2 text-sm"
          placeholder="e.g. min_price or filter_*"
        />
        <button
          onClick={addParam}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          Add
        </button>
      </div>

      <button
        onClick={save}
        disabled={saving}
        className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
      >
        {saving ? "Saving..." : "Save Filter Parameters"}
      </button>
      {message && <p className="text-sm text-green-600">{message}</p>}
    </div>
  );
}
