import React, { useState, useEffect } from "react";
import * as api from "../api";

const SCHEMA_PROPERTIES = [
  "color",
  "size",
  "material",
  "pattern",
  "weight",
  "width",
  "height",
  "depth",
  "flavor",
  "scent",
  "suggestedAge",
  "suggestedMaxAge",
  "suggestedGender",
  "energyEfficiencyClass",
  "brand",
  "model",
  "countryOfOrigin",
  "gtin",
  "mpn",
  "isbn",
];

function filterSuggestions(query) {
  if (!query || query.length < 1) return [];
  const lower = query.toLowerCase();
  return SCHEMA_PROPERTIES.filter((p) => p.toLowerCase().includes(lower));
}

function SchemaPropertyInput({ value, onChange }) {
  const [suggestions, setSuggestions] = useState([]);
  const [showDropdown, setShowDropdown] = useState(false);

  const handleChange = (e) => {
    const val = e.target.value;
    onChange(val);
    const filtered = filterSuggestions(val);
    setSuggestions(filtered);
    setShowDropdown(filtered.length > 0);
  };

  const selectSuggestion = (prop) => {
    onChange(prop);
    setShowDropdown(false);
  };

  return (
    <div style={{ position: "relative" }}>
      <input
        type="text"
        value={value}
        onChange={handleChange}
        onBlur={() => setTimeout(() => setShowDropdown(false), 200)}
        className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
        placeholder="e.g. color"
      />
      {showDropdown && suggestions.length > 0 && (
        <ul
          style={{
            position: "absolute",
            top: "100%",
            left: 0,
            right: 0,
            background: "#fff",
            border: "1px solid #d1d5db",
            borderTop: "none",
            maxHeight: "120px",
            overflowY: "auto",
            zIndex: 50,
            listStyle: "none",
            margin: 0,
            padding: 0,
          }}
        >
          {suggestions.map((prop) => (
            <li
              key={prop}
              onMouseDown={() => selectSuggestion(prop)}
              style={{
                padding: "6px 12px",
                cursor: "pointer",
                fontSize: "13px",
              }}
              onMouseOver={(e) => (e.target.style.background = "#f3f4f6")}
              onMouseOut={(e) => (e.target.style.background = "transparent")}
            >
              {prop}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default function AttributeMapping() {
  const [attributes, setAttributes] = useState([]);
  const [mappings, setMappings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");

  useEffect(() => {
    async function load() {
      try {
        const [attrs, maps] = await Promise.all([
          api.get("/woo-attributes"),
          api.get("/attribute-mappings"),
        ]);
        setAttributes(attrs);
        setMappings(
          Array.isArray(maps) && maps.length > 0
            ? maps
            : [{ attribute_slug: "", schema_property: "" }],
        );
      } catch {
        setMessage("Failed to load attribute data.");
      } finally {
        setLoading(false);
      }
    }
    load();
  }, []);

  const usedSlugs = mappings.map((m) => m.attribute_slug).filter(Boolean);

  const updateMapping = (index, field, value) => {
    setMappings((prev) => {
      const updated = [...prev];
      updated[index] = { ...updated[index], [field]: value };
      return updated;
    });
  };

  const addRow = () => {
    setMappings((prev) => [
      ...prev,
      { attribute_slug: "", schema_property: "" },
    ]);
  };

  const removeRow = (index) => {
    setMappings((prev) => prev.filter((_, i) => i !== index));
  };

  const handleSave = async () => {
    const valid = mappings.filter(
      (m) => m.attribute_slug && m.schema_property,
    );
    setSaving(true);
    setMessage("");
    try {
      await api.post("/attribute-mappings", valid);
      setMessage("Mappings saved successfully.");
      setMappings(
        valid.length > 0
          ? valid
          : [{ attribute_slug: "", schema_property: "" }],
      );
    } catch {
      setMessage("Failed to save mappings.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="text-gray-500">Loading...</div>;
  }

  return (
    <div>
      <p className="text-sm text-gray-600 mb-6">
        Map WooCommerce product attributes to schema.org properties. Mapped
        attributes will appear as native properties in product JSON-LD output.
      </p>

      {message && (
        <div
          className={`mb-4 rounded-md px-4 py-2 text-sm ${
            message.includes("success")
              ? "bg-green-50 text-green-700"
              : "bg-red-50 text-red-700"
          }`}
        >
          {message}
        </div>
      )}

      <div className="overflow-hidden rounded-md border border-gray-200">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                WooCommerce Attribute
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                Schema.org Property
              </th>
              <th className="px-4 py-3 w-16"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200 bg-white">
            {mappings.map((mapping, index) => (
              <tr key={index}>
                <td className="px-4 py-3">
                  <select
                    value={mapping.attribute_slug}
                    onChange={(e) =>
                      updateMapping(index, "attribute_slug", e.target.value)
                    }
                    className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  >
                    <option value="">Select attribute...</option>
                    {attributes.map((attr) => (
                      <option
                        key={attr.slug}
                        value={attr.slug}
                        disabled={
                          usedSlugs.includes(attr.slug) &&
                          attr.slug !== mapping.attribute_slug
                        }
                      >
                        {attr.label} ({attr.slug})
                      </option>
                    ))}
                  </select>
                </td>
                <td className="px-4 py-3">
                  <SchemaPropertyInput
                    value={mapping.schema_property}
                    onChange={(val) =>
                      updateMapping(index, "schema_property", val)
                    }
                  />
                </td>
                <td className="px-4 py-3 text-center">
                  <button
                    onClick={() => removeRow(index)}
                    className="text-red-500 hover:text-red-700 text-sm"
                    title="Remove mapping"
                  >
                    &times;
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mt-4 flex items-center gap-4">
        <button
          onClick={addRow}
          className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          + Add Mapping
        </button>
        <button
          onClick={handleSave}
          disabled={saving}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? "Saving..." : "Save Mappings"}
        </button>
      </div>
    </div>
  );
}
