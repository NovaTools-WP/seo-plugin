import React from "react";

export default function LandmarksBuilder({ landmarks, onChange }) {
  const addLandmark = () => {
    onChange([...landmarks, { name: "", url: "" }]);
  };

  const removeLandmark = (index) => {
    onChange(landmarks.filter((_, i) => i !== index));
  };

  const updateLandmark = (index, field, value) => {
    const updated = [...landmarks];
    updated[index] = { ...updated[index], [field]: value };
    onChange(updated);
  };

  const moveUp = (index) => {
    if (index === 0) return;
    const updated = [...landmarks];
    [updated[index - 1], updated[index]] = [updated[index], updated[index - 1]];
    onChange(updated);
  };

  const moveDown = (index) => {
    if (index === landmarks.length - 1) return;
    const updated = [...landmarks];
    [updated[index], updated[index + 1]] = [updated[index + 1], updated[index]];
    onChange(updated);
  };

  return (
    <div>
      <h3 className="text-lg font-medium text-gray-800">Nearby Landmarks</h3>
      <p className="mt-1 text-sm text-gray-500">
        Associate your business with nearby landmarks for hyper-local SEO
        signals.
      </p>

      <div className="mt-4 space-y-2">
        {landmarks.map((landmark, i) => (
          <div
            key={i}
            className="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2"
          >
            <input
              type="text"
              value={landmark.name}
              onChange={(e) => updateLandmark(i, "name", e.target.value)}
              placeholder="Landmark name"
              className="rounded-md border border-gray-300 px-2 py-1 text-sm"
              style={{ flex: 1 }}
            />
            <input
              type="url"
              value={landmark.url}
              onChange={(e) => updateLandmark(i, "url", e.target.value)}
              placeholder="https://www.wikidata.org/wiki/..."
              className="rounded-md border border-gray-300 px-2 py-1 text-sm"
              style={{ flex: 1.5 }}
            />
            <div className="flex gap-1">
              <button
                type="button"
                onClick={() => moveUp(i)}
                disabled={i === 0}
                className="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-30"
              >
                ↑
              </button>
              <button
                type="button"
                onClick={() => moveDown(i)}
                disabled={i === landmarks.length - 1}
                className="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-30"
              >
                ↓
              </button>
              <button
                type="button"
                onClick={() => removeLandmark(i)}
                className="text-sm text-red-400 hover:text-red-600"
              >
                ×
              </button>
            </div>
          </div>
        ))}
      </div>

      <button
        type="button"
        onClick={addLandmark}
        className="mt-3 rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
      >
        + Add Landmark
      </button>
    </div>
  );
}
