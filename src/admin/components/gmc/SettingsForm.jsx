import React, { useState, useEffect } from "react";
import * as api from "../../api";

export default function SettingsForm({ onSaved }) {
  const [merchantId, setMerchantId] = useState("");
  const [realtimeSync, setRealtimeSync] = useState(false);
  const [syncSchedule, setSyncSchedule] = useState("disabled");
  const [clientId, setClientId] = useState("");
  const [clientSecret, setClientSecret] = useState("");
  const [secretSet, setSecretSet] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");

  useEffect(() => {
    api.get("/gmc/settings").then((s) => {
      setMerchantId(s.merchant_id || "");
      setRealtimeSync(s.realtime_sync || false);
      setSyncSchedule(s.sync_schedule || "disabled");
      setClientId(s.client_id || "");
      setSecretSet(s.client_secret_set || false);
    });
  }, []);

  async function save() {
    setSaving(true);
    setMessage("");
    try {
      await api.post("/gmc/settings", {
        merchant_id: merchantId,
        realtime_sync: realtimeSync,
        sync_schedule: syncSchedule,
        client_id: clientId,
        ...(clientSecret ? { client_secret: clientSecret } : {}),
      });
      setMessage("Settings saved.");
      setSecretSet(true);
      setClientSecret("");
      if (onSaved) onSaved();
    } catch {
      setMessage("Error saving settings.");
    }
    setSaving(false);
  }

  return (
    <div className="space-y-5">
      <h3 className="text-lg font-medium text-gray-900">Configuration</h3>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Google OAuth Client ID
          </label>
          <input
            type="text"
            value={clientId}
            onChange={(e) => setClientId(e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            placeholder="xxxx.apps.googleusercontent.com"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Google OAuth Client Secret{" "}
            {secretSet && <span className="text-gray-400">(set)</span>}
          </label>
          <input
            type="password"
            value={clientSecret}
            onChange={(e) => setClientSecret(e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            placeholder={
              secretSet ? "Leave blank to keep current" : "Enter client secret"
            }
          />
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700">
          Merchant Center Account ID
        </label>
        <input
          type="text"
          value={merchantId}
          onChange={(e) => setMerchantId(e.target.value.replace(/\D/g, ""))}
          className="mt-1 block w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm"
          placeholder="Numeric account ID"
        />
      </div>

      <div className="flex items-center gap-3">
        <label className="text-sm font-medium text-gray-700">
          Real-time Product Sync
        </label>
        <button
          type="button"
          onClick={() => setRealtimeSync(!realtimeSync)}
          className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
            realtimeSync ? "bg-blue-600" : "bg-gray-200"
          }`}
        >
          <span
            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
              realtimeSync ? "translate-x-6" : "translate-x-1"
            }`}
          />
        </button>
        <span className="text-sm text-gray-500">
          {realtimeSync ? "Enabled" : "Disabled"}
        </span>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700">
          Automatic Sync Schedule
        </label>
        <select
          value={syncSchedule}
          onChange={(e) => setSyncSchedule(e.target.value)}
          className="mt-1 block w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm"
        >
          <option value="disabled">Disabled</option>
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
        </select>
      </div>

      <div className="flex items-center gap-3">
        <button
          onClick={save}
          disabled={saving}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? "Saving..." : "Save Settings"}
        </button>
        {message && (
          <span
            className={`text-sm ${
              message.includes("Error") ? "text-red-600" : "text-green-600"
            }`}
          >
            {message}
          </span>
        )}
      </div>
    </div>
  );
}
