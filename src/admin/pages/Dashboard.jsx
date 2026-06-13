import React, { useState, useEffect } from "react";
import * as api from "../api";

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api
      .get("/dashboard")
      .then(setData)
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div>
        <h2 className="text-2xl font-semibold text-gray-900">SEO Dashboard</h2>
        <p className="mt-2 text-sm text-gray-500">Loading...</p>
      </div>
    );
  }

  if (!data) {
    return (
      <div>
        <h2 className="text-2xl font-semibold text-gray-900">SEO Dashboard</h2>
        <p className="mt-2 text-sm text-red-500">
          Error loading dashboard data.
        </p>
      </div>
    );
  }

  function StatusBadge({ status }) {
    const colors = {
      valid: "bg-green-100 text-green-800",
      invalid: "bg-red-100 text-red-800",
      empty: "bg-gray-100 text-gray-600",
      enabled: "bg-green-100 text-green-800",
      disabled: "bg-red-100 text-red-800",
    };
    const labels = {
      valid: "Valid",
      invalid: "Invalid",
      empty: "Not Set",
      enabled: "Enabled",
      disabled: "Disabled",
    };
    return (
      <span
        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
          colors[status] || "bg-gray-100 text-gray-600"
        }`}
      >
        {labels[status] || status}
      </span>
    );
  }

  return (
    <div>
      <h2 className="text-2xl font-semibold text-gray-900">SEO Dashboard</h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Overview of your site's SEO configuration and status.
      </p>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <p className="text-sm font-medium text-gray-500">Sitemap</p>
          <div className="mt-2">
            <StatusBadge
              status={data.sitemap_enabled ? "enabled" : "disabled"}
            />
          </div>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <p className="text-sm font-medium text-gray-500">Active Redirects</p>
          <p className="mt-2 text-3xl font-semibold text-gray-900">
            {data.redirect_count}
          </p>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <p className="text-sm font-medium text-gray-500">License Status</p>
          <div className="mt-2">
            <StatusBadge status={data.license_status} />
          </div>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <p className="text-sm font-medium text-gray-500">
            Managed Post Types
          </p>
          <p className="mt-2 text-sm text-gray-900">
            {data.post_types?.length > 0 ? data.post_types.join(", ") : "None"}
          </p>
        </div>
      </div>
    </div>
  );
}
