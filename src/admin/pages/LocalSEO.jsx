import React, { useState, useEffect, useCallback } from "react";
import * as api from "../api";
import GeoShapeBuilder from "../components/local-seo/GeoShapeBuilder";
import LandmarksBuilder from "../components/local-seo/LandmarksBuilder";

const SITE_URL = window.novaToolsSEO?.siteUrl || "";
const MAX_HOLIDAY_OVERRIDES = 50;

const COUNTRIES = [
  { value: "", label: "Select country..." },
  { value: "AF", label: "Afghanistan" },
  { value: "AL", label: "Albania" },
  { value: "DZ", label: "Algeria" },
  { value: "AD", label: "Andorra" },
  { value: "AO", label: "Angola" },
  { value: "AG", label: "Antigua and Barbuda" },
  { value: "AR", label: "Argentina" },
  { value: "AM", label: "Armenia" },
  { value: "AU", label: "Australia" },
  { value: "AT", label: "Austria" },
  { value: "AZ", label: "Azerbaijan" },
  { value: "BS", label: "Bahamas" },
  { value: "BH", label: "Bahrain" },
  { value: "BD", label: "Bangladesh" },
  { value: "BB", label: "Barbados" },
  { value: "BY", label: "Belarus" },
  { value: "BE", label: "Belgium" },
  { value: "BZ", label: "Belize" },
  { value: "BJ", label: "Benin" },
  { value: "BT", label: "Bhutan" },
  { value: "BO", label: "Bolivia" },
  { value: "BA", label: "Bosnia and Herzegovina" },
  { value: "BW", label: "Botswana" },
  { value: "BR", label: "Brazil" },
  { value: "BN", label: "Brunei" },
  { value: "BG", label: "Bulgaria" },
  { value: "BF", label: "Burkina Faso" },
  { value: "BI", label: "Burundi" },
  { value: "KH", label: "Cambodia" },
  { value: "CM", label: "Cameroon" },
  { value: "CA", label: "Canada" },
  { value: "CF", label: "Central African Republic" },
  { value: "TD", label: "Chad" },
  { value: "CL", label: "Chile" },
  { value: "CN", label: "China" },
  { value: "CO", label: "Colombia" },
  { value: "KM", label: "Comoros" },
  { value: "CG", label: "Congo" },
  { value: "CD", label: "Congo (DRC)" },
  { value: "CR", label: "Costa Rica" },
  { value: "HR", label: "Croatia" },
  { value: "CU", label: "Cuba" },
  { value: "CY", label: "Cyprus" },
  { value: "CZ", label: "Czech Republic" },
  { value: "DK", label: "Denmark" },
  { value: "DJ", label: "Djibouti" },
  { value: "DM", label: "Dominica" },
  { value: "DO", label: "Dominican Republic" },
  { value: "EC", label: "Ecuador" },
  { value: "EG", label: "Egypt" },
  { value: "SV", label: "El Salvador" },
  { value: "GQ", label: "Equatorial Guinea" },
  { value: "ER", label: "Eritrea" },
  { value: "EE", label: "Estonia" },
  { value: "SZ", label: "Eswatini" },
  { value: "ET", label: "Ethiopia" },
  { value: "FJ", label: "Fiji" },
  { value: "FI", label: "Finland" },
  { value: "FR", label: "France" },
  { value: "GA", label: "Gabon" },
  { value: "GM", label: "Gambia" },
  { value: "GE", label: "Georgia" },
  { value: "DE", label: "Germany" },
  { value: "GH", label: "Ghana" },
  { value: "GR", label: "Greece" },
  { value: "GD", label: "Grenada" },
  { value: "GT", label: "Guatemala" },
  { value: "GN", label: "Guinea" },
  { value: "GW", label: "Guinea-Bissau" },
  { value: "GY", label: "Guyana" },
  { value: "HT", label: "Haiti" },
  { value: "HN", label: "Honduras" },
  { value: "HU", label: "Hungary" },
  { value: "IS", label: "Iceland" },
  { value: "IN", label: "India" },
  { value: "ID", label: "Indonesia" },
  { value: "IR", label: "Iran" },
  { value: "IQ", label: "Iraq" },
  { value: "IE", label: "Ireland" },
  { value: "IL", label: "Israel" },
  { value: "IT", label: "Italy" },
  { value: "JM", label: "Jamaica" },
  { value: "JP", label: "Japan" },
  { value: "JO", label: "Jordan" },
  { value: "KZ", label: "Kazakhstan" },
  { value: "KE", label: "Kenya" },
  { value: "KI", label: "Kiribati" },
  { value: "KP", label: "Korea (North)" },
  { value: "KR", label: "Korea (South)" },
  { value: "KW", label: "Kuwait" },
  { value: "KG", label: "Kyrgyzstan" },
  { value: "LA", label: "Laos" },
  { value: "LV", label: "Latvia" },
  { value: "LB", label: "Lebanon" },
  { value: "LS", label: "Lesotho" },
  { value: "LR", label: "Liberia" },
  { value: "LY", label: "Libya" },
  { value: "LI", label: "Liechtenstein" },
  { value: "LT", label: "Lithuania" },
  { value: "LU", label: "Luxembourg" },
  { value: "MG", label: "Madagascar" },
  { value: "MW", label: "Malawi" },
  { value: "MY", label: "Malaysia" },
  { value: "MV", label: "Maldives" },
  { value: "ML", label: "Mali" },
  { value: "MT", label: "Malta" },
  { value: "MH", label: "Marshall Islands" },
  { value: "MR", label: "Mauritania" },
  { value: "MU", label: "Mauritius" },
  { value: "MX", label: "Mexico" },
  { value: "FM", label: "Micronesia" },
  { value: "MD", label: "Moldova" },
  { value: "MC", label: "Monaco" },
  { value: "MN", label: "Mongolia" },
  { value: "ME", label: "Montenegro" },
  { value: "MA", label: "Morocco" },
  { value: "MZ", label: "Mozambique" },
  { value: "MM", label: "Myanmar" },
  { value: "NA", label: "Namibia" },
  { value: "NR", label: "Nauru" },
  { value: "NP", label: "Nepal" },
  { value: "NL", label: "Netherlands" },
  { value: "NZ", label: "New Zealand" },
  { value: "NI", label: "Nicaragua" },
  { value: "NE", label: "Niger" },
  { value: "NG", label: "Nigeria" },
  { value: "MK", label: "North Macedonia" },
  { value: "NO", label: "Norway" },
  { value: "OM", label: "Oman" },
  { value: "PK", label: "Pakistan" },
  { value: "PW", label: "Palau" },
  { value: "PS", label: "Palestine" },
  { value: "PA", label: "Panama" },
  { value: "PG", label: "Papua New Guinea" },
  { value: "PY", label: "Paraguay" },
  { value: "PE", label: "Peru" },
  { value: "PH", label: "Philippines" },
  { value: "PL", label: "Poland" },
  { value: "PT", label: "Portugal" },
  { value: "QA", label: "Qatar" },
  { value: "RO", label: "Romania" },
  { value: "RU", label: "Russia" },
  { value: "RW", label: "Rwanda" },
  { value: "KN", label: "Saint Kitts and Nevis" },
  { value: "LC", label: "Saint Lucia" },
  { value: "VC", label: "Saint Vincent and the Grenadines" },
  { value: "WS", label: "Samoa" },
  { value: "SM", label: "San Marino" },
  { value: "ST", label: "Sao Tome and Principe" },
  { value: "SA", label: "Saudi Arabia" },
  { value: "SN", label: "Senegal" },
  { value: "RS", label: "Serbia" },
  { value: "SC", label: "Seychelles" },
  { value: "SL", label: "Sierra Leone" },
  { value: "SG", label: "Singapore" },
  { value: "SK", label: "Slovakia" },
  { value: "SI", label: "Slovenia" },
  { value: "SB", label: "Solomon Islands" },
  { value: "SO", label: "Somalia" },
  { value: "ZA", label: "South Africa" },
  { value: "SS", label: "South Sudan" },
  { value: "ES", label: "Spain" },
  { value: "LK", label: "Sri Lanka" },
  { value: "SD", label: "Sudan" },
  { value: "SR", label: "Suriname" },
  { value: "SE", label: "Sweden" },
  { value: "CH", label: "Switzerland" },
  { value: "SY", label: "Syria" },
  { value: "TW", label: "Taiwan" },
  { value: "TJ", label: "Tajikistan" },
  { value: "TZ", label: "Tanzania" },
  { value: "TH", label: "Thailand" },
  { value: "TL", label: "Timor-Leste" },
  { value: "TG", label: "Togo" },
  { value: "TO", label: "Tonga" },
  { value: "TT", label: "Trinidad and Tobago" },
  { value: "TN", label: "Tunisia" },
  { value: "TR", label: "Turkey" },
  { value: "TM", label: "Turkmenistan" },
  { value: "TV", label: "Tuvalu" },
  { value: "UG", label: "Uganda" },
  { value: "UA", label: "Ukraine" },
  { value: "AE", label: "United Arab Emirates" },
  { value: "GB", label: "United Kingdom" },
  { value: "US", label: "United States" },
  { value: "UY", label: "Uruguay" },
  { value: "UZ", label: "Uzbekistan" },
  { value: "VU", label: "Vanuatu" },
  { value: "VA", label: "Vatican City" },
  { value: "VE", label: "Venezuela" },
  { value: "VN", label: "Vietnam" },
  { value: "YE", label: "Yemen" },
  { value: "ZM", label: "Zambia" },
  { value: "ZW", label: "Zimbabwe" },
];

