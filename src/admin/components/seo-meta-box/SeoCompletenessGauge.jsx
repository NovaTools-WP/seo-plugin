import React from "react";

const RADIUS = 36;
const STROKE = 5;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

function gaugeColor(pct) {
  if (pct >= 86) return "text-green-500";
  if (pct >= 50) return "text-yellow-500";
  return "text-red-500";
}

function gaugeStroke(pct) {
  if (pct >= 86) return "stroke-green-500";
  if (pct >= 50) return "stroke-yellow-500";
  return "stroke-red-500";
}

export default function SeoCompletenessGauge({
  checks,
  percentage,
  passed,
  total,
}) {
  const dashOffset = CIRCUMFERENCE - (percentage / 100) * CIRCUMFERENCE;

  return (
    <div className="rounded-md border border-gray-200 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-gray-700">
        SEO Completeness:{" "}
        <span className={gaugeColor(percentage)}>{percentage}%</span>
      </h3>

      <div className="flex items-start gap-4">
        {/* SVG gauge */}
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
              className={gaugeStroke(percentage)}
              strokeWidth={STROKE}
              strokeDasharray={CIRCUMFERENCE}
              strokeDashoffset={dashOffset}
              strokeLinecap="round"
              transform={`rotate(-90 ${RADIUS + STROKE} ${RADIUS + STROKE})`}
            />
          </svg>
          <span
            className={`absolute inset-0 flex items-center justify-center text-sm font-bold ${gaugeColor(
              percentage,
            )}`}
          >
            {percentage}%
          </span>
        </div>

        {/* Checklist */}
        <ul className="flex-1 space-y-1">
          {checks.map((check) => (
            <li key={check.key} className="flex items-start gap-1.5 text-xs">
              <span
                className={`mt-0.5 ${
                  check.present ? "text-green-600" : "text-red-400"
                }`}
              >
                {check.present ? "✓" : "✗"}
              </span>
              <div className="min-w-0">
                <span className="text-gray-700">{check.label}</span>
                {!check.present && (
                  <p className="text-gray-400">{check.suggestion}</p>
                )}
              </div>
            </li>
          ))}
        </ul>
      </div>

      <p className="mt-2 text-xs text-gray-400">
        {passed} of {total} fields complete
      </p>
    </div>
  );
}
