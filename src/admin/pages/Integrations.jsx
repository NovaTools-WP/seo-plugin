import React from "react";
import MerchantCenterPanel from "../components/gmc/MerchantCenterPanel";

export default function Integrations() {
  return (
    <div>
      <h2 className="text-2xl font-semibold text-gray-900">Integrations</h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Connect third-party services to extend your SEO capabilities.
      </p>
      <div className="max-w-3xl">
        <MerchantCenterPanel />
      </div>
    </div>
  );
}
