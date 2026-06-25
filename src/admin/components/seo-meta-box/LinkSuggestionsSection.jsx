import React, { useState } from "react";
import { Link2, Copy, Check } from "lucide-react";

/**
 * Collapsible panel of internal-link suggestions.
 *
 * Each suggestion shows its title, a short excerpt, the reason it was ranked,
 * and a "copy link" control so the author can drop the URL into their content.
 *
 * @param {{
 *   suggestions: Array<{ id:number, title:string, permalink:string, excerpt:string, reason:string }>,
 *   loading?: boolean,
 *   error?: boolean,
 *   defaultOpen?: boolean,
 * }} props
 */
export default function LinkSuggestionsSection({
  suggestions = [],
  loading = false,
  error = false,
  defaultOpen = false,
}) {
  const [copiedId, setCopiedId] = useState(null);

  const copy = (permalink, id) => {
    if (navigator?.clipboard?.writeText) {
      navigator.clipboard.writeText(permalink).then(() => {
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 1500);
      });
    }
  };

  return (
    <details
      open={defaultOpen}
      className="rounded-md border border-gray-200 bg-white p-3"
    >
      <summary className="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700">
        <Link2 className="h-4 w-4 text-gray-400" />
        <span className="flex-1">Internal Link Suggestions</span>
        {loading && (
          <span className="text-xs font-normal text-gray-400">Loading…</span>
        )}
      </summary>

      <div className="mt-3">
        {suggestions.length > 0 ? (
          <ul className="space-y-2">
            {suggestions.map((s) => (
              <li
                key={s.id}
                className="rounded-md border border-gray-100 bg-gray-50 p-2.5"
              >
                <div className="flex items-start justify-between gap-2">
                  <a
                    href={s.permalink}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm font-medium text-gray-800 hover:text-blue-600 hover:underline"
                  >
                    {s.title}
                  </a>
                  <button
                    type="button"
                    onClick={() => copy(s.permalink, s.id)}
                    title="Copy link"
                    className="inline-flex shrink-0 items-center gap-1 rounded border border-gray-300 bg-white px-1.5 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-100 cursor-pointer"
                  >
                    {copiedId === s.id ? (
                      <>
                        <Check className="h-3 w-3 text-green-600" /> Copied
                      </>
                    ) : (
                      <>
                        <Copy className="h-3 w-3" /> Copy
                      </>
                    )}
                  </button>
                </div>
                {s.excerpt && (
                  <p className="mt-1 line-clamp-2 text-xs text-gray-500">
                    {s.excerpt}
                  </p>
                )}
                {s.reason && (
                  <p className="mt-1 text-[11px] font-medium text-blue-600">
                    {s.reason}
                  </p>
                )}
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-xs text-gray-400">
            {loading
              ? "Finding related content…"
              : error
              ? "Could not load suggestions. Try again in a moment."
              : "No suggestions yet. Add categories, tags, or a focus keyphrase to improve matches."}
          </p>
        )}
      </div>
    </details>
  );
}
