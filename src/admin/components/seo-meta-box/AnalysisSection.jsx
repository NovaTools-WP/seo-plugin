import React from "react";
import ScoreGauge from "../common/ScoreGauge";
import AnalysisList from "../common/AnalysisList";

/**
 * A collapsible analysis section: a score gauge + a list of check items.
 *
 * Used for the SEO score and the readability score in the post meta box.
 *
 * @param {{
 *   title: string,
 *   score?: number,
 *   items?: Array,
 *   loading?: boolean,
 *   defaultOpen?: boolean,
 *   note?: string,
 * }} props
 */
export default function AnalysisSection({
  title,
  score,
  items,
  loading = false,
  defaultOpen = true,
  note,
}) {
  const hasItems = items && items.length > 0;

  return (
    <details
      open={defaultOpen}
      className="rounded-md border border-gray-200 bg-white p-3"
    >
      <summary className="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700">
        <ScoreGauge percentage={typeof score === "number" ? score : 0} />
        <span className="flex-1">{title}</span>
        {loading ? (
          <span className="text-xs font-normal text-gray-400">Analyzing…</span>
        ) : (
          note && (
            <span className="text-xs font-normal text-gray-400">{note}</span>
          )
        )}
      </summary>

      <div className="mt-3">
        {hasItems ? (
          <AnalysisList items={items} />
        ) : (
          <p className="text-xs text-gray-400">
            {loading ? "Running analysis…" : "No data yet."}
          </p>
        )}
      </div>
    </details>
  );
}
