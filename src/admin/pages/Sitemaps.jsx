import React, { useState, useEffect } from "react";
import * as Switch from "@radix-ui/react-switch";
import * as api from "../api";

export default function Sitemaps() {
  const [enabled, setEnabled] = useState(true);
  const [threshold, setThreshold] = useState(30);
  const [rebuilding, setRebuilding] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");
  const postTypes = window.novaToolsSEO?.postTypes || [];
  const hasWooCommerce = window.novaToolsSEO?.hasWooCommerce || false;

  useEffect(() => {
    api.get("/settings").then((s) => {
      setEnabled(s.wseo_sitemap_enabled !== "0");
      if (s.wseo_outofstock_threshold !== undefined) {
        setThreshold(parseInt(s.wseo_outofstock_threshold, 10) || 0);
      }
    });
  }, []);

  async function toggleEnabled(checked) {
    setEnabled(checked);
    setSaving(true);
    setMessage("");
    try {
      await api.post("/settings", { wseo_sitemap_enabled: checked ? "1" : "0" });
      setMessage("Sitemap " + (checked ? "enabled" : "disabled") + ".");
    } catch {
      setMessage("Error updating setting.");
    }
    setSaving(false);
  }

  async function saveThreshold() {
    const value = Math.max(0, parseInt(threshold, 10) || 0);
    setThreshold(value);
    setSaving(true);
    setMessage("");
    try {
      await api.post("/settings", { wseo_outofstock_threshold: String(value) });
      setMessage("Threshold saved.");
    } catch {
      setMessage("Error saving threshold.");
    }
    setSaving(false);
  }

  async function rebuild() {
    setRebuilding(true);
    setMessage("");
    try {
      await api.post("/sitemap/rebuild", {});
      setMessage("Sitemap rebuilt successfully.");
    } catch {
      setMessage("Error rebuilding sitemap.");
    }
    setRebuilding(false);
  }

  const siteUrl = window.novaToolsSEO?.apiUrl
    ? new URL(window.novaToolsSEO.apiUrl).origin + "/"
    : "/";

  return (
    <div>
      <h2 className="text-2xl font-semibold text-gray-900">Sitemaps</h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Manage XML sitemap generation and settings.
      </p>

      <div className="max-w-xl space-y-6">
        <div className="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4">
          <div>
            <p className="text-sm font-medium text-gray-900">
              XML Sitemap
            </p>
            <p className="text-xs text-gray-500">
              Generate static sitemaps on content changes
            </p>
          </div>
          <Switch.Root
            checked={enabled}
            onCheckedChange={toggleEnabled}
            className="relative h-6 w-11 rounded-full bg-gray-200 data-[state=checked]:bg-blue-600"
          >
            <Switch.Thumb className="block h-5 w-5 translate-x-0.5 rounded-full bg-white shadow transition-transform data-[state=checked]:translate-x-[22px]" />
          </Switch.Root>
        </div>

        {saving && <p className="text-xs text-gray-400">Saving...</p>}

        {enabled && (
          <>
            <div>
              <button
                onClick={rebuild}
                disabled={rebuilding}
                className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
              >
                {rebuilding ? "Rebuilding..." : "Rebuild Sitemaps Now"}
              </button>
            </div>

            {hasWooCommerce && (
              <div className="rounded-lg border border-gray-200 bg-white p-4">
                <label
                  htmlFor="outofstock-threshold"
                  className="block text-sm font-medium text-gray-900"
                >
                  Remove out-of-stock products after
                </label>
                <div className="mt-2 flex items-center gap-2">
                  <input
                    id="outofstock-threshold"
                    type="number"
                    min="0"
                    step="1"
                    value={threshold}
                    onChange={(e) => {
                      const v = parseInt(e.target.value, 10);
                      setThreshold(isNaN(v) ? "" : Math.max(0, v));
                    }}
                    className="w-20 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                  <span className="text-sm text-gray-600">days</span>
                  <button
                    onClick={saveThreshold}
                    disabled={saving}
                    className="ml-2 rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                  >
                    Save
                  </button>
                </div>
                <p className="mt-1 text-xs text-gray-500">
                  Set to 0 to exclude out-of-stock products immediately.
                </p>
              </div>
            )}

            <div>
              <h3 className="mb-2 text-sm font-medium text-gray-700">
                Sitemap URLs
              </h3>
              <ul className="space-y-1">
                <li>
                  <a
                    href={siteUrl + "sitemap.xml"}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm text-blue-600 hover:underline"
                  >
                    {siteUrl}sitemap.xml
                  </a>
                  <span className="ml-2 text-xs text-gray-400">
                    (index)
                  </span>
                </li>
                {postTypes.map((t) => (
                  <li key={t.name}>
                    <a
                      href={siteUrl + "sitemap-" + t.name + ".xml"}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-sm text-blue-600 hover:underline"
                    >
                      {siteUrl}sitemap-{t.name}.xml
                    </a>
                    <span className="ml-2 text-xs text-gray-400">
                      ({t.label})
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          </>
        )}

        {message && <p className="text-sm text-green-600">{message}</p>}
      </div>
    </div>
  );
}
