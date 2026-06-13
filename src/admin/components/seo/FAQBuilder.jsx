import React, { useState, useEffect, useCallback } from "react";

export default function FAQBuilder({ postId, initialFaq, container }) {
  const [faq, setFaq] = useState(() => {
    if (Array.isArray(initialFaq)) return initialFaq;
    return [];
  });

  const addEntry = () => {
    setFaq((prev) => [...prev, { question: "", answer: "" }]);
  };

  const removeEntry = (index) => {
    setFaq((prev) => prev.filter((_, i) => i !== index));
  };

  const updateEntry = (index, field, value) => {
    setFaq((prev) => {
      const updated = [...prev];
      updated[index] = { ...updated[index], [field]: value };
      return updated;
    });
  };

  const moveUp = (index) => {
    if (index === 0) return;
    setFaq((prev) => {
      const updated = [...prev];
      [updated[index - 1], updated[index]] = [
        updated[index],
        updated[index - 1],
      ];
      return updated;
    });
  };

  const moveDown = (index) => {
    if (index === faq.length - 1) return;
    setFaq((prev) => {
      const updated = [...prev];
      [updated[index], updated[index + 1]] = [
        updated[index + 1],
        updated[index],
      ];
      return updated;
    });
  };

  // Sync hidden field for WooCommerce save
  useEffect(() => {
    if (!container) return;
    const form = container.closest("form");
    if (!form) return;

    // Remove old hidden inputs
    form
      .querySelectorAll('input[name="_wseo_faq"]')
      .forEach((el) => el.remove());

    if (faq.length > 0) {
      const hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = "_wseo_faq";
      hidden.value = JSON.stringify(faq);
      form.appendChild(hidden);
    }
  }, [faq, container]);

  const inputCls =
    "w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500";

  return (
    <div className="rounded-md border border-gray-200 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-gray-700">
        FAQ / Q&A (FAQPage Schema)
      </h3>

      <div className="space-y-3">
        {faq.map((entry, i) => (
          <div
            key={i}
            className="rounded-md border border-gray-200 bg-gray-50 p-3"
          >
            <div className="flex items-start gap-2">
              <input
                type="text"
                value={entry.question}
                onChange={(e) => updateEntry(i, "question", e.target.value)}
                placeholder="Question..."
                className={inputCls}
              />
              <div className="flex shrink-0 gap-0.5 pt-1">
                <button
                  type="button"
                  onClick={() => moveUp(i)}
                  disabled={i === 0}
                  className="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-200 disabled:opacity-30 disabled:hover:bg-transparent"
                  title="Move up"
                >
                  ↑
                </button>
                <button
                  type="button"
                  onClick={() => moveDown(i)}
                  disabled={i === faq.length - 1}
                  className="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-200 disabled:opacity-30 disabled:hover:bg-transparent"
                  title="Move down"
                >
                  ↓
                </button>
                <button
                  type="button"
                  onClick={() => removeEntry(i)}
                  className="rounded px-1.5 py-0.5 text-xs text-red-500 hover:bg-red-50"
                  title="Remove"
                >
                  ×
                </button>
              </div>
            </div>
            <textarea
              value={entry.answer}
              onChange={(e) => updateEntry(i, "answer", e.target.value)}
              placeholder="Answer..."
              rows={2}
              className="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
        ))}
      </div>

      <button
        type="button"
        onClick={addEntry}
        className="mt-3 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
      >
        + Add FAQ Entry
      </button>
    </div>
  );
}
