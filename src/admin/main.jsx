import React from "react";
import { createRoot } from "react-dom/client";
import { HashRouter, Routes, Route, Navigate } from "react-router-dom";
import Layout from "./layout";
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
import SeoMetaBox from "./components/seo-meta-box/SeoMetaBox";
import ProductSeoTab from "./components/product-seo-tab/ProductSeoTab";
import "./index.css";

const container = document.getElementById("novatools-seo-app");
if (container) {
  const root = createRoot(container);
  root.render(
    <HashRouter>
      <Layout>
        <Routes>
          <Route path="/" element={<Dashboard />} />
          <Route path="/general-settings" element={<GeneralSettings />} />
          <Route path="/sitemaps" element={<Sitemaps />} />
          <Route path="/social-media" element={<SocialMedia />} />
          <Route path="/redirects" element={<RedirectManager />} />
          <Route path="/local-seo" element={<LocalSEO />} />
          <Route path="/integrations" element={<Integrations />} />
          <Route path="/woo-seo" element={<WooCommerceSEO />} />
          <Route path="/tools" element={<Tools />} />
          <Route path="/geo" element={<GEO />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Layout>
    </HashRouter>,
  );
}

const metaBoxContainer = document.getElementById("wseo-react-meta-box");
if (metaBoxContainer) {
  const root = createRoot(metaBoxContainer);
  root.render(<SeoMetaBox />);
}

const productTabContainer = document.getElementById("wseo-product-schema-tab");
if (productTabContainer) {
  const root = createRoot(productTabContainer);
  root.render(<ProductSeoTab />);
}
