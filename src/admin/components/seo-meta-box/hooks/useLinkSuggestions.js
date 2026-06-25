import { useEffect, useRef, useState } from "react";
import * as api from "../../../api";

/**
 * Fetch internal-link suggestions for the post being edited.
 *
 * Re-fetches (debounced) when the focus keyphrase changes, since keyphrase
 * relevance feeds the ranking — the live keyphrase is sent as a query param so
 * unsaved edits still affect ranking. Stale in-flight responses are ignored.
 *
 * @param {{ postId: string, keyphrase: string, limit?: number }} input
 * @returns {{ suggestions: array, loading: boolean, error: boolean }}
 */
export default function useLinkSuggestions({ postId, keyphrase, limit = 5 }) {
  const [suggestions, setSuggestions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const reqId = useRef(0);
  const timer = useRef(null);

  useEffect(() => {
    if (!postId) return undefined;

    if (timer.current) clearTimeout(timer.current);

    timer.current = setTimeout(async () => {
      const id = ++reqId.current;
      setLoading(true);
      setError(false);
      try {
        const params = new URLSearchParams({ limit: String(limit) });
        if (keyphrase) params.set("keyphrase", keyphrase);
        const res = await api.get(
          `/link-suggestions/${postId}?${params.toString()}`,
        );
        if (id === reqId.current) {
          setSuggestions(
            Array.isArray(res?.suggestions) ? res.suggestions : [],
          );
          setLoading(false);
          setError(false);
        }
      } catch {
        if (id === reqId.current) {
          setSuggestions([]);
          setLoading(false);
          setError(true);
        }
      }
    }, 500);

    return () => {
      if (timer.current) clearTimeout(timer.current);
    };
  }, [postId, keyphrase, limit]);

  return { suggestions, loading, error };
}
