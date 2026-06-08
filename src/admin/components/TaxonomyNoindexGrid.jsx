import React, { useState, useEffect } from "react";
import * as api from "../api";

export default function TaxonomyNoindexGrid() {
  const [taxonomies, setTaxonomies] = useState({});
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");

  useEffect(() => {
    api.get("/woo/taxonomy-noindex").then((data) => {
      setTaxonomies(data.taxonomies || {});
    });
  }, []);

  function toggle(taxName) {
    setTaxonomies((prev) => ({
      ...prev,
      [taxName]: !prev[taxName],
    }));
  }

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      await api.post("/woo/taxonomy-noindex", { taxonomies });
      setMessage("Taxonomy settings saved.");
    } catch {
      setMessage("Error saving taxonomy settings.");
    }
    setSaving(false);
  }

  const entries = Object.entries(taxonomies);

  if (entries.length === 0) {
    return (
      <p className="text-sm text-gray-500">
        No WooCommerce taxonomies detected. Make sure WooCommerce is active.
      </p>
    );
  }

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700">
          Taxonomy Indexing Controls
        </label>
        <p className="mt-1 text-xs text-gray-400">
          Set taxonomies to noindex to prevent their term archive pages from
          being indexed. Noindexed taxonomies are also excluded from the XML
          sitemap.
        </p>
      </div>

      <div className="overflow-hidden rounded-md border border-gray-200">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                Taxonomy
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                Status
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                Action
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200 bg-white">
            {entries.map(([name, noindexed]) => (
              <tr key={name}>
                <td className="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                  {name}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-sm">
                  <span
                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                      noindexed
                        ? "bg-red-50 text-red-700"
                        : "bg-green-50 text-green-700"
                    }`}
                  >
                    {noindexed ? "Noindex, Nofollow" : "Index"}
                  </span>
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-sm">
                  <button
                    onClick={() => toggle(name)}
                    className={`rounded-md px-3 py-1 text-xs font-medium ${
                      noindexed
                        ? "bg-gray-100 text-gray-700 hover:bg-gray-200"
                        : "bg-red-50 text-red-700 hover:bg-red-100"
                    }`}
                  >
                    {noindexed ? "Set to Index" : "Set to Noindex"}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <button
        onClick={save}
        disabled={saving}
        className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
      >
        {saving ? "Saving..." : "Save Taxonomy Settings"}
      </button>
      {message && <p className="text-sm text-green-600">{message}</p>}
    </div>
  );
}
