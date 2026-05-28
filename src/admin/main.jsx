import React from "react";
import { createRoot } from "react-dom/client";
import { HashRouter, Routes, Route, Navigate } from "react-router-dom";
import Layout from "./layout";
import Dashboard from "./pages/Dashboard";
import GeneralSettings from "./pages/GeneralSettings";
import Sitemaps from "./pages/Sitemaps";
import SocialMedia from "./pages/SocialMedia";
import RedirectManager from "./pages/RedirectManager";
import Tools from "./pages/Tools";
import SeoMetaBox from "./components/seo-meta-box/SeoMetaBox";
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
          <Route path="/tools" element={<Tools />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Layout>
    </HashRouter>,
  );
}

const metaBoxContainer = document.getElementById("novatools-seo-meta-box");
if (metaBoxContainer) {
  const root = createRoot(metaBoxContainer);
  root.render(<SeoMetaBox />);
}
