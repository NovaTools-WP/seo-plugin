import React, { useState, useEffect } from "react";
import * as api from "../../api";

const PRESET_BOTS = [
  { user_agent: "GPTBot", label: "GPTBot (OpenAI)" },
  { user_agent: "ChatGPT-User", label: "ChatGPT-User (OpenAI)" },
  { user_agent: "ClaudeBot", label: "ClaudeBot (Anthropic)" },
  { user_agent: "PerplexityBot", label: "PerplexityBot" },
  { user_agent: "Bytespider", label: "Bytespider (ByteDance)" },
  { user_agent: "Applebot-Extended", label: "Applebot-Extended" },
];

export default function AiBotRules({ settings, setSetting }) {
  const [rules, setRules] = useState({
    preset_bots: [],
    custom_bots: [],
  });
  const [newCustomBot, setNewCustomBot] = useState("");
  const [message, setMessage] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const data = settings.wseo_ai_bot_rules;
    if (data && typeof data === "object") {
      setRules({
        preset_bots: Array.isArray(data.preset_bots) ? data.preset_bots : [],
        custom_bots: Array.isArray(data.custom_bots) ? data.custom_bots : [],
      });
    }
  }, [settings.wseo_ai_bot_rules]);

  function getPresetBlocked(userAgent) {
    const bot = rules.preset_bots.find((b) => b.user_agent === userAgent);
    return bot ? !!bot.blocked : false;
  }

  function togglePreset(userAgent) {
    setRules((prev) => {
      const existing = prev.preset_bots.find((b) => b.user_agent === userAgent);
      if (existing) {
        return {
          ...prev,
          preset_bots: prev.preset_bots.map((b) =>
            b.user_agent === userAgent ? { ...b, blocked: !b.blocked } : b,
          ),
        };
      }
      return {
        ...prev,
        preset_bots: [...prev.preset_bots, { user_agent: userAgent, blocked: true, path_rules: [] }],
      };
    });
  }

  function setPresetPathRule(userAgent, pathRules) {
    setRules((prev) => ({
      ...prev,
      preset_bots: prev.preset_bots.map((b) =>
        b.user_agent === userAgent ? { ...b, path_rules: pathRules } : b,
      ),
    }));
  }

  function addPresetPathRule(userAgent) {
    const bot = rules.preset_bots.find((b) => b.user_agent === userAgent);
    const current = bot?.path_rules || [];
    setPresetPathRule(userAgent, [...current, { path: "", allow: false }]);
  }

  function updatePresetPathRule(userAgent, ruleIndex, field, value) {
    const bot = rules.preset_bots.find((b) => b.user_agent === userAgent);
    const current = bot?.path_rules || [];
    const updated = [...current];
    updated[ruleIndex] = { ...updated[ruleIndex], [field]: value };
    setPresetPathRule(userAgent, updated);
  }

  function removePresetPathRule(userAgent, ruleIndex) {
    const bot = rules.preset_bots.find((b) => b.user_agent === userAgent);
    const current = bot?.path_rules || [];
    setPresetPathRule(userAgent, current.filter((_, i) => i !== ruleIndex));
  }

  function addCustomBot() {
    const name = newCustomBot.trim();
    if (!name) return;
    if (rules.custom_bots.find((b) => b.user_agent === name)) return;
    setRules((prev) => ({
      ...prev,
      custom_bots: [...prev.custom_bots, { user_agent: name, blocked: true, path_rules: [] }],
    }));
    setNewCustomBot("");
  }

  function removeCustomBot(index) {
    setRules((prev) => ({
      ...prev,
      custom_bots: prev.custom_bots.filter((_, i) => i !== index),
    }));
  }

  function toggleCustomBot(index) {
    setRules((prev) => ({
      ...prev,
      custom_bots: prev.custom_bots.map((b, i) =>
        i === index ? { ...b, blocked: !b.blocked } : b,
      ),
    }));
  }

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      await api.post("/settings", { wseo_ai_bot_rules: rules });
      setSetting("wseo_ai_bot_rules", rules);
      setMessage("AI bot rules saved.");
    } catch {
      setMessage("Error saving settings.");
    }
    setSaving(false);
  }

  function renderBotRow(bot, onToggle, onRemove, onAddPath, onUpdatePath, onRemovePath) {
    return (
      <div
        key={bot.user_agent}
        className="rounded-md border border-gray-200 p-3"
      >
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <label className="relative inline-flex cursor-pointer items-center">
              <input
                type="checkbox"
                checked={!!bot.blocked}
                onChange={onToggle}
                className="peer sr-only"
              />
              <div className="peer h-5 w-9 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-red-500 peer-checked:after:translate-x-full peer-checked:after:border-white" />
            </label>
            <span className="text-sm font-medium text-gray-700">
              {bot.user_agent}
            </span>
          </div>
          <div className="flex items-center gap-2">
            {onRemove && (
              <button
                type="button"
                onClick={onRemove}
                className="text-sm text-red-500 hover:text-red-700"
              >
                Remove
              </button>
            )}
          </div>
        </div>

        {(bot.path_rules || []).length > 0 && (
          <div className="mt-2 ml-12 space-y-1">
            {(bot.path_rules || []).map((rule, ri) => (
              <div key={ri} className="flex items-center gap-2">
                <select
                  value={rule.allow ? "allow" : "disallow"}
                  onChange={(e) => onUpdatePath(ri, "allow", e.target.value === "allow")}
                  className="rounded border border-gray-300 px-1.5 py-0.5 text-xs"
                >
                  <option value="disallow">Disallow</option>
                  <option value="allow">Allow</option>
                </select>
                <input
                  type="text"
                  value={rule.path}
                  onChange={(e) => onUpdatePath(ri, "path", e.target.value)}
                  placeholder="/path/"
                  className="rounded border border-gray-300 px-2 py-0.5 text-xs"
                  style={{ width: "200px" }}
                />
                <button
                  type="button"
                  onClick={() => onRemovePath(ri)}
                  className="text-xs text-red-400 hover:text-red-600"
                >
                  ×
                </button>
              </div>
            ))}
          </div>
        )}

        {bot.blocked && (
          <button
            type="button"
            onClick={onAddPath}
            className="ml-12 mt-1 text-xs text-blue-600 hover:text-blue-800"
          >
            + Add path rule
          </button>
        )}
      </div>
    );
  }

  return (
    <div>
      <h3 className="text-lg font-medium text-gray-800">AI Bot Rules</h3>
      <p className="mt-1 text-sm text-gray-500">
        Control which AI crawlers can access your site via robots.txt.
      </p>

      <div className="mt-4 space-y-2">
        {PRESET_BOTS.map((preset) =>
          renderBotRow(
            {
              user_agent: preset.user_agent,
              blocked: getPresetBlocked(preset.user_agent),
              path_rules:
                rules.preset_bots.find((b) => b.user_agent === preset.user_agent)
                  ?.path_rules || [],
            },
            () => togglePreset(preset.user_agent),
            null,
            () => addPresetPathRule(preset.user_agent),
            (ri, field, value) =>
              updatePresetPathRule(preset.user_agent, ri, field, value),
            (ri) => removePresetPathRule(preset.user_agent, ri),
          ),
        )}
      </div>

      {/* Custom bots */}
      <div className="mt-6">
        <h4 className="text-sm font-medium text-gray-700">Custom Bots</h4>
        <div className="mt-2 space-y-2">
          {rules.custom_bots.map((bot, i) =>
            renderBotRow(
              bot,
              () => toggleCustomBot(i),
              () => removeCustomBot(i),
              () => {
                setRules((prev) => ({
                  ...prev,
                  custom_bots: prev.custom_bots.map((b, bi) =>
                    bi === i
                      ? { ...b, path_rules: [...(b.path_rules || []), { path: "", allow: false }] }
                      : b,
                  ),
                }));
              },
              (ri, field, value) => {
                setRules((prev) => ({
                  ...prev,
                  custom_bots: prev.custom_bots.map((b, bi) => {
                    if (bi !== i) return b;
                    const updated = [...(b.path_rules || [])];
                    updated[ri] = { ...updated[ri], [field]: value };
                    return { ...b, path_rules: updated };
                  }),
                }));
              },
              (ri) => {
                setRules((prev) => ({
                  ...prev,
                  custom_bots: prev.custom_bots.map((b, bi) =>
                    bi === i
                      ? { ...b, path_rules: (b.path_rules || []).filter((_, pi) => pi !== ri) }
                      : b,
                  ),
                }));
              },
            ),
          )}
        </div>

        <div className="mt-3 flex gap-2">
          <input
            type="text"
            value={newCustomBot}
            onChange={(e) => setNewCustomBot(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), addCustomBot())}
            placeholder="User-agent name"
            className="rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
          <button
            type="button"
            onClick={addCustomBot}
            className="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
          >
            Add Bot
          </button>
        </div>
      </div>

      <div className="mt-4">
        <button
          onClick={save}
          disabled={saving}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? "Saving..." : "Save AI Bot Rules"}
        </button>
        {message && (
          <p
            className={`mt-2 text-sm ${
              message.includes("Error") ? "text-red-600" : "text-green-600"
            }`}
          >
            {message}
          </p>
        )}
      </div>
    </div>
  );
}
