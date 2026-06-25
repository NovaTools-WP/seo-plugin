import React from "react";

const STATUS_STYLES = {
  green: {
    dot: "bg-green-500",
    border: "border-green-200",
    bg: "bg-green-50",
    text: "text-green-800",
  },
  yellow: {
    dot: "bg-yellow-500",
    border: "border-yellow-200",
    bg: "bg-yellow-50",
    text: "text-yellow-800",
  },
  red: {
    dot: "bg-red-500",
    border: "border-red-200",
    bg: "bg-red-50",
    text: "text-red-800",
  },
};

/**
 * Presentational list of analysis items.
 *
 * Shared item contract: { id, label, status: 'green'|'yellow'|'red', message }.
 * Extracted from product-tab/ContentAnalysisPanel so the post meta box
 * analysis sections and the product tab render identically.
 *
 * @param {{ items: Array<{id:string,label:string,status:string,message:string}> }} props
 */
export default function AnalysisList({ items }) {
  if (!items || items.length === 0) return null;

  return (
    <ul className="space-y-2">
      {items.map((item) => {
        const style = STATUS_STYLES[item.status] || STATUS_STYLES.red;
        return (
          <li
            key={item.id}
            className={`flex items-start gap-2 rounded border ${style.border} ${style.bg} p-2`}
          >
            <span
              className={`mt-0.5 inline-block h-2.5 w-2.5 shrink-0 rounded-full ${style.dot}`}
            />
            <div className="min-w-0">
              <span className="text-sm font-medium text-gray-800">
                {item.label}
              </span>
              <p className={`text-xs ${style.text}`}>{item.message}</p>
            </div>
          </li>
        );
      })}
    </ul>
  );
}
