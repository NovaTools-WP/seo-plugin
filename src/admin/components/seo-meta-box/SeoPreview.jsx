import React, { useState, useEffect } from "react";
import {
  Globe,
  Facebook,
  Twitter,
  Smartphone,
  Monitor,
  Image as ImageIcon,
  Eye,
  Sun,
  Moon,
} from "lucide-react";

export default function SeoPreview({
  title,
  description,
  url,
  ogTitle,
  ogDesc,
  ogImage,
  twitterCard,
  twitterTitle,
  twitterDesc,
  twitterImage,
  initialMeta,
}) {
  const [platform, setPlatform] = useState("google"); // "google", "facebook", "twitter"
  const [googleMode, setGoogleMode] = useState("mobile"); // "mobile", "desktop"
  const [twitterTheme, setTwitterTheme] = useState("dark"); // "light", "dark"
  const [domFeaturedImageUrl, setDomFeaturedImageUrl] = useState("");

  // Extract domain and paths for display
  const displayUrl = url || "https://example.com/your-page";
  let domain = "example.com";
  let pathSegments = [];
  try {
    const parsed = new URL(displayUrl);
    domain = parsed.hostname;
    pathSegments = parsed.pathname.split("/").filter(Boolean);
  } catch (e) {
    // fallback if invalid URL
    domain = displayUrl.replace(/https?:\/\/(www\.)?/, "").split("/")[0];
    pathSegments = displayUrl.split("/").slice(3).filter(Boolean);
  }

  // Monitor DOM for featured image shifts (real-time responsiveness)
  useEffect(() => {
    const getFeaturedImageUrl = () => {
      // Classic Editor
      const classicImg = document.querySelector("#postimagediv img");
      if (classicImg && classicImg.src) {
        return classicImg.src;
      }
      // Block Editor (Gutenberg)
      const blockImg = document.querySelector(
        ".editor-post-featured-image__preview img, .editor-post-featured-image img, .components-responsive-wrapper__content img",
      );
      if (blockImg && blockImg.src) {
        return blockImg.src;
      }
      return "";
    };

    const updateImage = () => {
      const imgUrl = getFeaturedImageUrl();
      if (imgUrl) {
        setDomFeaturedImageUrl(imgUrl);
      }
    };

    // Run immediately
    updateImage();

    // Observe changes in body for editor modifications
    const observer = new MutationObserver(updateImage);
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
    });

    // Fallback interval
    const interval = setInterval(updateImage, 1500);

    return () => {
      observer.disconnect();
      clearInterval(interval);
    };
  }, []);

  // Determine actual image fallbacks
  const savedThumbnailUrl = initialMeta?._thumbnail_url || "";
  const fallbackImage = ogImage || domFeaturedImageUrl || savedThumbnailUrl;
  const twitterFallbackImage = twitterImage || fallbackImage;

  // Title fallback computations
  const googleTitle = title || "Please enter an SEO title...";
  const googleDesc =
    description ||
    "Please enter a meta description so search engines can display a snippet here.";

  const facebookTitle =
    ogTitle || title || "Please enter an OpenGraph title...";
  const facebookDesc =
    ogDesc || description || "Please enter an OpenGraph description...";

  const xTitle =
    twitterTitle || ogTitle || title || "Please enter a Twitter title...";
  const xDesc =
    twitterDesc ||
    ogDesc ||
    description ||
    "Please enter a Twitter description...";

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <div className="mb-4 flex flex-col justify-between gap-3 border-b border-gray-100 pb-3 sm:flex-row sm:items-center">
        <div className="flex items-center gap-2">
          <Eye className="h-4 w-4 text-blue-600" />
          <span className="text-sm font-semibold text-gray-800">
            SEO Snippet Preview
          </span>
        </div>

        {/* Platform selection tabs */}
        <div className="flex gap-1 rounded-md bg-gray-50 p-0.5">
          <button
            type="button"
            onClick={() => setPlatform("google")}
            className={`flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium transition-all ${
              platform === "google"
                ? "bg-white text-gray-900 shadow-sm"
                : "text-gray-500 hover:text-gray-900"
            }`}
          >
            <Globe className="h-3.5 w-3.5" />
            Google
          </button>
          <button
            type="button"
            onClick={() => setPlatform("facebook")}
            className={`flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium transition-all ${
              platform === "facebook"
                ? "bg-white text-gray-900 shadow-sm"
                : "text-gray-500 hover:text-gray-900"
            }`}
          >
            <Facebook className="h-3.5 w-3.5 text-blue-600" />
            Facebook
          </button>
          <button
            type="button"
            onClick={() => setPlatform("twitter")}
            className={`flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium transition-all ${
              platform === "twitter"
                ? "bg-white text-gray-900 shadow-sm"
                : "text-gray-500 hover:text-gray-900"
            }`}
          >
            <Twitter className="h-3.5 w-3.5 text-sky-500" />
            Twitter / X
          </button>
        </div>
      </div>

      {/* Render Google Platform Preview */}
      {platform === "google" && (
        <div className="space-y-3">
          <div className="flex justify-end gap-2 border-b border-gray-100 pb-2">
            <button
              type="button"
              onClick={() => setGoogleMode("mobile")}
              className={`flex items-center gap-1 rounded px-2 py-0.5 text-xs transition-all ${
                googleMode === "mobile"
                  ? "bg-blue-50 text-blue-700 font-semibold"
                  : "text-gray-500 hover:bg-gray-100"
              }`}
            >
              <Smartphone className="h-3 w-3" />
              Mobile
            </button>
            <button
              type="button"
              onClick={() => setGoogleMode("desktop")}
              className={`flex items-center gap-1 rounded px-2 py-0.5 text-xs transition-all ${
                googleMode === "desktop"
                  ? "bg-blue-50 text-blue-700 font-semibold"
                  : "text-gray-500 hover:bg-gray-100"
              }`}
            >
              <Monitor className="h-3 w-3" />
              Desktop
            </button>
          </div>

          <div className="bg-gray-50/50 p-4 rounded-md border border-gray-100 flex justify-center">
            {googleMode === "mobile" ? (
              /* Google Mobile Preview */
              <div className="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-4 shadow-sm font-sans text-left">
                <div className="flex items-center gap-2 text-xs text-gray-600">
                  <div className="flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500">
                    {domain.charAt(0).toUpperCase()}
                  </div>
                  <div className="overflow-hidden text-ellipsis whitespace-nowrap">
                    <span className="font-semibold text-gray-800">
                      {domain}
                    </span>
                    <span className="mx-1 text-gray-400">›</span>
                    {pathSegments.length > 0
                      ? pathSegments.join(" › ")
                      : "page"}
                  </div>
                </div>
                <h4 className="mt-1.5 text-[19px] leading-tight text-[#1a0dab] hover:underline cursor-pointer font-medium">
                  {googleTitle}
                </h4>
                <p className="mt-1.5 text-sm leading-relaxed text-[#4d5156] break-words">
                  {googleDesc}
                </p>
              </div>
            ) : (
              /* Google Desktop Preview */
              <div className="w-full max-w-2xl rounded-lg border border-gray-200 bg-white p-4 shadow-sm font-sans text-left">
                <div className="text-[12px] text-[#202124] leading-5">
                  <span>https://{domain}</span>
                  {pathSegments.length > 0 && (
                    <span className="text-gray-500">
                      {" "}
                      › {pathSegments.join(" › ")}
                    </span>
                  )}
                </div>
                <h4 className="mt-1 text-xl leading-normal text-[#1a0dab] hover:underline cursor-pointer font-medium">
                  {googleTitle}
                </h4>
                <p className="mt-1 text-sm leading-relaxed text-[#4d5156] break-words">
                  {googleDesc}
                </p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Render Facebook Platform Preview */}
      {platform === "facebook" && (
        <div className="bg-gray-50/50 p-4 rounded-md border border-gray-100 flex justify-center">
          <div className="w-full max-w-[500px] border border-gray-200 bg-white rounded-md overflow-hidden font-sans text-left shadow-sm">
            {/* FB Post Header */}
            <div className="flex items-center gap-2.5 p-3">
              <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                {domain.charAt(0).toUpperCase()}
              </div>
              <div>
                <h5 className="text-sm font-semibold text-gray-900 leading-tight">
                  {domain}
                </h5>
                <p className="text-xs text-gray-500 mt-0.5">
                  Just now · Sponsored · 🌐
                </p>
              </div>
            </div>
            {/* FB Post Text */}
            <div className="px-3 pb-3 text-sm text-gray-800 break-words">
              {facebookDesc}
            </div>
            {/* FB Card Image/Content */}
            <div className="border-t border-gray-200">
              {fallbackImage ? (
                <div className="relative aspect-[1.91/1] w-full overflow-hidden bg-gray-100">
                  <img
                    src={fallbackImage}
                    alt="Facebook Preview"
                    className="h-full w-full object-cover"
                  />
                </div>
              ) : (
                <div className="flex aspect-[1.91/1] w-full flex-col items-center justify-center bg-gray-100 text-gray-400">
                  <ImageIcon className="h-10 w-10 stroke-1" />
                  <span className="mt-1 text-xs">
                    No image selected / placeholder
                  </span>
                </div>
              )}
              {/* FB Card Footer Info */}
              <div className="bg-[#f2f3f5] p-3 border-t border-gray-200">
                <span className="text-[11px] uppercase tracking-wider text-gray-500 block">
                  {domain}
                </span>
                <h4 className="text-[16px] font-semibold text-[#1d2129] mt-0.5 line-clamp-1">
                  {facebookTitle}
                </h4>
                <p className="text-xs text-[#606770] mt-1 line-clamp-1">
                  {facebookDesc}
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Render Twitter / X Platform Preview */}
      {platform === "twitter" && (
        <div className="space-y-3">
          <div className="flex justify-end gap-2 border-b border-gray-100 pb-2">
            <button
              type="button"
              onClick={() => setTwitterTheme("light")}
              className={`flex items-center gap-1 rounded px-2 py-0.5 text-xs transition-all ${
                twitterTheme === "light"
                  ? "bg-gray-200 text-gray-800 font-semibold"
                  : "text-gray-500 hover:bg-gray-100"
              }`}
            >
              <Sun className="h-3 w-3" />
              Light
            </button>
            <button
              type="button"
              onClick={() => setTwitterTheme("dark")}
              className={`flex items-center gap-1 rounded px-2 py-0.5 text-xs transition-all ${
                twitterTheme === "dark"
                  ? "bg-gray-800 text-white font-semibold"
                  : "text-gray-500 hover:bg-gray-100"
              }`}
            >
              <Moon className="h-3 w-3" />
              Dark
            </button>
          </div>

          <div className="bg-gray-50/50 p-4 rounded-md border border-gray-100 flex justify-center">
            <div
              className={`w-full max-w-[500px] border rounded-xl overflow-hidden font-sans text-left p-4 transition-colors duration-200 ${
                twitterTheme === "dark"
                  ? "bg-black border-[#2f3336] text-white"
                  : "bg-white border-gray-200 text-gray-900"
              }`}
            >
              {/* X Profile Header */}
              <div className="flex gap-3 items-start">
                <div className="h-10 w-10 rounded-full bg-blue-500/20 border border-blue-500/30 flex items-center justify-center font-bold text-blue-500 flex-shrink-0">
                  {domain.charAt(0).toUpperCase()}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-1">
                    <span className="font-bold text-sm truncate hover:underline cursor-pointer">
                      {domain.split(".")[0]}
                    </span>
                    <span
                      className={`text-xs truncate ${
                        twitterTheme === "dark"
                          ? "text-gray-500"
                          : "text-gray-400"
                      }`}
                    >
                      @{domain.split(".")[0]} · 1h
                    </span>
                  </div>
                  <p className="mt-1 text-[15px] leading-relaxed break-words">
                    {xDesc}
                  </p>
                </div>
              </div>

              {/* X Card Container */}
              <div className="mt-3 ml-13 pl-13">
                {twitterCard === "summary_large_image" ? (
                  /* Large Image Card layout */
                  <div
                    className={`border rounded-2xl overflow-hidden ${
                      twitterTheme === "dark"
                        ? "border-[#2f3336]"
                        : "border-gray-200"
                    }`}
                  >
                    {twitterFallbackImage ? (
                      <div className="relative aspect-[1.91/1] w-full overflow-hidden bg-gray-900">
                        <img
                          src={twitterFallbackImage}
                          alt="Twitter Preview"
                          className="h-full w-full object-cover"
                        />
                      </div>
                    ) : (
                      <div className="flex aspect-[1.91/1] w-full flex-col items-center justify-center bg-gray-100 text-gray-400">
                        <ImageIcon className="h-10 w-10 stroke-1" />
                        <span className="mt-1 text-xs">
                          No image selected / placeholder
                        </span>
                      </div>
                    )}
                    <div className="p-3">
                      <span
                        className={`text-[13px] block ${
                          twitterTheme === "dark"
                            ? "text-gray-500"
                            : "text-gray-400"
                        }`}
                      >
                        {domain}
                      </span>
                      <h4 className="text-sm font-semibold mt-0.5 line-clamp-1">
                        {xTitle}
                      </h4>
                      <p
                        className={`text-[13px] mt-0.5 line-clamp-2 ${
                          twitterTheme === "dark"
                            ? "text-gray-500"
                            : "text-gray-600"
                        }`}
                      >
                        {xDesc}
                      </p>
                    </div>
                  </div>
                ) : (
                  /* Summary Card layout (small photo on left, title on right) */
                  <div
                    className={`border rounded-2xl overflow-hidden flex h-[120px] ${
                      twitterTheme === "dark"
                        ? "border-[#2f3336]"
                        : "border-gray-200"
                    }`}
                  >
                    <div className="w-[120px] h-full flex-shrink-0 bg-gray-900 border-r border-inherit overflow-hidden">
                      {twitterFallbackImage ? (
                        <img
                          src={twitterFallbackImage}
                          alt="Twitter Preview"
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        <div className="flex h-full w-full flex-col items-center justify-center bg-gray-100 text-gray-400">
                          <ImageIcon className="h-6 w-6 stroke-1" />
                        </div>
                      )}
                    </div>
                    <div className="p-3 flex flex-col justify-center min-w-0 flex-1">
                      <span
                        className={`text-[11px] ${
                          twitterTheme === "dark"
                            ? "text-gray-500"
                            : "text-gray-400"
                        }`}
                      >
                        {domain}
                      </span>
                      <h4 className="text-sm font-semibold truncate mt-0.5">
                        {xTitle}
                      </h4>
                      <p
                        className={`text-[12px] line-clamp-2 mt-0.5 ${
                          twitterTheme === "dark"
                            ? "text-gray-500"
                            : "text-gray-600"
                        }`}
                      >
                        {xDesc}
                      </p>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
