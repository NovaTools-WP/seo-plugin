import React, { useState, useEffect } from "react";
import * as api from "../api";
import { goToSetupWizard } from "../utils/nav";

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

      {!data.setup_completed && !data.setup_skipped && (
        <div className="mb-6 flex flex-col gap-3 rounded-lg border border-blue-200 bg-blue-50 p-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-semibold text-blue-900">
              Finish setting up SEO
            </p>
            <p className="mt-0.5 text-sm text-blue-700">
              Run the setup wizard to configure titles, social profiles, and
              sitemaps in a few quick steps.
            </p>
          </div>
          <button
            type="button"
            onClick={goToSetupWizard}
            className="shrink-0 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
          >
            Run setup wizard
          </button>
        </div>
      )}

      {/* Always-available re-run entry point for the wizard. */}
      <div className="mb-6 flex justify-end">
        <button
          type="button"
          onClick={goToSetupWizard}
          className="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline"
        >
          {data.setup_completed || data.setup_skipped
            ? "Re-run setup wizard"
            : "Setup wizard"}
        </button>
      </div>

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
