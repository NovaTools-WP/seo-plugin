import { useMemo } from "react";

const SCHEMA_FIELDS = [
  { key: "gtin", label: "GTIN", suggestion: "Add a GTIN to improve rich snippet eligibility." },
  { key: "brand", label: "Brand", suggestion: "Assign a brand to help Google identify your product." },
  { key: "sku", label: "SKU", suggestion: "Add an SKU for product identification in search results." },
  {
    key: "itemCondition",
    label: "Item Condition",
    suggestion: "Set the item condition to enhance product listing snippets.",
  },
  {
    key: "featuredImage",
    label: "Featured Image",
    suggestion: "Add a featured image — it appears in search results and social shares.",
  },
  {
    key: "metaDescription",
    label: "Meta Description",
    suggestion: "Write a meta description to control your search snippet.",
  },
  { key: "ogImage", label: "OG Image", suggestion: "Set an OG image for better social media previews." },
];

/**
 * Computes schema field completeness from form state.
 * @param {Object} formState
 * @returns {{ checks: Array, percentage: number, passed: number, total: number }}
 */
export default function useSchemaCompleteness(formState) {
  return useMemo(() => {
    const total = SCHEMA_FIELDS.length;
    let passed = 0;

    const checks = SCHEMA_FIELDS.map((field) => {
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
