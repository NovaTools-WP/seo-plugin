import Dashboard from "./pages/Dashboard";
import GeneralSettings from "./pages/GeneralSettings";
import Sitemaps from "./pages/Sitemaps";
import SocialMedia from "./pages/SocialMedia";
import RedirectManager from "./pages/RedirectManager";
import Tools from "./pages/Tools";

window.NovaToolsAddons = window.NovaToolsAddons || {};
window.NovaToolsAddons["novatools-seo"] = {
  Dashboard,
  GeneralSettings,
  Sitemaps,
  SocialMedia,
  RedirectManager,
  Tools,
};

console.log("[SEO Addon] Registered REAL components on NovaToolsAddons");
