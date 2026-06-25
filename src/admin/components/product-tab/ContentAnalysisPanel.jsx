import React from "react";
import AnalysisList from "../common/AnalysisList";

export default function ContentAnalysisPanel({ items }) {
  if (!items || items.length === 0) return null;

  const green = items.filter((i) => i.status === "green").length;
  const total = items.length;

  return (
    <div className="rounded-md border border-gray-200 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-gray-700">
        Content Analysis
        <span className="ml-2 text-xs font-normal text-gray-400">
          {green}/{total} passing
        </span>
      </h3>
      <AnalysisList items={items} />
    </div>
  );
}
