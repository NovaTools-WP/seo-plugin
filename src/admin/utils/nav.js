/**
 * This plugin's admin pages render in two contexts:
 *
 *   • Addon mode — the parent "novatools" plugin is active and hosts the
 *     router. Hash routes are prefixed with "seo" (e.g. #/seo, #/seo/sitemaps).
 *   • Standalone mode — this plugin runs its own app (main.jsx) with hash
 *     routes at the root (e.g. #/, #/setup-wizard).
 *
 * react-router-dom is bundled separately per plugin (only React itself is
 * shared), so router hooks such as useNavigate() have no matching context in
 * addon mode and throw. In addon mode navigation must be driven through
 * window.location.hash, matching the pattern already used in addon-entry.jsx.
 */

function isAddonMode() {
  return /^#\/seo(\/|$)/.test(window.location.hash || "");
}

/** Navigate to the setup wizard. */
export function goToSetupWizard() {
  window.location.hash = isAddonMode()
    ? "#/seo/setup-wizard"
    : "#/setup-wizard";
}

/** Navigate back to the dashboard (used by the wizard on finish/skip). */
export function goToDashboard() {
  window.location.hash = isAddonMode() ? "#/seo" : "#/";
}
