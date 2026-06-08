import React from "react";

export default function GeoShapeBuilder({ coordinates, onChange }) {
  const addPoint = () => {
    onChange([...coordinates, { lat: "", lng: "" }]);
  };

  const removePoint = (index) => {
    onChange(coordinates.filter((_, i) => i !== index));
  };

  const updatePoint = (index, field, value) => {
    const updated = [...coordinates];
    updated[index] = { ...updated[index], [field]: value };
    onChange(updated);
  };

  const clearAll = () => {
    onChange([]);
  };

  const validCount = coordinates.filter(
    (c) => c.lat !== "" && c.lng !== "" && !isNaN(parseFloat(c.lat)) && !isNaN(parseFloat(c.lng)),
  ).length;

  return (
    <div>
      <h3 className="text-lg font-medium text-gray-800">
        Service Area Polygon
      </h3>
      <p className="mt-1 text-sm text-gray-500">
        Define a service area polygon for your business. Requires at least 3 coordinate points.
      </p>

      {coordinates.length > 0 && coordinates.length < 3 && (
        <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
          At least 3 points are required to form a valid polygon. Currently {coordinates.length} point(s).
        </div>
      )}

      <div className="mt-4 space-y-2">
        {coordinates.map((point, i) => (
          <div
            key={i}
            className="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2"
          >
            <span className="w-8 text-xs text-gray-400">#{i + 1}</span>
            <div className="flex items-center gap-2 flex-1">
              <label className="text-xs text-gray-500">Lat:</label>
              <input
                type="text"
                inputMode="decimal"
                value={point.lat}
                onChange={(e) => updatePoint(i, "lat", e.target.value)}
                className="rounded-md border border-gray-300 px-2 py-1 text-sm"
                style={{ width: "140px" }}
                placeholder="59.4370"
              />
              <label className="text-xs text-gray-500">Lng:</label>
              <input
                type="text"
                inputMode="decimal"
                value={point.lng}
                onChange={(e) => updatePoint(i, "lng", e.target.value)}
                className="rounded-md border border-gray-300 px-2 py-1 text-sm"
                style={{ width: "140px" }}
                placeholder="24.7536"
              />
            </div>
            <button
              type="button"
              onClick={() => removePoint(i)}
              className="text-sm text-red-400 hover:text-red-600"
            >
              ×
            </button>
          </div>
        ))}
      </div>

      <div className="mt-3 flex gap-2">
        <button
          type="button"
          onClick={addPoint}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
        >
          + Add Point
        </button>
        {coordinates.length > 0 && (
          <button
            type="button"
            onClick={clearAll}
            className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
          >
            Clear All
          </button>
        )}
      </div>

      {validCount >= 3 && (
        <p className="mt-2 text-xs text-green-600">
          {validCount} valid point(s) — polygon will be output in schema.
        </p>
      )}
    </div>
  );
}
