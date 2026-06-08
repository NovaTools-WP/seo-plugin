import React, { useState, useEffect } from "react";
import IndexNowSettings from "../components/geo/IndexNowSettings";
import AiBotRules from "../components/seo/AiBotRules";
import * as api from "../api";

export default function GEO() {
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get("/settings").then((s) => {
      setSettings(s);
      setLoading(false);
    });
  }, []);

  function setSetting(key, value) {
    setSettings((s) => ({ ...s, [key]: value }));
  }

  if (loading) {
    return (
      <div>
        <h2 className="text-2xl font-semibold text-gray-900">GEO</h2>
        <p className="mt-2 text-sm text-gray-500">Loading...</p>
      </div>
    );
  }

  return (
    <div className="max-w-3xl">
      <h2 className="text-2xl font-semibold text-gray-900">
        Generative Engine Optimization
      </h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Optimize your site for AI-powered search engines and knowledge panels.
      </p>

      <div className="space-y-10">
        <IndexNowSettings settings={settings} setSetting={setSetting} />
        <hr className="border-gray-200" />
        <AiBotRules settings={settings} setSetting={setSetting} />
      </div>
    </div>
  );
}
