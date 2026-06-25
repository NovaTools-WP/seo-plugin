import Field from "./Field";
import RepeatableGroup from "./RepeatableGroup";

/**
 * Renders one schema type: an enable checkbox and, when enabled, the type's
 * fields (scalars via <Field>, repeatable groups via <RepeatableGroup>).
 */
export default function TypeForm({ type, data, enabled, onToggle, onChange }) {
  return (
    <div className="rounded-md border border-gray-200 bg-white p-3">
      <label className="flex items-center gap-2">
        <input
          type="checkbox"
          checked={enabled}
          onChange={(e) => onToggle(e.target.checked)}
          className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <span className="text-sm font-medium text-gray-700">{type.label}</span>
      </label>

      {enabled && (
        <div className="mt-3 space-y-3">
          {(type.fields || []).map((f) =>
            f.type === "group" && f.multiple ? (
              <RepeatableGroup
                key={f.name}
                field={f}
                value={data?.[f.name]}
                onChange={(v) => onChange(f.name, v)}
              />
            ) : (
              <Field
                key={f.name}
                field={f}
                value={data?.[f.name]}
                onChange={(v) => onChange(f.name, v)}
              />
            ),
          )}
        </div>
      )}
    </div>
  );
}
