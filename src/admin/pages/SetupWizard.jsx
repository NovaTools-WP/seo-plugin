import React, { useEffect, useState } from "react";
import * as api from "../api";
import { goToDashboard } from "../utils/nav";

// Five steps per the plan: Site info, Title template, Social, Local SEO, Sitemap.
const STEPS = [
  { key: "site", label: "Site Info" },
  { key: "title", label: "Title Template" },
  { key: "social", label: "Social" },
  { key: "local", label: "Local SEO" },
  { key: "sitemap", label: "Sitemap" },
];

const TITLE_TOKENS = "%%title%% %%sep%% %%sitename%%";

const inputClass =
  "mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm";

export default function SetupWizard() {
  const [settings, setSettings] = useState(null); // full fetched settings
  const [step, setStep] = useState(0);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  // Site identity (core blogname/blogdescription — feeds the
  // %%sitename%% / %%sitedesc%% title tokens).
  const [site, setSite] = useState({ siteName: "", siteTagline: "" });

  // Editable working copy keyed by full option name.
  const [form, setForm] = useState({
    wseo_general_title_template: "",
    wseo_general_desc_template: "",
    wseo_social_twitter_site: "",
    wseo_social_og_default_image: "",
    wseo_social_twitter_card_type: "summary_large_image",
    wseo_sitemap_enabled: "1",
    wseo_sitemap_ping_enabled: "1",
  });
  // Local SEO is an array option — merged into the existing one on save.
  const [local, setLocal] = useState({
    business_name: "",
    business_address: "",
    business_phone: "",
  });

  useEffect(() => {
    api
      .get("/settings")
      .then((s) => {
        setSettings(s);
        setSite({
          siteName: window.novaToolsSEO?.siteName ?? "",
          siteTagline: window.novaToolsSEO?.siteTagline ?? "",
        });
        setForm((f) => ({
          ...f,
          wseo_general_title_template: s.wseo_general_title_template ?? "",
          wseo_general_desc_template: s.wseo_general_desc_template ?? "",
          wseo_social_twitter_site: s.wseo_social_twitter_site ?? "",
          wseo_social_og_default_image: s.wseo_social_og_default_image ?? "",
          wseo_social_twitter_card_type:
            s.wseo_social_twitter_card_type ?? "summary_large_image",
          wseo_sitemap_enabled: s.wseo_sitemap_enabled ?? "1",
          wseo_sitemap_ping_enabled: s.wseo_sitemap_ping_enabled ?? "1",
        }));
        const existing = s.wseo_local_seo || {};
        setLocal({
          business_name: existing.business_name ?? "",
          business_address: existing.business_address ?? "",
          business_phone: existing.business_phone ?? "",
        });
      })
      .catch(() => setError("Could not load settings."));
  }, []);

  if (settings === null) {
    return (
      <div>
        <h2 className="text-2xl font-semibold text-gray-900">
          SEO Setup Wizard
        </h2>
        <p className="mt-2 text-sm text-gray-500">{error || "Loading…"}</p>
      </div>
    );
  }

  const setField = (key, value) => setForm((f) => ({ ...f, [key]: value }));

  /**
   * Save the wizard fields. Merges onto the *full* fetched settings so other
   * options (e.g. unrelated local_seo fields) are preserved, not wiped.
   */
  async function finish() {
    setSaving(true);
    setError("");
    try {
      const merged = { ...settings, ...form };

      // Core site identity feeds the %%sitename%% / %%sitedesc%% tokens.
      merged.blogname = site.siteName;
      merged.blogdescription = site.siteTagline;

      // Local SEO: overlay the wizard fields onto the existing array option.
      merged.wseo_local_seo = {
        ...(settings.wseo_local_seo || {}),
        business_name: local.business_name,
        business_address: local.business_address,
        business_phone: local.business_phone,
      };

      merged.wseo_setup_completed = "1";
      // Completing the wizard cancels any previous skip.
      merged.wseo_setup_skipped = "";

      await api.post("/settings", merged);
      // Rebuild the sitemap to reflect the new configuration.
      api.post("/sitemap/rebuild").catch(() => {});
      goToDashboard();
    } catch {
      setError("Could not save settings. Please try again.");
      setSaving(false);
    }
  }

  async function skip() {
    setSaving(true);
    setError("");
    try {
      await api.post("/settings", { wseo_setup_skipped: "1" });
      goToDashboard();
    } catch {
      setError("Could not skip setup. Please try again.");
      setSaving(false);
    }
  }

  const isFirst = step === 0;
  const isLast = step === STEPS.length - 1;

  return (
    <div className="mx-auto max-w-2xl">
      <h2 className="text-2xl font-semibold text-gray-900">SEO Setup Wizard</h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Get your site's SEO configured in a few quick steps.
      </p>

      {/* Stepper — only current/previous steps are clickable (no jumping ahead). */}
      <ol className="mb-6 flex flex-wrap items-center gap-2">
        {STEPS.map((s, i) => {
          const reachable = i <= step;
          return (
            <li key={s.key} className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => reachable && setStep(i)}
                disabled={!reachable}
                className={`flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                  i === step
                    ? "bg-blue-600 text-white"
                    : i < step
                    ? "bg-green-100 text-green-800"
                    : "bg-gray-100 text-gray-400"
                } ${reachable ? "cursor-pointer" : "cursor-not-allowed"}`}
              >
                <span
                  className={`flex h-5 w-5 items-center justify-center rounded-full text-[11px] ${
                    i === step
                      ? "bg-white/20"
                      : i < step
                      ? "bg-green-200 text-green-900"
                      : "bg-gray-200 text-gray-500"
                  }`}
                >
                  {i + 1}
                </span>
                {s.label}
              </button>
              {i < STEPS.length - 1 && (
                <span className="h-px w-4 bg-gray-300" />
              )}
            </li>
          );
        })}
      </ol>

      <div className="rounded-lg border border-gray-200 bg-white p-6">
        {step === 0 && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Site Name
              </label>
              <input
                type="text"
                value={site.siteName}
                onChange={(e) =>
                  setSite((s) => ({ ...s, siteName: e.target.value }))
                }
                className={inputClass}
                placeholder="Your Site Name"
              />
              <p className="mt-1 text-xs text-gray-400">
                Used by the %%sitename%% token in your title templates.
              </p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Site Tagline
              </label>
              <input
                type="text"
                value={site.siteTagline}
                onChange={(e) =>
                  setSite((s) => ({ ...s, siteTagline: e.target.value }))
                }
                className={inputClass}
                placeholder="A short description of your site"
              />
              <p className="mt-1 text-xs text-gray-400">
                Used by the %%sitedesc%% token in your title templates.
              </p>
            </div>
          </div>
        )}

        {step === 1 && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Title Template
              </label>
              <input
                type="text"
                value={form.wseo_general_title_template}
                onChange={(e) =>
                  setField("wseo_general_title_template", e.target.value)
                }
                className={inputClass}
                placeholder={TITLE_TOKENS}
              />
              <p className="mt-1 text-xs text-gray-400">
                Tokens: %%title%%, %%sitename%%, %%sitedesc%%, %%sep%%,
                %%category%%, %%page%%
              </p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Meta Description Template
              </label>
              <textarea
                value={form.wseo_general_desc_template}
                onChange={(e) =>
                  setField("wseo_general_desc_template", e.target.value)
                }
                className={inputClass}
                rows={2}
                placeholder="Optional global description template"
              />
            </div>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Twitter / X Handle
              </label>
              <input
                type="text"
                value={form.wseo_social_twitter_site}
                onChange={(e) =>
                  setField("wseo_social_twitter_site", e.target.value)
                }
                className={inputClass}
                placeholder="@yoursite"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Default Social Image URL
              </label>
              <input
                type="url"
                value={form.wseo_social_og_default_image}
                onChange={(e) =>
                  setField("wseo_social_og_default_image", e.target.value)
                }
                className={inputClass}
                placeholder="https://example.com/og-default.jpg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Default Twitter Card Type
              </label>
              <select
                value={form.wseo_social_twitter_card_type}
                onChange={(e) =>
                  setField("wseo_social_twitter_card_type", e.target.value)
                }
                className={inputClass}
              >
                <option value="summary_large_image">Summary Large Image</option>
                <option value="summary">Summary</option>
              </select>
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="space-y-4">
            <p className="text-sm text-gray-500">
              Optional — only if this site represents a physical business.
            </p>
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Business Name
              </label>
              <input
                type="text"
                value={local.business_name}
                onChange={(e) =>
                  setLocal((l) => ({ ...l, business_name: e.target.value }))
                }
                className={inputClass}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Business Address
              </label>
              <input
                type="text"
                value={local.business_address}
                onChange={(e) =>
                  setLocal((l) => ({ ...l, business_address: e.target.value }))
                }
                className={inputClass}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Business Phone
              </label>
              <input
                type="text"
                value={local.business_phone}
                onChange={(e) =>
                  setLocal((l) => ({ ...l, business_phone: e.target.value }))
                }
                className={inputClass}
              />
            </div>
          </div>
        )}

        {step === 4 && (
          <div className="space-y-4">
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={form.wseo_sitemap_enabled === "1"}
                onChange={(e) =>
                  setField("wseo_sitemap_enabled", e.target.checked ? "1" : "0")
                }
                className="h-4 w-4 rounded border-gray-300"
              />
              <span className="text-sm text-gray-700">Enable XML sitemap</span>
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={form.wseo_sitemap_ping_enabled === "1"}
                onChange={(e) =>
                  setField(
                    "wseo_sitemap_ping_enabled",
                    e.target.checked ? "1" : "0",
                  )
                }
                className="h-4 w-4 rounded border-gray-300"
              />
              <span className="text-sm text-gray-700">
                Ping search engines when the sitemap updates
              </span>
            </label>
          </div>
        )}
      </div>

      {error && <p className="mt-3 text-sm text-red-500">{error}</p>}

      {/* Navigation */}
      <div className="mt-6 flex items-center justify-between">
        <button
          type="button"
          onClick={skip}
          disabled={saving}
          className="text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50"
        >
          Skip setup
        </button>
        <div className="flex gap-2">
          {!isFirst && (
            <button
              type="button"
              onClick={() => setStep((s) => s - 1)}
              disabled={saving}
              className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
              Back
            </button>
          )}
          {isLast ? (
            <button
              type="button"
              onClick={finish}
              disabled={saving}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {saving ? "Saving…" : "Finish"}
            </button>
          ) : (
            <button
              type="button"
              onClick={() => setStep((s) => s + 1)}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              Next
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
