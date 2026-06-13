import React, {
  useState,
  useEffect,
  useRef,
  useCallback,
  useMemo,
} from "react";
import { Upload, X } from "lucide-react";
import * as api from "../../api";
import useSeoCompleteness from "./hooks/useSeoCompleteness";
import SeoCompletenessGauge from "./SeoCompletenessGauge";
import SeoPreview from "./SeoPreview";

const TITLE_MAX = 60;
const DESC_MAX = 160;
const SAVE_DEBOUNCE_MS = 800;

export default function SeoMetaBox() {
  const container = document.getElementById("wseo-react-meta-box");
  const postId = container?.dataset?.postId || "";
  const permalink = container?.dataset?.permalink || "";
  const initialMeta = container?.dataset?.meta
    ? JSON.parse(container.dataset.meta)
    : {};

  const [title, setTitle] = useState(initialMeta._wseo_title || "");
  const [description, setDescription] = useState(
    initialMeta._wseo_description || "",
  );
  const [canonical, setCanonical] = useState(
    initialMeta._wseo_canonical || permalink,
  );
  const [robots, setRobots] = useState(initialMeta._wseo_robots || "");
  const [ogTitle, setOgTitle] = useState(initialMeta._wseo_og_title || "");
  const [ogDesc, setOgDesc] = useState(initialMeta._wseo_og_description || "");
  const [ogImage, setOgImage] = useState(initialMeta._wseo_og_image || "");
  const [twitterCard, setTwitterCard] = useState(
    initialMeta._wseo_twitter_card || "summary_large_image",
  );
  const [twitterTitle, setTwitterTitle] = useState(
    initialMeta._wseo_twitter_title || "",
  );
  const [twitterDesc, setTwitterDesc] = useState(
    initialMeta._wseo_twitter_description || "",
  );
  const [twitterImage, setTwitterImage] = useState(
    initialMeta._wseo_twitter_image || "",
  );
  const [localBusiness, setLocalBusiness] = useState(
    initialMeta._wseo_local_business === "1" ||
      initialMeta._wseo_local_business === true,
  );
  const [saving, setSaving] = useState(false);

  const selectImageMedia = (setter) => {
    if (typeof window.wp === "undefined" || !window.wp.media) {
      alert("WordPress Media Library is not enqueued.");
      return;
    }
    const frame = window.wp.media({
      title: "Select or Upload Social Media Image",
      button: {
        text: "Select Image",
      },
      multiple: false,
      library: {
        type: "image",
      },
    });
    frame.on("select", () => {
      const attachment = frame.state().get("selection").first().toJSON();
      setter(attachment.url);
    });
    frame.open();
  };

  const renderImageField = (label, value, setter, placeholder) => {
    return (
      <div className="space-y-1">
        <label className="block text-xs font-medium text-gray-600">
          {label}
        </label>
        <div className="flex gap-2">
          <input
            type="url"
            value={value}
            onChange={(e) => setter(e.target.value)}
            className="block w-full flex-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
            placeholder={placeholder}
          />
          <button
            type="button"
            onClick={() => selectImageMedia(setter)}
            className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-all whitespace-nowrap cursor-pointer"
          >
            <Upload className="h-3.5 w-3.5 text-gray-500" />
            Select Image
          </button>
        </div>
        {value && (
          <div className="relative mt-2 inline-block rounded-md border border-gray-200 p-1 bg-gray-50 group">
            <img
              src={value}
              alt="Social Preview"
              className="h-14 w-auto rounded object-cover max-w-[200px]"
            />
            <button
              type="button"
              onClick={() => setter("")}
              className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow-sm hover:bg-red-600 transition-all focus:outline-none cursor-pointer"
              title="Remove image"
            >
              <X className="h-3 w-3" />
            </button>
          </div>
        )}
      </div>
    );
  };

  const featuredImage = Boolean(initialMeta._thumbnail_id);

  const seoFormState = useMemo(
    () => ({
      seoTitle: title,
      metaDescription: description,
      ogImage,
      featuredImage,
      ogTitle,
      ogDescription: ogDesc,
      canonical,
      robots,
    }),
    [
      title,
      description,
      ogImage,
      featuredImage,
      ogTitle,
      ogDesc,
      canonical,
      robots,
    ],
  );

  const { checks, percentage, passed, total } =
    useSeoCompleteness(seoFormState);

  const saveTimer = useRef(null);
  const latestFields = useRef({});

  const saveMeta = useCallback(
    async (fields) => {
      if (!postId) return;
      setSaving(true);
      try {
        await api.post(`/post-meta/${postId}`, fields);
      } catch {
        // silent fail — will retry on next change
      }
      setSaving(false);
    },
    [postId],
  );

  // Debounced save: collect latest field values and save after user stops typing
  useEffect(() => {
    latestFields.current = {
      _wseo_title: title,
      _wseo_description: description,
      _wseo_canonical: canonical,
      _wseo_robots: robots,
      _wseo_og_title: ogTitle,
      _wseo_og_description: ogDesc,
      _wseo_og_image: ogImage,
      _wseo_twitter_card: twitterCard,
      _wseo_twitter_title: twitterTitle,
      _wseo_twitter_description: twitterDesc,
      _wseo_twitter_image: twitterImage,
      _wseo_local_business: localBusiness ? "1" : "",
    };

    if (saveTimer.current) clearTimeout(saveTimer.current);
    saveTimer.current = setTimeout(() => {
      saveMeta(latestFields.current);
    }, SAVE_DEBOUNCE_MS);

    return () => {
      if (saveTimer.current) clearTimeout(saveTimer.current);
    };
  }, [
    title,
    description,
    canonical,
    robots,
    ogTitle,
    ogDesc,
    ogImage,
    twitterCard,
    twitterTitle,
    twitterDesc,
    twitterImage,
    localBusiness,
    saveMeta,
  ]);

  // Also sync to hidden inputs for classic editor fallback
  useEffect(() => {
    const fields = latestFields.current;
    const parent = container?.parentElement;
    if (!parent) return;

    Object.entries(fields).forEach(([name, value]) => {
      const input = parent.querySelector(`input[name="${name}"]`);
      if (input) {
        input.value = value;
      } else {
        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = name;
        hidden.value = value;
        parent.appendChild(hidden);
      }
    });
  }, [
    title,
    description,
    canonical,
    robots,
    ogTitle,
    ogDesc,
    ogImage,
    twitterCard,
    twitterTitle,
    twitterDesc,
    twitterImage,
    localBusiness,
  ]);

  return (
    <div className="space-y-4">
      <SeoCompletenessGauge
        checks={checks}
        percentage={percentage}
        passed={passed}
        total={total}
      />

      <SeoPreview
        title={title}
        description={description}
        url={permalink}
        ogTitle={ogTitle}
        ogDesc={ogDesc}
        ogImage={ogImage}
        twitterCard={twitterCard}
        twitterTitle={twitterTitle}
        twitterDesc={twitterDesc}
        twitterImage={twitterImage}
        initialMeta={initialMeta}
      />

      <div>
        <label className="block text-sm font-medium text-gray-700">
          SEO Title{" "}
          <span
            className={`text-xs ${
              title.length > TITLE_MAX ? "text-red-500" : "text-gray-400"
            }`}
          >
            {title.length}/{TITLE_MAX}
          </span>
        </label>
        <input
          type="text"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          placeholder="Enter SEO title..."
        />
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700">
          Meta Description{" "}
          <span
            className={`text-xs ${
              description.length > DESC_MAX ? "text-red-500" : "text-gray-400"
            }`}
          >
            {description.length}/{DESC_MAX}
          </span>
        </label>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          rows={3}
          placeholder="Enter meta description..."
        />
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700">
          Canonical URL
        </label>
        <input
          type="url"
          value={canonical}
          onChange={(e) => setCanonical(e.target.value)}
          className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          placeholder={permalink}
        />
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700">
          Robots
        </label>
        <select
          value={robots}
          onChange={(e) => setRobots(e.target.value)}
          className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
        >
          <option value="">Default</option>
          <option value="index,follow">index, follow</option>
          <option value="noindex,follow">noindex, follow</option>
          <option value="index,nofollow">index, nofollow</option>
          <option value="noindex,nofollow">noindex, nofollow</option>
        </select>
      </div>

      <details className="rounded-md border border-gray-200 p-3">
        <summary className="cursor-pointer text-sm font-medium text-gray-700">
          OpenGraph & Twitter/X
        </summary>
        <div className="mt-3 space-y-3">
          <div>
            <label className="block text-xs font-medium text-gray-600">
              OG Title
            </label>
            <input
              type="text"
              value={ogTitle}
              onChange={(e) => setOgTitle(e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              placeholder="Falls back to SEO title"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600">
              OG Description
            </label>
            <input
              type="text"
              value={ogDesc}
              onChange={(e) => setOgDesc(e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              placeholder="Falls back to meta description"
            />
          </div>
          {renderImageField(
            "OG Image URL",
            ogImage,
            setOgImage,
            "Falls back to featured image",
          )}
          <div>
            <label className="block text-xs font-medium text-gray-600">
              Twitter Card Type
            </label>
            <select
              value={twitterCard}
              onChange={(e) => setTwitterCard(e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
            >
              <option value="summary_large_image">Summary Large Image</option>
              <option value="summary">Summary</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600">
              Twitter Title
            </label>
            <input
              type="text"
              value={twitterTitle}
              onChange={(e) => setTwitterTitle(e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              placeholder="Falls back to OG title"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600">
              Twitter Description
            </label>
            <input
              type="text"
              value={twitterDesc}
              onChange={(e) => setTwitterDesc(e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              placeholder="Falls back to OG description"
            />
          </div>
          {renderImageField(
            "Twitter Image URL",
            twitterImage,
            setTwitterImage,
            "Falls back to OG image",
          )}
        </div>
      </details>

      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          checked={localBusiness}
          onChange={(e) => setLocalBusiness(e.target.checked)}
          className="h-4 w-4 rounded border-gray-300"
        />
        <label className="text-sm text-gray-700">
          Output Local Business Schema on this page
        </label>
      </div>

      {saving && <p className="text-xs text-gray-400">Saving...</p>}
    </div>
  );
}
