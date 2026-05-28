import React, { useState, useEffect } from "react";
import * as api from "../api";

export default function SocialMedia() {
  const [ogImage, setOgImage] = useState("");
  const [twitterCard, setTwitterCard] = useState("summary_large_image");
  const [twitterSite, setTwitterSite] = useState("");
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");

  useEffect(() => {
    api.get("/settings").then((s) => {
      setOgImage(s.wseo_social_og_default_image || "");
      setTwitterCard(s.wseo_social_twitter_card_type || "summary_large_image");
      setTwitterSite(s.wseo_social_twitter_site || "");
    });
  }, []);

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      await api.post("/settings", {
        wseo_social_og_default_image: ogImage,
        wseo_social_twitter_card_type: twitterCard,
        wseo_social_twitter_site: twitterSite,
      });
      setMessage("Settings saved.");
    } catch {
      setMessage("Error saving settings.");
    }
    setSaving(false);
  }

  return (
    <div>
      <h2 className="text-2xl font-semibold text-gray-900">
        Social Media Settings
      </h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Configure default OpenGraph and Twitter/X Card settings.
      </p>

      <div className="max-w-xl space-y-5">
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Default OG Image URL
          </label>
          <input
            type="url"
            value={ogImage}
            onChange={(e) => setOgImage(e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            placeholder="https://example.com/default-image.jpg"
          />
          {ogImage && (
            <img
              src={ogImage}
              alt="OG preview"
              className="mt-2 h-20 rounded border border-gray-200 object-cover"
              onError={(e) => (e.target.style.display = "none")}
            />
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            Twitter/X Card Type
          </label>
          <select
            value={twitterCard}
            onChange={(e) => setTwitterCard(e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          >
            <option value="summary_large_image">Summary Large Image</option>
            <option value="summary">Summary</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            Twitter/X Site Handle
          </label>
          <input
            type="text"
            value={twitterSite}
            onChange={(e) => setTwitterSite(e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            placeholder="@yourhandle"
          />
        </div>

        <button
          onClick={save}
          disabled={saving}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? "Saving..." : "Save Changes"}
        </button>

        {message && <p className="text-sm text-green-600">{message}</p>}
      </div>
    </div>
  );
}
