import { useMemo } from "react";

/**
 * SEO fields checked for the completeness gauge.
 *
 * Must stay in sync with ContentListSeoColumn::SCORE_FIELDS in PHP.
 */
const SEO_FIELDS = [
  {
    key: "seoTitle",
    label: "SEO Title",
    suggestion: "Add a compelling SEO title under 60 characters.",
  },
  {
    key: "metaDescription",
    label: "Meta Description",
    suggestion: "Write a meta description to control your search snippet.",
  },
  {
    key: "ogImage",
    label: "OG Image",
    suggestion: "Set an OG image for better social media previews.",
  },
  {
    key: "featuredImage",
    label: "Featured Image",
    suggestion:
      "Add a featured image — it appears in search results and social shares.",
  },
  {
    key: "ogTitle",
    label: "OG Title",
    suggestion: "Set an OG title for better social sharing.",
  },
  {
    key: "ogDescription",
    label: "OG Description",
    suggestion: "Add an OG description for social media previews.",
  },
  {
    key: "canonical",
    label: "Canonical URL",
    suggestion: "Set a canonical URL to avoid duplicate content issues.",
  },
  {
    key: "robots",
    label: "Robots Directive",
    suggestion: "Explicitly set robots to control search engine indexing.",
  },
];

/**
 * Computes SEO field completeness from form state.
 *
 * @param {Object} formState
 * @returns {{ checks: Array, percentage: number, passed: number, total: number }}
 */
export default function useSeoCompleteness(formState) {
  return useMemo(() => {
    const total = SEO_FIELDS.length;
    let passed = 0;

    const checks = SEO_FIELDS.map((field) => {
      const value = formState[field.key];
      const present = Array.isArray(value) ? value.length > 0 : Boolean(value);
      if (present) passed++;
      return {
        key: field.key,
        label: field.label,
        present,
        suggestion: field.suggestion,
      };
    });

    const percentage = total > 0 ? Math.round((passed / total) * 100) : 0;

    return { checks, percentage, passed, total };
  }, [formState]);
}
