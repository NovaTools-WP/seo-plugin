import React, { useState, useEffect } from "react";
import { createRoot } from "react-dom/client";
import "./addon.css";
import Dashboard from "./pages/Dashboard";
import GeneralSettings from "./pages/GeneralSettings";
import Sitemaps from "./pages/Sitemaps";
import SocialMedia from "./pages/SocialMedia";
import RedirectManager from "./pages/RedirectManager";
import LocalSEO from "./pages/LocalSEO";
import Integrations from "./pages/Integrations";
import WooCommerceSEO from "./pages/WooCommerceSEO";
import Tools from "./pages/Tools";
import GEO from "./pages/GEO";
import ProductSeoTab from "./components/product-seo-tab/ProductSeoTab";

const navItems = [
  { path: "seo", label: "Dashboard" },
  { path: "seo/general-settings", label: "General Settings" },
  { path: "seo/sitemaps", label: "Sitemaps" },
  { path: "seo/social-media", label: "Social Media" },
  { path: "seo/redirects", label: "Redirects" },
  { path: "seo/local-seo", label: "Local SEO" },
  { path: "seo/integrations", label: "Integrations" },
  { path: "seo/woo-seo", label: "WooCommerce SEO" },
  { path: "seo/tools", label: "Tools" },
  { path: "seo/geo", label: "GEO" },
];

function getHashPath() {
  const hash = window.location.hash || "#/";
  return hash.replace(/^#\/?/, "");
}

function AddonLayout({ children }) {
  const [current, setCurrent] = useState(getHashPath());

  useEffect(() => {
    const onHashChange = () => setCurrent(getHashPath());
    window.addEventListener("hashchange", onHashChange);
    return () => window.removeEventListener("hashchange", onHashChange);
  }, []);

  return (
    <div className="flex min-h-screen bg-gray-50">
      <aside className="w-60 shrink-0 border-r border-gray-200 bg-white p-4">
        <h1 className="mb-6 text-lg font-semibold text-gray-900">SEO</h1>
        <nav className="space-y-1">
          {navItems.map((item) => (
            <button
              key={item.path}
              onClick={() => {
                window.location.hash = "#/" + item.path;
              }}
              className={`w-full rounded-md px-3 py-2 text-left text-sm transition-colors ${
                current === item.path
                  ? "bg-gray-100 font-medium text-gray-900"
                  : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
              }`}
            >
              {item.label}
            </button>
          ))}
        </nav>
      </aside>
      <main className="flex-1 p-6">{children}</main>
    </div>
  );
}

function withLayout(Component) {
  return function WrappedComponent() {
    return (
      <AddonLayout>
        <Component />
      </AddonLayout>
    );
  };
}

window.NovaToolsAddons = window.NovaToolsAddons || {};
window.NovaToolsAddons["novatools-seo"] = {
  Dashboard: withLayout(Dashboard),
  GeneralSettings: withLayout(GeneralSettings),
  Sitemaps: withLayout(Sitemaps),
  SocialMedia: withLayout(SocialMedia),
  RedirectManager: withLayout(RedirectManager),
  LocalSEO: withLayout(LocalSEO),
  Integrations: withLayout(Integrations),
  WooCommerceSEO: withLayout(WooCommerceSEO),
  Tools: withLayout(Tools),
  GEO: withLayout(GEO),
};

// Mount ProductSeoTab into WooCommerce product data panel
const productTabContainer = document.getElementById("wseo-product-schema-tab");
if (productTabContainer) {
  const root = createRoot(productTabContainer);
  root.render(<ProductSeoTab />);
}

console.log("[SEO Addon] Registered REAL components on NovaToolsAddons");
