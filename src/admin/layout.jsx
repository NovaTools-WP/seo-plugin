import React from "react";
import { useLocation, useNavigate } from "react-router-dom";

const navItems = [
  { path: "/", label: "Dashboard" },
  { path: "/general-settings", label: "General Settings" },
  { path: "/sitemaps", label: "Sitemaps" },
  { path: "/social-media", label: "Social Media" },
  { path: "/redirects", label: "Redirects" },
  { path: "/local-seo", label: "Local SEO" },
  { path: "/integrations", label: "Integrations" },
  { path: "/woo-seo", label: "WooCommerce SEO" },
  { path: "/tools", label: "Tools" },
  { path: "/geo", label: "GEO" },
];

export default function Layout({ children }) {
  const location = useLocation();
  const navigate = useNavigate();

  return (
    <div className="flex min-h-screen bg-gray-50">
      <aside className="w-60 border-r border-gray-200 bg-white p-4">
        <h1 className="mb-6 text-lg font-semibold text-gray-900">SEO</h1>
        <nav className="space-y-1">
          {navItems.map((item) => (
            <button
              key={item.path}
              onClick={() => navigate(item.path)}
              className={`w-full rounded-md px-3 py-2 text-left text-sm transition-colors ${
                location.pathname === item.path
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
