/**
 * Capture the live post body content for content analysis.
 *
 * Classic editor (TinyMCE): read from the active editor so the analysis
 * updates as the user types. Block editor (Gutenberg): the SEO meta box has
 * no DOM access to block content, so return null and let the server fall
 * back to the saved post_content (analysis reflects the last saved state).
 *
 * @returns {string|null} HTML content, or null when unavailable.
 */
export default function getEditorContent() {
  // Classic editor — TinyMCE active editor.
  if (window.tinymce) {
    const editor = window.tinymce.get("content");
    if (editor && !editor.isHidden()) {
      return editor.getContent();
    }
  }

  // Classic editor — plain textarea (HTML tab or no TinyMCE).
  const textarea = document.getElementById("content");
  if (textarea) {
    return textarea.value;
  }

  // Block editor — no DOM access from the meta box.
  return null;
}

/**
 * Whether the block editor (Gutenberg) is active on this screen.
 *
 * Used to show users why content analysis reflects the last saved state
 * rather than live typing in the block editor.
 *
 * @returns {boolean}
 */
export function isBlockEditor() {
  if (!document.body) return false;
  return (
    document.body.classList.contains("block-editor-page") ||
    !!window.wp?.data?.select?.("core/block-editor")
  );
}
