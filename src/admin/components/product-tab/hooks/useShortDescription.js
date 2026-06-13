import { useState, useEffect, useRef } from "react";
import countWords from "../utils/wordCount";

/**
 * Tracks the WooCommerce product short description word count.
 * Uses wp.data for Block Editor, MutationObserver fallback for Classic Editor.
 * @returns {number} Current word count of the short description.
 */
export default function useShortDescription() {
  const [wordCount, setWordCount] = useState(0);
  const observerRef = useRef(null);

  useEffect(() => {
    // Try Block Editor first
    let unsubscribe = null;
    if (window.wp?.data?.select("core/editor")) {
      const select = () => {
        const excerpt = window.wp.data
          .select("core/editor")
          ?.getEditedPostAttribute("excerpt");
        return excerpt
          ? typeof excerpt === "string"
            ? excerpt
            : excerpt.raw || ""
          : "";
      };

      const update = () => setWordCount(countWords(select()));
      update();

      unsubscribe = window.wp.data.subscribe(update);
      return () => {
        if (unsubscribe) unsubscribe();
      };
    }

    // Classic Editor fallback — watch #excerpt textarea
    const textarea = document.getElementById("excerpt");
    if (textarea) {
      const update = () => setWordCount(countWords(textarea.value));
      update();

      observerRef.current = new MutationObserver(() => update());
      observerRef.current.observe(textarea, {
        attributes: true,
        childList: true,
        characterData: true,
        subtree: true,
      });

      textarea.addEventListener("input", update);
      return () => {
        observerRef.current?.disconnect();
        textarea.removeEventListener("input", update);
      };
    }
  }, []);

  return wordCount;
}
