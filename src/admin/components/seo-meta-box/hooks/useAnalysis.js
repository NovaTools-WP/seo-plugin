import { useEffect, useRef, useState } from "react";
import * as api from "../../../api";
import getEditorContent from "../utils/editorContent";

const DEBOUNCE_MS = 600;
const TINYMCE_POLL_MS = 1500;

/**
 * Debounced content/readability/keyphrase analysis for the post meta box.
 *
 * Re-runs (debounced) whenever the focus keyphrase, SEO title, meta
 * description, or the editor body content changes. Content is read live from
 * the classic editor (TinyMCE/#content); in the block editor the server
 * falls back to saved post_content. Stale in-flight responses are ignored.
 *
 * @param {{ postId: string, keyphrase: string, title: string, description: string }} input
 * @returns {{ readability: object|null, seo: object|null, loading: boolean }}
 */
export default function useAnalysis({ postId, keyphrase, title, description }) {
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(false);
  const reqId = useRef(0);
  const timer = useRef(null);
  // Bumped whenever the classic-editor body changes so body edits re-trigger
  // analysis even when the keyphrase/title/description are unchanged.
  const [contentVersion, setContentVersion] = useState(0);

  // Watch the classic editor body for changes.
  useEffect(() => {
    if (!postId) return undefined;

    const bump = () => setContentVersion((v) => v + 1);
    let editor = null;
    let poll = null;

    const attach = () => {
      if (editor) return true;
      if (window.tinymce) {
        editor = window.tinymce.get("content");
        if (editor) {
          editor.on("input NodeChange", bump);
          return true;
        }
      }
      return false;
    };

    const textarea = document.getElementById("content");
    if (textarea) textarea.addEventListener("input", bump);

    if (!attach()) {
      // TinyMCE may initialize after this component mounts.
      poll = setInterval(() => {
        if (attach()) clearInterval(poll);
      }, TINYMCE_POLL_MS);
    }

    return () => {
      if (poll) clearInterval(poll);
      if (editor) editor.off("input NodeChange", bump);
      if (textarea) textarea.removeEventListener("input", bump);
    };
  }, [postId]);

  // Debounced analysis request.
  useEffect(() => {
    if (!postId) return undefined;

    if (timer.current) clearTimeout(timer.current);
    setLoading(true);

    timer.current = setTimeout(async () => {
      const id = ++reqId.current;
      const content = getEditorContent();
      const body = {
        post_id: Number(postId),
        keyphrase: keyphrase || "",
        title: title || "",
        description: description || "",
      };
      if (content) body.content = content;

      try {
        const res = await api.post("/analyze", body);
        if (id === reqId.current) {
          setResult(res);
          setLoading(false);
        }
      } catch {
        if (id === reqId.current) {
          setLoading(false);
        }
      }
    }, DEBOUNCE_MS);

    return () => {
      if (timer.current) clearTimeout(timer.current);
    };
  }, [postId, keyphrase, title, description, contentVersion]);

  return {
    readability: result?.readability ?? null,
    seo: result?.seo ?? null,
    loading,
  };
}
