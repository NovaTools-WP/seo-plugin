import React, { useState, useEffect, useCallback } from "react";
import * as api from "../api";

export default function SocialMedia() {
  const [ogImage, setOgImage] = useState("");
  const [twitterCard, setTwitterCard] = useState("summary_large_image");
  const [twitterSite, setTwitterSite] = useState("");
  const [pinterestRichPins, setPinterestRichPins] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");

  useEffect(() => {
    api.get("/settings").then((s) => {
      setOgImage(s.wseo_social_og_default_image || "");
      setTwitterCard(s.wseo_social_twitter_card_type || "summary_large_image");
      setTwitterSite(s.wseo_social_twitter_site || "");
      setPinterestRichPins(s.wseo_social_pinterest_rich_pins !== "0");
    });
  }, []);

  const togglePinterest = useCallback(
    () => setPinterestRichPins((prev) => !prev),
    [],
  );

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      await api.post("/settings", {
        wseo_social_og_default_image: ogImage,
        wseo_social_twitter_card_type: twitterCard,
        wseo_social_twitter_site: twitterSite,
        wseo_social_pinterest_rich_pins: pinterestRichPins ? "1" : "0",
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

        <div className="flex items-center justify-between rounded-md border border-gray-200 px-4 py-3">
          <div>
            <label className="text-sm font-medium text-gray-700">
              Enable Pinterest Rich Pins for Products
            </label>
            <p className="text-xs text-gray-500">
              Output product price, availability, and SKU tags for Pinterest
              Rich Pins on WooCommerce product pages.
            </p>
          </div>
          <button
            type="button"
            role="switch"
            aria-checked={pinterestRichPins}
            onClick={togglePinterest}
            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
              pinterestRichPins ? "bg-blue-600" : "bg-gray-200"
            }`}
          >
            <span
              className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                pinterestRichPins ? "translate-x-5" : "translate-x-0"
              }`}
            />
          </button>
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
