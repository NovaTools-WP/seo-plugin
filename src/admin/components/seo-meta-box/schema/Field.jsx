/**
 * Renders a single scalar schema field by its declared `type`.
 *
 * Field types come from the PHP SchemaType::fields() definitions, mirrored here:
 * text, textarea, url, number, select, duration, datetime, date, boolean.
 */

const inputCls =
  "mt-1 block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500";

export default function Field({ field, value, onChange }) {
  const { name, label, type = "text", options = [], help, required } = field;

  const renderInput = () => {
    switch (type) {
      case "textarea":
        return (
          <textarea
            value={value || ""}
            onChange={(e) => onChange(e.target.value)}
            rows={2}
            className={inputCls}
            placeholder={help || label}
          />
        );

      case "url":
        return (
          <input
            type="url"
            value={value || ""}
            onChange={(e) => onChange(e.target.value)}
            className={inputCls}
            placeholder={help || "https://"}
          />
        );

      case "number":
        return (
          <input
            type="number"
            value={value ?? ""}
            min={field.min}
            max={field.max}
            step={field.integer ? 1 : "any"}
            onChange={(e) =>
              onChange(e.target.value === "" ? "" : Number(e.target.value))
            }
            className={inputCls}
          />
        );

      case "select":
        return (
          <select
            value={value || ""}
            onChange={(e) => onChange(e.target.value)}
            className={inputCls}
          >
            <option value="">— Select —</option>
            {options.map((o) => {
              const v = typeof o === "object" ? o.value : o;
              const l = typeof o === "object" ? o.label : o;
              return (
                <option key={v} value={v}>
                  {l}
                </option>
              );
            })}
          </select>
        );

      case "boolean":
        return (
          <input
            type="checkbox"
            checked={!!value}
            onChange={(e) => onChange(e.target.checked)}
            className="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
        );

      // duration / datetime / date are plain text inputs: the PHP sanitizer
      // validates the format (ISO 8601) and discards invalid values.
      case "duration":
      case "datetime":
      case "date":
      case "text":
      default:
        return (
          <input
            type="text"
            value={value || ""}
            onChange={(e) => onChange(e.target.value)}
            className={inputCls}
            placeholder={help || (type === "duration" ? "e.g. PT15M" : label)}
          />
        );
    }
  };

  return (
    <div>
      <label className="block text-xs font-medium text-gray-600">
        {label}
        {required && <span className="text-red-500"> *</span>}
      </label>
      {renderInput()}
      {help &&
        (type === "duration" || type === "datetime" || type === "url") && (
          <p className="mt-1 text-xs text-gray-400">{help}</p>
        )}
    </div>
  );
}
