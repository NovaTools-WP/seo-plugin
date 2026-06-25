import React from "react";

const RADIUS = 36;
const STROKE = 5;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

function textColor(pct) {
  if (pct >= 86) return "text-green-500";
  if (pct >= 50) return "text-yellow-500";
  return "text-red-500";
}

function strokeColor(pct) {
  if (pct >= 86) return "stroke-green-500";
  if (pct >= 50) return "stroke-yellow-500";
  return "stroke-red-500";
}

/**
 * Presentational circular score gauge (0–100).
 *
 * Extracted from SeoCompletenessGauge so both the completeness gauge and the
 * analysis score sections share one implementation. Color thresholds mirror
 * the existing gauge: ≥86 green, ≥50 yellow, otherwise red.
 *
 * @param {{ percentage: number }} props
 */
export default function ScoreGauge({ percentage }) {
  const pct = Math.max(0, Math.min(100, Number(percentage) || 0));
  const dashOffset = CIRCUMFERENCE - (pct / 100) * CIRCUMFERENCE;

  return (
    <div className="relative shrink-0">
      <svg width={(RADIUS + STROKE) * 2} height={(RADIUS + STROKE) * 2}>
        <circle
          cx={RADIUS + STROKE}
          cy={RADIUS + STROKE}
          r={RADIUS}
          fill="none"
          stroke="#e5e7eb"
          strokeWidth={STROKE}
        />
        <circle
          cx={RADIUS + STROKE}
          cy={RADIUS + STROKE}
          r={RADIUS}
          fill="none"
          className={strokeColor(pct)}
          strokeWidth={STROKE}
          strokeDasharray={CIRCUMFERENCE}
          strokeDashoffset={dashOffset}
          strokeLinecap="round"
          transform={`rotate(-90 ${RADIUS + STROKE} ${RADIUS + STROKE})`}
        />
      </svg>
      <span
        className={`absolute inset-0 flex items-center justify-center text-sm font-bold ${textColor(
          pct,
        )}`}
      >
        {pct}
      </span>
    </div>
  );
}
