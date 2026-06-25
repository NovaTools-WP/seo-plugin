import Field from "./Field";

/**
 * Renders a repeatable group field: a stack of cards, each holding the
 * group's sub-fields, with add / remove / move-up / move-down controls.
 * The pattern mirrors src/admin/components/seo/FAQBuilder.jsx.
 */
export default function RepeatableGroup({ field, value, onChange }) {
  const items = Array.isArray(value) ? value : [];
  const subFields = field.fields || [];
  const singularLabel = field.singular_label || field.label;

  const blank = () => {
    const entry = {};
    subFields.forEach((f) => {
      entry[f.name] = "";
    });
    return entry;
  };

  const add = () => onChange([...items, blank()]);
  const remove = (i) => onChange(items.filter((_, idx) => idx !== i));

  const update = (i, fieldName, v) =>
    onChange(
      items.map((it, idx) => (idx === i ? { ...it, [fieldName]: v } : it)),
    );

  const moveUp = (i) => {
    if (i === 0) return;
    const next = [...items];
    [next[i - 1], next[i]] = [next[i], next[i - 1]];
    onChange(next);
  };

  const moveDown = (i) => {
    if (i === items.length - 1) return;
    const next = [...items];
    [next[i], next[i + 1]] = [next[i + 1], next[i]];
    onChange(next);
  };

  const btn =
    "rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-200 disabled:opacity-30 disabled:hover:bg-transparent";

  return (
    <div>
      <label className="block text-xs font-medium text-gray-600">
        {field.label}
      </label>
      <div className="mt-1 space-y-2">
        {items.map((item, i) => (
          <div
            key={i}
            className="rounded-md border border-gray-200 bg-gray-50 p-3"
          >
            <div className="mb-2 flex justify-end gap-0.5">
              <button
                type="button"
                onClick={() => moveUp(i)}
                disabled={i === 0}
                className={btn}
                title="Move up"
              >
                ↑
              </button>
              <button
                type="button"
                onClick={() => moveDown(i)}
                disabled={i === items.length - 1}
                className={btn}
                title="Move down"
              >
                ↓
              </button>
              <button
                type="button"
                onClick={() => remove(i)}
                className="rounded px-1.5 py-0.5 text-xs text-red-500 hover:bg-red-50"
                title="Remove"
              >
                ×
              </button>
            </div>
            <div className="space-y-2">
              {subFields.map((sf) => (
                <Field
                  key={sf.name}
                  field={sf}
                  value={item[sf.name]}
                  onChange={(v) => update(i, sf.name, v)}
                />
              ))}
            </div>
          </div>
        ))}
      </div>
      <button
        type="button"
        onClick={add}
        className="mt-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
      >
        + Add {singularLabel}
      </button>
    </div>
  );
}