const DAYS = [
  "Monday",
  "Tuesday",
  "Wednesday",
  "Thursday",
  "Friday",
  "Saturday",
  "Sunday",
];

const DEFAULT_OPENING_HOURS = DAYS.map((day) => ({
  day_of_week: day,
  opens: "",
  closes: "",
  closed: false,
}));

const defaultSettings = () => ({
  business_name: "",
  business_type: "LocalBusiness",
  street_address: "",
  city: "",
  state_region: "",
  postal_code: "",
  country: "",
  phone: "",
  website_url: SITE_URL,
  email: "",
  latitude: "",
  longitude: "",
  opening_hours: DEFAULT_OPENING_HOURS,
  holiday_overrides: [],
  area_served: [],
  contact_page_id: "",
  sameas: [],
  geoshape_coordinates: [],
  landmarks: [],
});

export default function LocalSEO() {
  const [settings, setSettings] = useState(defaultSettings());
  const [businessTypes, setBusinessTypes] = useState({});
  const [pages, setPages] = useState([]);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");
  const [geoError, setGeoError] = useState("");

  useEffect(() => {
    Promise.all([
      api.get("/settings").then((s) => {
        const local = s.wseo_local_seo;
        if (local && typeof local === "object") {
          setSettings((prev) => ({
            ...prev,
            ...local,
            opening_hours: Array.isArray(local.opening_hours)
              ? local.opening_hours
              : DEFAULT_OPENING_HOURS,
            holiday_overrides: Array.isArray(local.holiday_overrides)
              ? local.holiday_overrides
              : [],
            area_served: Array.isArray(local.area_served)
              ? local.area_served
              : [],
          }));
        }
      }),
      api.get("/local-business-types").then(setBusinessTypes),
      api.get("/pages").then(setPages),
    ]).finally(() => setLoading(false));
  }, []);

  const set = useCallback((key, value) => {
    setSettings((s) => ({ ...s, [key]: value }));
  }, []);

  function save() {
    setSaving(true);
    setMessage("");

    // Prune old holiday overrides
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
    const pruned = (settings.holiday_overrides || []).filter(
      (h) => !h.date || new Date(h.date) >= oneYearAgo,
    );

    const payload = { ...settings, holiday_overrides: pruned };
    setSettings(payload);

    api
      .post("/settings", { wseo_local_seo: payload })
      .then(() => setMessage("Local SEO settings saved."))
      .catch(() => setMessage("Error saving settings."))
      .finally(() => setSaving(false));
  }

  // Geo helpers
  function validateCoords(lat, lng) {
    const latF = parseFloat(lat);
    const lngF = parseFloat(lng);
    if (lat !== "" && (isNaN(latF) || latF < -90 || latF > 90)) return false;
    if (lng !== "" && (isNaN(lngF) || lngF < -180 || lngF > 180)) return false;
    return true;
  }

  function useCurrentLocation() {
    setGeoError("");
    if (!navigator.geolocation) {
      setGeoError("Geolocation is not supported by your browser.");
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        set("latitude", pos.coords.latitude.toFixed(6));
        set("longitude", pos.coords.longitude.toFixed(6));
      },
      (err) => {
        setGeoError(
          err.code === err.PERMISSION_DENIED
            ? "Location permission denied. Please enter coordinates manually."
            : "Unable to retrieve location. Please enter coordinates manually.",
        );
      },
    );
  }

  // Opening hours helpers
  function setHour(index, field, value) {
    const updated = [...settings.opening_hours];
    updated[index] = { ...updated[index], [field]: value };
    set("opening_hours", updated);
  }

  // Holiday overrides helpers
  function addHolidayOverride() {
    if ((settings.holiday_overrides || []).length >= MAX_HOLIDAY_OVERRIDES)
      return;
    set("holiday_overrides", [
      ...(settings.holiday_overrides || []),
      { date: "", opens: "09:00", closes: "17:00", closed: false },
    ]);
  }

  function updateHolidayOverride(index, field, value) {
    const updated = [...settings.holiday_overrides];
    updated[index] = { ...updated[index], [field]: value };
    set("holiday_overrides", updated);
  }

  function removeHolidayOverride(index) {
    set(
      "holiday_overrides",
      settings.holiday_overrides.filter((_, i) => i !== index),
    );
  }

  // Area served helpers
  function addAreaServed(value) {
    const trimmed = value.trim();
    if (!trimmed) return;
    if ((settings.area_served || []).includes(trimmed)) return;
    set("area_served", [...(settings.area_served || []), trimmed]);
  }

  function removeAreaServed(index) {
    set(
      "area_served",
      settings.area_served.filter((_, i) => i !== index),
    );
  }

  if (loading) {
    return (
      <div>
        <h2 className="text-2xl font-semibold text-gray-900">Local SEO</h2>
        <p className="mt-2 text-sm text-gray-500">Loading...</p>
      </div>
    );
  }

  const coordsValid = validateCoords(settings.latitude, settings.longitude);

  return (
    <div className="max-w-3xl">
      <h2 className="text-2xl font-semibold text-gray-900">Local SEO</h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Configure local business schema for better visibility in local search
        results.
      </p>

      {/* Business Info */}
      <section className="space-y-4">
        <h3 className="text-lg font-medium text-gray-800">
          Business Information
        </h3>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            Business Name
          </label>
          <input
            type="text"
            value={settings.business_name}
            onChange={(e) => set("business_name", e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            placeholder="Acme Dental"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            Business Type
          </label>
          <select
            value={settings.business_type}
            onChange={(e) => set("business_type", e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          >
            {Object.entries(businessTypes).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>
      </section>

      {/* Address */}
      <section className="mt-8 space-y-4">
        <h3 className="text-lg font-medium text-gray-800">Address</h3>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            Street Address
          </label>
          <input
            type="text"
            value={settings.street_address}
            onChange={(e) => set("street_address", e.target.value)}
            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            placeholder="123 Main St"
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              City
            </label>
            <input
              type="text"
              value={settings.city}
              onChange={(e) => set("city", e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder="Tallinn"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              State / Region
            </label>
            <input
              type="text"
              value={settings.state_region}
              onChange={(e) => set("state_region", e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder="Harju County"
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Postal Code
            </label>
            <input
              type="text"
              value={settings.postal_code}
              onChange={(e) => set("postal_code", e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder="10111"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Country
            </label>
            <select
              value={settings.country}
              onChange={(e) => set("country", e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
              {COUNTRIES.map((c) => (
                <option key={c.value} value={c.value}>
                  {c.label}
                </option>
              ))}
            </select>
          </div>
        </div>
      </section>

      {/* Contact */}
      <section className="mt-8 space-y-4">
        <h3 className="text-lg font-medium text-gray-800">Contact</h3>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Phone Number
            </label>
            <input
              type="tel"
              value={settings.phone}
              onChange={(e) => set("phone", e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder="+372 555 1234"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Website URL
            </label>
            <input
              type="url"
              value={settings.website_url}
              onChange={(e) => set("website_url", e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder={SITE_URL}
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Email <span className="text-gray-400">(optional)</span>
            </label>
            <input
              type="email"
              value={settings.email}
              onChange={(e) => set("email", e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              placeholder="info@example.com"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Contact Page
            </label>
            <select
              value={settings.contact_page_id}
              onChange={(e) => set("contact_page_id", e.target.value)}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
              <option value="">None</option>
              {pages.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.title}
                </option>
              ))}
            </select>
          </div>
        </div>
      </section>

      {/* Geo Coordinates */}
      <section className="mt-8 space-y-4">
        <h3 className="text-lg font-medium text-gray-800">Geo Coordinates</h3>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Latitude
            </label>
            <input
              type="text"
              inputMode="decimal"
              value={settings.latitude}
              onChange={(e) => {
                set("latitude", e.target.value);
              }}
              className={`mt-1 block w-full rounded-md border px-3 py-2 text-sm ${
                settings.latitude !== "" &&
                !validateCoords(settings.latitude, settings.longitude)
                  ? "border-red-500"
                  : "border-gray-300"
              }`}
              placeholder="59.4370"
            />
            {settings.latitude !== "" &&
              !validateCoords(settings.latitude, "") && (
                <p className="mt-1 text-xs text-red-500">
                  Must be a number between -90 and 90
                </p>
              )}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Longitude
            </label>
            <input
              type="text"
              inputMode="decimal"
              value={settings.longitude}
              onChange={(e) => {
                set("longitude", e.target.value);
              }}
              className={`mt-1 block w-full rounded-md border px-3 py-2 text-sm ${
                settings.longitude !== "" &&
                !validateCoords(settings.latitude, settings.longitude)
                  ? "border-red-500"
                  : "border-gray-300"
              }`}
              placeholder="24.7536"
            />
            {settings.longitude !== "" &&
              !validateCoords("", settings.longitude) && (
                <p className="mt-1 text-xs text-red-500">
                  Must be a number between -180 and 180
                </p>
              )}
          </div>
        </div>

        <button
          type="button"
          onClick={useCurrentLocation}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
        >
          Use My Current Location
        </button>
        {geoError && <p className="text-xs text-amber-600">{geoError}</p>}
      </section>

      {/* Opening Hours */}
      <section className="mt-8 space-y-4">
        <h3 className="text-lg font-medium text-gray-800">Opening Hours</h3>

        <div className="space-y-2">
          {settings.opening_hours.map((entry, i) => (
            <div
              key={entry.day_of_week}
              className="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2"
            >
              <span className="w-24 text-sm font-medium text-gray-700">
                {entry.day_of_week}
              </span>
              <input
                type="time"
                value={entry.opens}
                onChange={(e) => setHour(i, "opens", e.target.value)}
                disabled={entry.closed}
                className="rounded-md border border-gray-300 px-2 py-1 text-sm disabled:opacity-50"
              />
              <span className="text-sm text-gray-400">to</span>
              <input
                type="time"
                value={entry.closes}
                onChange={(e) => setHour(i, "closes", e.target.value)}
                disabled={entry.closed}
                className="rounded-md border border-gray-300 px-2 py-1 text-sm disabled:opacity-50"
              />
              <label className="ml-auto flex items-center gap-2 text-sm text-gray-600">
                <input
                  type="checkbox"
                  checked={entry.closed}
                  onChange={(e) => setHour(i, "closed", e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300"
                />
                Closed
              </label>
            </div>
          ))}
        </div>
      </section>

      {/* Holiday Overrides */}
      <section className="mt-8 space-y-4">
        <h3 className="text-lg font-medium text-gray-800">Holiday Overrides</h3>

        <div className="space-y-2">
          {(settings.holiday_overrides || []).map((holiday, i) => (
            <div
              key={i}
              className="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2"
            >
              <input
                type="date"
                value={holiday.date}
                onChange={(e) =>
                  updateHolidayOverride(i, "date", e.target.value)
                }
                className="rounded-md border border-gray-300 px-2 py-1 text-sm"
              />
              <input
                type="time"
                value={holiday.opens}
                onChange={(e) =>
                  updateHolidayOverride(i, "opens", e.target.value)
                }
                disabled={holiday.closed}
                className="rounded-md border border-gray-300 px-2 py-1 text-sm disabled:opacity-50"
              />
              <span className="text-sm text-gray-400">to</span>
              <input
                type="time"
                value={holiday.closes}
                onChange={(e) =>
                  updateHolidayOverride(i, "closes", e.target.value)
                }
                disabled={holiday.closed}
                className="rounded-md border border-gray-300 px-2 py-1 text-sm disabled:opacity-50"
              />
              <label className="flex items-center gap-1 text-sm text-gray-600">
                <input
                  type="checkbox"
                  checked={holiday.closed}
                  onChange={(e) =>
                    updateHolidayOverride(i, "closed", e.target.checked)
                  }
                  className="h-4 w-4 rounded border-gray-300"
                />
                Closed
              </label>
              <button
                type="button"
                onClick={() => removeHolidayOverride(i)}
                className="ml-auto text-sm text-red-500 hover:text-red-700"
              >
                Remove
              </button>
            </div>
          ))}
        </div>

        <button
          type="button"
          onClick={addHolidayOverride}
          disabled={
            (settings.holiday_overrides || []).length >= MAX_HOLIDAY_OVERRIDES
          }
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          Add Holiday Override
        </button>
        {(settings.holiday_overrides || []).length >= MAX_HOLIDAY_OVERRIDES && (
          <p className="text-xs text-amber-600">
            Maximum of {MAX_HOLIDAY_OVERRIDES} holiday overrides reached.
          </p>
        )}
      </section>

      {/* Area Served */}
      <section className="mt-8 space-y-4">
        <h3 className="text-lg font-medium text-gray-800">Area Served</h3>

        <div className="flex flex-wrap gap-2">
          {(settings.area_served || []).map((area, i) => (
            <span
              key={i}
              className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700"
            >
              {area}
              <button
                type="button"
                onClick={() => removeAreaServed(i)}
                className="text-gray-400 hover:text-gray-600"
              >
                &times;
              </button>
            </span>
          ))}
        </div>

        <input
          type="text"
          placeholder="Type a location and press Enter"
          className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          onKeyDown={(e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              addAreaServed(e.target.value);
              e.target.value = "";
            }
          }}
        />
      </section>

      {/* sameAs Entity Linking */}
      <section className="mt-8 space-y-4">
        <h3 className="text-lg font-medium text-gray-800">
          Entity URLs (sameAs)
        </h3>
        <p className="text-sm text-gray-500">
          Links to authoritative entity pages (Wikipedia, Wikidata, Google
          Business, Facebook, LinkedIn, etc.).
        </p>

        <div className="space-y-2">
          {(settings.sameas || []).map((url, i) => (
            <div key={i} className="flex items-center gap-2">
              <input
                type="url"
                value={url}
                onChange={(e) => {
                  const updated = [...(settings.sameas || [])];
                  updated[i] = e.target.value;
                  set("sameas", updated);
                }}
                className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                placeholder="https://en.wikipedia.org/wiki/..."
              />
              <button
                type="button"
                onClick={() =>
                  set(
                    "sameas",
                    (settings.sameas || []).filter((_, j) => j !== i),
                  )
                }
                className="text-sm text-red-500 hover:text-red-700"
              >
                ×
              </button>
            </div>
          ))}
        </div>

        <button
          type="button"
          onClick={() => set("sameas", [...(settings.sameas || []), ""])}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
        >
          + Add URL
        </button>
      </section>

      {/* Service Area Polygon */}
      <section className="mt-8">
        <GeoShapeBuilder
          coordinates={settings.geoshape_coordinates || []}
          onChange={(coords) => set("geoshape_coordinates", coords)}
        />
      </section>

      {/* Nearby Landmarks */}
      <section className="mt-8">
        <LandmarksBuilder
          landmarks={settings.landmarks || []}
          onChange={(lms) => set("landmarks", lms)}
        />
      </section>

      {/* Save */}
      <div className="mt-8">
        <button
          onClick={save}
          disabled={saving || !coordsValid}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? "Saving..." : "Save Local SEO Settings"}
        </button>
        {!coordsValid && (
          <p className="mt-1 text-xs text-red-500">
            Fix coordinate validation errors before saving.
          </p>
        )}
        {message && (
          <p
            className={`mt-2 text-sm ${
              message.includes("Error") ? "text-red-600" : "text-green-600"
            }`}
          >
            {message}
          </p>
        )}
      </div>
    </div>
  );
}
