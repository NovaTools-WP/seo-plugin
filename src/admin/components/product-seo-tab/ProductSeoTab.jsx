import React, { useState, useEffect, useCallback, useMemo } from "react";
import * as api from "../../api";
import ContentAnalysisPanel from "../product-tab/ContentAnalysisPanel";
import GalleryAltTextScanner from "../product-tab/GalleryAltTextScanner";
import PrimaryCategorySelector from "../product-tab/PrimaryCategorySelector";
import SchemaCompletenessGauge from "../product-tab/SchemaCompletenessGauge";
import useContentAnalysis from "../product-tab/hooks/useContentAnalysis";
import useShortDescription from "../product-tab/hooks/useShortDescription";
import useGalleryImages from "../product-tab/hooks/useGalleryImages";
import useSchemaCompleteness from "../product-tab/hooks/useSchemaCompleteness";
import FAQBuilder from "../seo/FAQBuilder";

const CONDITIONS = [
  { value: "NewCondition", label: "New" },
  { value: "UsedCondition", label: "Used" },
  { value: "RefurbishedCondition", label: "Refurbished" },
  { value: "DamagedCondition", label: "Damaged" },
];

export default function ProductSeoTab() {
  const container = document.getElementById("wseo-product-schema-tab");
  const postId = container?.dataset?.postId || "";
  const initialMeta = container?.dataset?.meta
    ? JSON.parse(container.dataset.meta)
    : {};

  const [gtin, setGtin] = useState(initialMeta._wseo_gtin || "");
  const [mpn, setMpn] = useState(initialMeta._wseo_mpn || "");
  const [isbn, setIsbn] = useState(initialMeta._wseo_isbn || "");
  const [brand, setBrand] = useState(initialMeta._wseo_brand || "");
  const [itemCondition, setItemCondition] = useState(
    initialMeta._wseo_item_condition || "NewCondition",
  );
  const [primaryCategory, setPrimaryCategory] = useState(
    initialMeta._wseo_primary_category || "",
  );
  const [faq, setFaq] = useState(initialMeta._wseo_faq || []);
  const [localInventory, setLocalInventory] = useState(
    initialMeta._wseo_local_inventory === "1" ||
      initialMeta._wseo_local_inventory === true,
  );

  const [brandSuggestions, setBrandSuggestions] = useState([]);
  const [showBrandDropdown, setShowBrandDropdown] = useState(false);
  const hasBrandTaxonomy = window.wseoProductTab?.hasBrandTaxonomy || false;

  const [saving, setSaving] = useState(false);

  // LocalBusiness config check for LocalInventory warning
  const [localBusinessConfigured, setLocalBusinessConfigured] = useState(null);

  useEffect(() => {
    api
      .get("/settings")
      .then((s) => {
        const lb = s.wseo_local_seo;
        setLocalBusinessConfigured(!!(lb && lb.business_name));
      })
      .catch(() => {
        setLocalBusinessConfigured(false);
      });
  }, []);

  // Read WooCommerce SKU from the General tab's _sku input
  const [wooSku, setWooSku] = useState(initialMeta._sku || "");

  // Read meta description / OG image from SeoMetaBox hidden fields
  const [metaDescription, setMetaDescription] = useState("");
  const [ogImage, setOgImage] = useState("");
  const [featuredImage, setFeaturedImage] = useState("");

  useEffect(() => {
    const poll = () => {
      const descInput = document.querySelector(
        'input[name="_wseo_description"]',
      );
      if (descInput) setMetaDescription(descInput.value);
      const ogInput = document.querySelector('input[name="_wseo_og_image"]');
      if (ogInput) setOgImage(ogInput.value);
      const skuInput =
        document.querySelector("#_sku") ||
        document.querySelector('input[name="_sku"]');
      if (skuInput) setWooSku(skuInput.value);
    };
    const interval = setInterval(poll, 1000);
    poll();
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    const thumbInput = document.querySelector('input[name="_thumbnail_id"]');
    if (thumbInput) setFeaturedImage(thumbInput.value);
    const observer = new MutationObserver(() => {
      const input = document.querySelector('input[name="_thumbnail_id"]');
      if (input) setFeaturedImage(input.value);
    });
    observer.observe(document.body, { childList: true, subtree: true });
    return () => observer.disconnect();
  }, []);

  const syncHiddenFields = useCallback(() => {
    const fields = {
      _wseo_gtin: gtin,
      _wseo_mpn: mpn,
      _wseo_isbn: isbn,
      _wseo_brand: brand,
      _wseo_item_condition: itemCondition,
    };

    Object.entries(fields).forEach(([name, value]) => {
      const input = document.querySelector(`input[name="${name}"]`);
      if (input) {
        input.value = value;
      } else {
        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = name;
        hidden.value = value;
        const form = container?.closest("form");
        if (form) form.appendChild(hidden);
      }
    });
  }, [gtin, mpn, isbn, brand, itemCondition, container]);

  useEffect(() => {
    syncHiddenFields();
  }, [syncHiddenFields]);

  // Hooks
  const shortDescWordCount = useShortDescription();
  const {
    images: galleryImages,
    total: galleryTotal,
    missingAlt: galleryMissingAlt,
    loading: galleryLoading,
    generating,
    bulkGenerate,
    error: galleryError,
  } = useGalleryImages(postId, initialMeta._product_image_gallery);

  // Build form state for analysis hooks — uses actual WooCommerce SKU
  const formState = useMemo(
    () => ({
      shortDescWordCount,
      gtin,
      brand,
      sku: wooSku,
      itemCondition,
      featuredImage,
      metaDescription,
      ogImage,
      galleryMissingAlt,
      galleryTotal,
    }),
    [
      shortDescWordCount,
      gtin,
      brand,
      wooSku,
      itemCondition,
      featuredImage,
      metaDescription,
      ogImage,
      galleryMissingAlt,
      galleryTotal,
    ],
  );

  const analysisItems = useContentAnalysis(formState);
  const completeness = useSchemaCompleteness(formState);

  const handlePrimaryCategoryChange = useCallback(
    (termId) => {
      setPrimaryCategory(termId);
      const input = document.querySelector(
        'input[name="_wseo_primary_category"]',
      );
      if (input) {
        input.value = termId;
      } else {
        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = "_wseo_primary_category";
        hidden.value = termId;
        const form = container?.closest("form");
        if (form) form.appendChild(hidden);
      }
    },
    [container],
  );

  const fetchBrandSuggestions = async (query) => {
    if (!hasBrandTaxonomy || query.length < 2) {
      setBrandSuggestions([]);
      setShowBrandDropdown(false);
      return;
    }

    try {
      const res = await fetch(
        `/wp-json/wp/v2/product_brand?search=${encodeURIComponent(
          query,
        )}&per_page=10`,
        { headers: { "X-WP-Nonce": window.novaToolsSEO?.nonce || "" } },
      );
      const terms = await res.json();
      setBrandSuggestions(terms.map((t) => t.name));
      setShowBrandDropdown(terms.length > 0);
    } catch {
      setBrandSuggestions([]);
      setShowBrandDropdown(false);
    }
  };

  const handleBrandChange = (e) => {
    const val = e.target.value;
    setBrand(val);
    fetchBrandSuggestions(val);
  };

  const selectBrand = (name) => {
    setBrand(name);
    setShowBrandDropdown(false);
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.post(`/post-meta/${postId}`, {
        _wseo_gtin: gtin,
        _wseo_mpn: mpn,
        _wseo_isbn: isbn,
        _wseo_brand: brand,
        _wseo_item_condition: itemCondition,
      });
    } catch {
      // Silent fail — WooCommerce save will also persist via hidden fields
    }
    setSaving(false);
  };

  useEffect(() => {
    const form = container?.closest("form");
    if (!form) return;
    const handler = () => handleSave();
    form.addEventListener("submit", handler);
    return () => form.removeEventListener("submit", handler);
  }, [container, gtin, mpn, isbn, brand, itemCondition]);

  const inputCls =
    "w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500";

  return (
    <div className="p-3 space-y-4">
      {/* Content Analysis */}
      <ContentAnalysisPanel items={analysisItems} />

      {/* Schema Completeness */}
      <SchemaCompletenessGauge {...completeness} />

      {/* Product Schema Fields */}
      <div className="rounded-md border border-gray-200 bg-white p-4">
        <h3 className="mb-3 text-sm font-semibold text-gray-700">
          Product Schema Data
        </h3>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">
              GTIN (UPC/EAN)
            </label>
            <input
              type="text"
              value={gtin}
              onChange={(e) => setGtin(e.target.value)}
              className={inputCls}
              placeholder="e.g. 01234567890123"
            />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">
              MPN
            </label>
            <input
              type="text"
              value={mpn}
              onChange={(e) => setMpn(e.target.value)}
              className={inputCls}
              placeholder="Manufacturer Part Number"
            />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">
              ISBN
            </label>
            <input
              type="text"
              value={isbn}
              onChange={(e) => setIsbn(e.target.value)}
              className={inputCls}
              placeholder="e.g. 978-3-16-148410-0"
            />
          </div>
          <div className="relative">
            <label className="mb-1 block text-xs font-medium text-gray-600">
              Brand
            </label>
            <input
              type="text"
              value={brand}
              onChange={handleBrandChange}
              onBlur={() => setTimeout(() => setShowBrandDropdown(false), 200)}
              className={inputCls}
              placeholder={
                hasBrandTaxonomy ? "Search or type brand..." : "Brand name"
              }
            />
            {showBrandDropdown && brandSuggestions.length > 0 && (
              <ul className="absolute left-0 right-0 top-full z-50 max-h-40 overflow-y-auto rounded-b-md border border-gray-200 bg-white py-1 text-sm shadow-sm">
                {brandSuggestions.map((name) => (
                  <li
                    key={name}
                    onMouseDown={() => selectBrand(name)}
                    className="cursor-pointer px-3 py-1.5 hover:bg-gray-100"
                  >
                    {name}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        <div className="mt-3">
          <label className="mb-1 block text-xs font-medium text-gray-600">
            Item Condition
          </label>
          <select
            value={itemCondition}
            onChange={(e) => setItemCondition(e.target.value)}
            className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          >
            {CONDITIONS.map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Primary Category */}
      <PrimaryCategorySelector
        postId={postId}
        value={primaryCategory}
        onChange={handlePrimaryCategoryChange}
      />

      {/* Gallery Alt-Text Scanner */}
      <GalleryAltTextScanner
        images={galleryImages}
        total={galleryTotal}
        missingAlt={galleryMissingAlt}
        loading={galleryLoading}
        generating={generating}
        onBulkGenerate={bulkGenerate}
        error={galleryError}
      />

      {/* LocalInventory Toggle */}
      <div className="rounded-md border border-gray-200 bg-white p-4">
        <h3 className="mb-2 text-sm font-semibold text-gray-700">
          Local Inventory
        </h3>
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={localInventory}
            onChange={(e) => {
              setLocalInventory(e.target.checked);
              const input = document.querySelector(
                'input[name="_wseo_local_inventory"]',
              );
              if (input) {
                input.value = e.target.checked ? "1" : "";
              } else {
                const hidden = document.createElement("input");
                hidden.type = "hidden";
                hidden.name = "_wseo_local_inventory";
                hidden.value = e.target.checked ? "1" : "";
                const form = container?.closest("form");
                if (form) form.appendChild(hidden);
              }
            }}
            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <span className="text-sm text-gray-700">
            Declare physical stock for Local Inventory
          </span>
        </label>
        {localInventory && localBusinessConfigured === false && (
          <div className="mt-2 rounded border border-yellow-200 bg-yellow-50 p-3 text-xs text-yellow-800">
            LocalBusiness schema is not fully configured. LocalInventory
            requires a complete LocalBusiness profile.
            <a
              href="#/local-seo"
              className="ml-1 font-semibold text-yellow-700 underline"
            >
              Configure Local SEO
            </a>
          </div>
        )}
        {localInventory && localBusinessConfigured === true && (
          <p className="mt-2 text-xs text-gray-500">
            This product will be included in LocalInventory schema when
            WooCommerce stock management is enabled.
          </p>
        )}
      </div>

      {/* FAQ Builder */}
      <FAQBuilder postId={postId} initialFaq={faq} container={container} />
    </div>
  );
}
