import React, { useState, useEffect, useCallback } from "react";
import { get } from "../../api";

export default function PrimaryCategorySelector({ postId, value, onChange }) {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!postId) return;

    async function load() {
      setLoading(true);
      try {
        // Fetch product categories via WP REST
        const res = await fetch(
          `/wp-json/wp/v2/product_cat?post=${postId}`,
          { headers: { "X-WP-Nonce": window.novaToolsSEO?.nonce || "" } },
        );
        if (!res.ok) {
          // Fallback: use terms from post data
          const postRes = await get(`/post-meta/${postId}`);
          const catIds = postRes._wseo_product_cats || [];
          if (catIds.length > 0) {
            const catRes = await fetch(
              `/wp-json/wp/v2/product_cat?include=${catIds.join(",")}`,
              { headers: { "X-WP-Nonce": window.novaToolsSEO?.nonce || "" } },
            );
            if (catRes.ok) {
              setCategories(await catRes.json());
            }
          }
          return;
        }
        setCategories(await res.json());
      } catch {
        // Silently fail — component shows "assign categories" message
      } finally {
        setLoading(false);
      }
    }

    load();
  }, [postId]);

  // Auto-assign when only one category
  useEffect(() => {
    if (!loading && categories.length === 1 && !value) {
      onChange(categories[0].id);
    }
  }, [loading, categories, value, onChange]);

  if (loading) {
    return (
      <div className="rounded-md border border-gray-200 bg-white p-4">
        <h3 className="text-sm font-semibold text-gray-700">Primary Category</h3>
        <p className="mt-1 text-xs text-gray-400">Loading…</p>
      </div>
    );
  }

  if (categories.length === 0) {
    return (
      <div className="rounded-md border border-gray-200 bg-white p-4">
        <h3 className="text-sm font-semibold text-gray-700">Primary Category</h3>
        <p className="mt-1 text-xs text-yellow-700">
          Assign at least one category to set a primary category.
        </p>
      </div>
    );
  }

  if (categories.length === 1) {
    return (
      <div className="rounded-md border border-gray-200 bg-white p-4">
        <h3 className="text-sm font-semibold text-gray-700">Primary Category</h3>
        <p className="mt-1 text-xs text-green-700">
          {categories[0].name} <span className="text-gray-400">(auto-assigned)</span>
        </p>
        <input type="hidden" name="_wseo_primary_category" value={categories[0].id} />
      </div>
    );
  }

  return (
    <div className="rounded-md border border-gray-200 bg-white p-4">
      <label className="block text-sm font-semibold text-gray-700">Primary Category</label>
      <select
        value={value || ""}
        onChange={(e) => onChange(Number(e.target.value))}
        name="_wseo_primary_category"
        className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
      >
        <option value="">— Select —</option>
        {categories.map((cat) => (
          <option key={cat.id} value={cat.id}>
            {cat.name}
          </option>
        ))}
      </select>
      <p className="mt-1 text-xs text-gray-400">
        Used for permalink structure and breadcrumb trail.
      </p>
    </div>
  );
}
