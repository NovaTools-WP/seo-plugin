import React, { useState, useEffect } from "react";
import * as Tabs from "@radix-ui/react-tabs";
import * as api from "../api";

const ROBOTS_OPTIONS = [
  { value: "index,follow", label: "index, follow" },
  { value: "noindex,follow", label: "noindex, follow" },
  { value: "index,nofollow", label: "index, nofollow" },
  { value: "noindex,nofollow", label: "noindex, nofollow" },
];

export default function GeneralSettings() {
  const [settings, setSettings] = useState({});
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");
  const postTypes = window.novaToolsSEO?.postTypes || [];

  useEffect(() => {
    api.get("/settings").then(setSettings);
  }, []);

  function set(key, value) {
    setSettings((s) => ({ ...s, [key]: value }));
  }

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      await api.post("/settings", settings);
      setMessage("Settings saved.");
    } catch {
      setMessage("Error saving settings.");
    }
    setSaving(false);
  }

  function PostTypeTab({ type }) {
    const pfx = `wseo_general_${type.name}_`;
    return (
      <div className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Title Template
          </label>
          <input
            type="text"
            value={settings[pfx + "title_template"] || ""}
            onChange={(e) => set(pfx + "title_template", e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            placeholder={
              settings.wseo_general_title_template ||
              "%%title%% %%sep%% %%sitename%%"
            }
          />
          <p className="mt-1 text-xs text-gray-400">
            Tokens: %%title%%, %%sitename%%, %%sep%%, %%category%%, %%page%%
          </p>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Description Template
          </label>
          <textarea
            value={settings[pfx + "desc_template"] || ""}
            onChange={(e) => set(pfx + "desc_template", e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            rows={2}
            placeholder={settings.wseo_general_desc_template || ""}
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Robots Default
          </label>
          <select
            value={settings[pfx + "robots_default"] || ""}
            onChange={(e) => set(pfx + "robots_default", e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          >
            <option value="">Use global default</option>
            {ROBOTS_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={settings[pfx + "sitemap_visibility"] !== "0"}
            onChange={(e) =>
              set(pfx + "sitemap_visibility", e.target.checked ? "1" : "0")
            }
            className="h-4 w-4 rounded border-gray-300"
          />
          <label className="text-sm text-gray-700">
            Include in sitemap
          </label>
        </div>
      </div>
    );
  }

  return (
    <div>
      <h2 className="text-2xl font-semibold text-gray-900">General Settings</h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Configure default SEO settings per post type.
      </p>

      <Tabs.Root defaultValue="global">
        <Tabs.List className="mb-4 flex flex-wrap gap-1 border-b border-gray-200 pb-2">
          <Tabs.Trigger
            value="global"
            className="rounded-md px-3 py-1.5 text-sm text-gray-600 transition-colors data-[state=active]:bg-white data-[state=active]:font-medium data-[state=active]:text-gray-900 data-[state=active]:shadow-sm"
          >
            Global Defaults
          </Tabs.Trigger>
          {postTypes.map((t) => (
            <Tabs.Trigger
              key={t.name}
              value={t.name}
              className="rounded-md px-3 py-1.5 text-sm text-gray-600 transition-colors data-[state=active]:bg-white data-[state=active]:font-medium data-[state=active]:text-gray-900 data-[state=active]:shadow-sm"
            >
              {t.label}
            </Tabs.Trigger>
          ))}
        </Tabs.List>

        <Tabs.Content value="global" className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Global Title Template
            </label>
            <input
              type="text"
              value={settings.wseo_general_title_template || ""}
              onChange={(e) =>
                set("wseo_general_title_template", e.target.value)
              }
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder="%%title%% %%sep%% %%sitename%%"
            />
            <p className="mt-1 text-xs text-gray-400">
              Tokens: %%title%%, %%sitename%%, %%sep%%, %%category%%, %%page%%
            </p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Global Description Template
            </label>
            <textarea
              value={settings.wseo_general_desc_template || ""}
              onChange={(e) =>
                set("wseo_general_desc_template", e.target.value)
              }
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              rows={2}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Global Robots Default
            </label>
            <select
              value={settings.wseo_general_robots_default || "index,follow"}
              onChange={(e) =>
                set("wseo_general_robots_default", e.target.value)
              }
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
              {ROBOTS_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              robots.txt Content
            </label>
            <textarea
              value={settings.wseo_robots_txt_content || ""}
              onChange={(e) =>
                set("wseo_robots_txt_content", e.target.value)
              }
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
              rows={6}
              placeholder="User-agent: *&#10;Disallow: /wp-admin/"
            />
          </div>
        </Tabs.Content>

        {postTypes.map((t) => (
          <Tabs.Content key={t.name} value={t.name}>
            <PostTypeTab type={t} />
          </Tabs.Content>
        ))}
      </Tabs.Root>

      <div className="mt-6">
        <button
          onClick={save}
          disabled={saving}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? "Saving..." : "Save All Settings"}
        </button>
        {message && <p className="mt-2 text-sm text-green-600">{message}</p>}
      </div>
    </div>
  );
}
