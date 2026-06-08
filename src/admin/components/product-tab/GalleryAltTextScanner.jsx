import React, { useState } from "react";

export default function GalleryAltTextScanner({ images, total, missingAlt, loading, generating, onBulkGenerate, error }) {
  const [confirmOpen, setConfirmOpen] = useState(false);

  if (loading) {
    return (
      <div className="rounded-md border border-gray-200 bg-white p-4">
        <h3 className="text-sm font-semibold text-gray-700">Gallery Alt Text</h3>
        <p className="mt-1 text-xs text-gray-400">Loading images…</p>
      </div>
    );
  }

  if (total === 0) {
    return (
      <div className="rounded-md border border-gray-200 bg-white p-4">
        <h3 className="text-sm font-semibold text-gray-700">Gallery Alt Text</h3>
        <p className="mt-1 text-xs text-yellow-700">
          No gallery images found. Add images to improve product SEO.
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-md border border-gray-200 bg-white p-4">
      <h3 className="mb-2 text-sm font-semibold text-gray-700">
        Gallery Alt Text
        {missingAlt === 0 ? (
          <span className="ml-2 text-xs font-normal text-green-600">
            All {total} images have alt text
          </span>
        ) : (
          <span className="ml-2 text-xs font-normal text-red-600">
            {missingAlt} of {total} images missing alt text
          </span>
        )}
      </h3>

      {images.length > 0 && (
        <ul className="mb-3 space-y-1">
          {images.map((img, idx) => (
            <li key={img.id} className="flex items-center gap-2 text-xs">
              {img.sourceUrl ? (
                <img
                  src={img.sourceUrl}
                  alt=""
                  className="h-6 w-6 rounded border border-gray-200 object-cover"
                />
              ) : (
                <span className="inline-block h-6 w-6 rounded border border-gray-200 bg-gray-100" />
              )}
              <span className="truncate text-gray-600">
                {img.title || `Image ${idx + 1}`}
              </span>
              {img.alt ? (
                <span className="ml-auto text-green-600">✓</span>
              ) : (
                <span className="ml-auto text-red-500">✗</span>
              )}
            </li>
          ))}
        </ul>
      )}

      {error && (
        <p className="mb-2 text-xs text-red-600">{error}</p>
      )}

      {missingAlt > 0 && !confirmOpen && (
        <button
          type="button"
          onClick={() => setConfirmOpen(true)}
          disabled={generating}
          className="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          Bulk Generate Alt-Text
        </button>
      )}

      {confirmOpen && (
        <div className="rounded border border-yellow-200 bg-yellow-50 p-3">
          <p className="text-xs text-yellow-800">
            This will generate alt text for {missingAlt} image{missingAlt !== 1 ? "s" : ""} with
            missing descriptions using the format:{" "}
            <code className="text-[11px]">[Product Title] - [Primary Category] - Image [N]</code>
          </p>
          <div className="mt-2 flex gap-2">
            <button
              type="button"
              onClick={async () => {
                setConfirmOpen(false);
                await onBulkGenerate();
              }}
              disabled={generating}
              className="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {generating ? "Generating…" : "Confirm"}
            </button>
            <button
              type="button"
              onClick={() => setConfirmOpen(false)}
              className="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
            >
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
