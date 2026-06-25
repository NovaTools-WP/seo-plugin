import { useState, useEffect } from "react";
import * as api from "../../../api";
import TypeForm from "./TypeForm";

/**
 * Collapsible "Schema / Structured Data" section for the SEO meta box.
 *
 * Fetches the schema-type registry config once (filtered by post type so e.g.
 * FAQ is hidden on products) and renders one TypeForm per type. State lives in
 * the parent (SeoMetaBox) so it rides the existing debounced save.
 *
 * @param {object}  props
 * @param {object}  props.schemaState    The `_wseo_schema` object keyed by type id.
 * @param {(updater: (prev: object) => object) => void} props.setSchemaState
 * @param {string}  props.postType       Current post type (for type filtering).
 */
export default function SchemaSection({
  schemaState,
  setSchemaState,
  postType,
}) {
  const [types, setTypes] = useState([]);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    const qs = postType ? `?post_type=${encodeURIComponent(postType)}` : "";
    api
      .get(`/schema-types${qs}`)
      .then((cfg) => setTypes(Array.isArray(cfg) ? cfg : []))
      .catch(() => setTypes([]))
      .finally(() => setLoaded(true));
  }, [postType]);

  const toggle = (type, checked) => {
    setSchemaState((prev) => {
      const next = { ...prev };
      if (checked) {
        const init = {};
        (type.fields || []).forEach((f) => {
          init[f.name] = f.type === "group" && f.multiple ? [] : "";
        });
        next[type.id] = init;
      } else {
        delete next[type.id];
      }
      return next;
    });
  };

  const updateField = (typeId, fieldName, value) => {
    setSchemaState((prev) => ({
      ...prev,
      [typeId]: { ...(prev[typeId] || {}), [fieldName]: value },
    }));
  };

  return (
    <details className="rounded-md border border-gray-200 p-3">
      <summary className="cursor-pointer text-sm font-medium text-gray-700">
        Schema / Structured Data
      </summary>
      <p className="mt-2 text-xs text-gray-400">
        Enable rich-result schema types for this content. Each enabled type is
        output as JSON-LD on the page.
      </p>
      <div className="mt-3 space-y-3">
        {types.map((t) => (
          <TypeForm
            key={t.id}
            type={t}
            data={schemaState[t.id]}
            enabled={t.id in schemaState}
            onToggle={(checked) => toggle(t, checked)}
            onChange={(fieldName, value) => updateField(t.id, fieldName, value)}
          />
        ))}
        {!loaded && (
          <p className="text-xs text-gray-400">Loading schema types…</p>
        )}
        {loaded && types.length === 0 && (
          <p className="text-xs text-gray-400">
            No schema types available for this content.
          </p>
        )}
      </div>
    </details>
  );
}
