import React, { useState, useEffect } from "react";
import * as api from "../../api";

const API_KEY_PATTERN = /^[a-zA-Z0-9]{8,128}$/;

export default function IndexNowSettings({ settings, setSetting }) {
  const [enabled, setEnabled] = useState(
    settings.wseo_indexnow_enabled === "1",
  );
  const [apiKey, setApiKey] = useState(settings.wseo_indexnow_api_key || "");
  const [keyError, setKeyError] = useState("");
  const [asAvailable, setAsAvailable] = useState(true);
  const [message, setMessage] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setEnabled(settings.wseo_indexnow_enabled === "1");
    setApiKey(settings.wseo_indexnow_api_key || "");
  }, [settings.wseo_indexnow_enabled, settings.wseo_indexnow_api_key]);

  useEffect(() => {
    setAsAvailable(!!window.novaToolsSEO?.actionSchedulerAvailable);
  }, []);

  function validateKey(key) {
    if (!key) {
      setKeyError("");
      return true;
    }
    if (!API_KEY_PATTERN.test(key)) {
      setKeyError("API key must be 8-128 alphanumeric characters.");
      return false;
    }
    setKeyError("");
    return true;
  }

  async function save() {
    if (!validateKey(apiKey)) return;

    setSaving(true);
    setMessage("");
    try {
      await api.post("/settings", {
        wseo_indexnow_enabled: enabled ? "1" : "",
        wseo_indexnow_api_key: apiKey,
      });
      setSetting("wseo_indexnow_enabled", enabled ? "1" : "");
      setSetting("wseo_indexnow_api_key", apiKey);
      setMessage("IndexNow settings saved.");
    } catch {
      setMessage("Error saving settings.");
    }
    setSaving(false);
  }

  return (
    <div>
      <h3 className="text-lg font-medium text-gray-800">
        IndexNow Integration
      </h3>
      <p className="mt-1 text-sm text-gray-500">
        Notify search engines instantly when content changes.
      </p>

      {!asAvailable && (
        <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
          Action Scheduler is not available. IndexNow requires Action Scheduler
          (bundled with WooCommerce) to process pings in the background. Install
          and activate WooCommerce, or another plugin providing Action
          Scheduler, to enable this feature.
        </div>
      )}

      <div className="mt-4 space-y-4">
        <div className="flex items-center gap-3">
          <label className="relative inline-flex cursor-pointer items-center">
            <input
              type="checkbox"
              checked={enabled}
              onChange={(e) => setEnabled(e.target.checked)}
              className="peer sr-only"
              disabled={!asAvailable}
            />
            <div className="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-disabled:opacity-50" />
          </label>
          <span className="text-sm font-medium text-gray-700">
            Enable IndexNow
          </span>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            API Key
          </label>
          <input
            type="text"
            value={apiKey}
            onChange={(e) => {
              setApiKey(e.target.value);
              validateKey(e.target.value);
            }}
            className={`mt-1 block w-full max-w-md rounded-md border px-3 py-2 text-sm ${
              keyError ? "border-red-500" : "border-gray-300"
            }`}
            placeholder="Enter your IndexNow API key"
            disabled={!asAvailable}
          />
          {keyError && <p className="mt-1 text-xs text-red-500">{keyError}</p>}
          <p className="mt-1 text-xs text-gray-400">
            Get your key from{" "}
            <a
              href="https://www.indexnow.org/documentation"
              target="_blank"
              rel="noopener noreferrer"
              className="text-blue-600 underline"
            >
              indexnow.org
            </a>
          </p>
        </div>
      </div>

      <div className="mt-4">
        <button
          onClick={save}
          disabled={saving || !asAvailable}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? "Saving..." : "Save IndexNow Settings"}
        </button>
        {message && (
          <p
            className={`mt-2 text-sm ${
              message.includes("Error") ? "text-red-600" : "text-green-600"
            }`}
          >
            {message}
          </p>
        )}
      </div>
    </div>
  );
}
