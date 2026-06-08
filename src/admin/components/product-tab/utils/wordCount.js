/**
 * Strip HTML tags and count words in the resulting text.
 * @param {string} html
 * @returns {number}
 */
export default function countWords(html) {
  if (!html || typeof html !== "string") return 0;
  const text = html.replace(/<[^>]*>/g, " ").replace(/&nbsp;/g, " ");
  const words = text.trim().split(/\s+/).filter(Boolean);
  return words.length;
}
