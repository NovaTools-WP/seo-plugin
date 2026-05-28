import React, { useState, useEffect } from "react";

const TITLE_MAX = 60;
const DESC_MAX = 160;

export default function SeoMetaBox() {
  const container = document.getElementById("novatools-seo-meta-box");
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
  const [ogDesc, setOgDesc] = useState(
    initialMeta._wseo_og_description || "",
  );
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

  useEffect(() => {
    const fields = {
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
  ]);

  return (
    <div className="space-y-4">
      <SnippetPreview
        title={title || "Post Title"}
        description={description || "Meta description will appear here..."}
        url={permalink}
      />

      <div>
        <label className="block text-sm font-medium text-gray-700">
          SEO Title{" "}
          <span
            className={`text-xs ${title.length > TITLE_MAX ? "text-red-500" : "text-gray-400"}`}
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
            className={`text-xs ${description.length > DESC_MAX ? "text-red-500" : "text-gray-400"}`}
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
          <div>
            <label className="block text-xs font-medium text-gray-600">
              OG Image URL
            </label>
            <input
              type="url"
              value={ogImage}
              onChange={(e) => setOgImage(e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              placeholder="Falls back to featured image"
            />
          </div>
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
          <div>
            <label className="block text-xs font-medium text-gray-600">
              Twitter Image URL
            </label>
            <input
              type="url"
              value={twitterImage}
              onChange={(e) => setTwitterImage(e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
              placeholder="Falls back to OG image"
            />
          </div>
        </div>
      </details>
    </div>
  );
}

function SnippetPreview({ title, description, url }) {
  return (
    <div className="rounded-md border border-gray-200 bg-white p-4">
      <p className="text-sm text-gray-800">{url}</p>
      <p className="mt-0.5 text-lg font-medium text-blue-700 hover:underline">
        {title}
      </p>
      <p className="mt-0.5 text-sm text-gray-600 line-clamp-2">
        {description}
      </p>
    </div>
  );
}
