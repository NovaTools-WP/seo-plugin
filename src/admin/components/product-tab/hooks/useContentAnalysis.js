import { useMemo } from "react";

/**
 * @typedef {Object} AnalysisItem
 * @property {string} id
 * @property {string} label
 * @property {'green'|'yellow'|'red'} status
 * @property {string} message
 */

/**
 * Orchestrates all content analysis checks for a WooCommerce product.
 * @param {Object} formState - Current field values from the product SEO tab.
 * @returns {AnalysisItem[]}
 */
export default function useContentAnalysis(formState) {
  return useMemo(() => {
    const items = [];

    // Short description analysis (delegated to caller's shortDescWordCount)
    const { shortDescWordCount } = formState;
    if (shortDescWordCount === 0 || shortDescWordCount == null) {
      items.push({
        id: "short-desc",
        label: "Short Description",
        status: "red",
        message:
          "Short description is missing. Add one for better snippet display.",
      });
    } else if (shortDescWordCount < 20) {
      items.push({
        id: "short-desc",
        label: "Short Description",
        status: "yellow",
        message: `${shortDescWordCount} words — too brief. Aim for 20–50 words for optimal snippet display.`,
      });
    } else if (shortDescWordCount > 50) {
      items.push({
        id: "short-desc",
        label: "Short Description",
        status: "yellow",
        message: `${shortDescWordCount} words — too long. Aim for 20–50 words for optimal snippet display.`,
      });
    } else {
      items.push({
        id: "short-desc",
        label: "Short Description",
        status: "green",
        message: `${shortDescWordCount} words — optimal length for snippet display.`,
      });
    }

    // Schema completeness fields
    const schemaFields = [
      {
        key: "gtin",
        label: "GTIN",
        missing: "Add a GTIN to improve rich snippet eligibility.",
      },
      {
        key: "brand",
        label: "Brand",
        missing: "Assign a brand to help Google identify your product.",
      },
      {
        key: "sku",
        label: "SKU",
        missing: "Add an SKU for product identification in search results.",
      },
      {
        key: "itemCondition",
        label: "Item Condition",
        missing: "Set the item condition to enhance product listing snippets.",
      },
      {
        key: "featuredImage",
        label: "Featured Image",
        missing:
          "Add a featured image — it appears in search results and social shares.",
      },
      {
        key: "metaDescription",
        label: "Meta Description",
        missing: "Write a meta description to control your search snippet.",
      },
      {
        key: "ogImage",
        label: "OG Image",
        missing: "Set an OG image for better social media previews.",
      },
    ];

    for (const field of schemaFields) {
      const value = formState[field.key];
      const present = Array.isArray(value) ? value.length > 0 : Boolean(value);
      items.push({
        id: `schema-${field.key}`,
        label: field.label,
        status: present ? "green" : "red",
        message: present ? `${field.label} is set.` : field.missing,
      });
    }

    // Gallery alt-text summary
    const { galleryMissingAlt, galleryTotal } = formState;
    if (galleryTotal === 0) {
      items.push({
        id: "gallery-alt",
        label: "Gallery Alt Text",
        status: "yellow",
        message: "No gallery images found. Add images to improve product SEO.",
      });
    } else if (galleryMissingAlt === 0) {
      items.push({
        id: "gallery-alt",
        label: "Gallery Alt Text",
        status: "green",
        message: `All ${galleryTotal} gallery images have alt text.`,
      });
    } else {
      items.push({
        id: "gallery-alt",
        label: "Gallery Alt Text",
        status: "red",
        message: `${galleryMissingAlt} of ${galleryTotal} images missing alt text.`,
      });
    }

    return items;
  }, [formState]);
}
