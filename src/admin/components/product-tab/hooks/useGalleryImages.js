import { useState, useEffect, useCallback } from "react";
import { get, post } from "../../../api";

/**
 * Fetches product gallery images and their alt-text status.
 * @param {number|string} postId
 * @returns {{ images: Array, total: number, missingAlt: number, loading: boolean, refresh: Function, bulkGenerate: Function, generating: boolean, error: string|null }}
 */
export default function useGalleryImages(postId, initialGalleryStr) {
  const [images, setImages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState(null);

  const fetchImages = useCallback(async () => {
    if (!postId) return;
    setLoading(true);
    setError(null);

    let galleryStr = initialGalleryStr || "";
    if (!galleryStr) {
      try {
        const data = await get(`/post-meta/${postId}`);
        galleryStr = data._product_image_gallery || "";
      } catch (e) {
        setError(e.message);
        setLoading(false);
        return;
      }
    }

    const ids = galleryStr
      .split(",")
      .map((s) => s.trim())
      .filter(Boolean)
      .map(Number);

    if (ids.length === 0) {
      setImages([]);
      setLoading(false);
      return;
    }

    try {
      // Fetch attachment data from WP REST media endpoint
      const nonce = window.novaToolsSEO?.nonce || "";
      const headers = nonce ? { "X-WP-Nonce": nonce } : {};
      const mediaRes = await fetch(
        `/wp-json/wp/v2/media?include=${ids.join(",")}&per_page=${ids.length}`,
        { headers },
      );

      if (mediaRes.ok) {
        const mediaData = await mediaRes.json();
        const map = {};
        for (const m of mediaData) {
          map[m.id] = {
            id: m.id,
            title: m.title?.rendered || "",
            alt: m.alt_text || "",
            sourceUrl: m.media_details?.sizes?.thumbnail?.source_url || m.source_url || "",
          };
        }
        const ordered = ids.map((id) => map[id] || { id, title: "", alt: "", sourceUrl: "" });
        setImages(ordered);
      } else {
        // Fallback: use wp.data media store if available
        if (window.wp?.data?.select("core")) {
          const ordered = ids.map((id) => {
            const media = window.wp.data.select("core").getMedia(id);
            return {
              id,
              title: media?.title?.rendered || "",
              alt: media?.alt_text || "",
              sourceUrl: media?.source_url || "",
            };
          });
          setImages(ordered);
        } else {
          setImages(ids.map((id) => ({ id, title: "", alt: "", sourceUrl: "" })));
        }
      }
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [postId, initialGalleryStr]);

  const bulkGenerate = useCallback(async () => {
    if (!postId) return;
    setGenerating(true);
    setError(null);
    try {
      const result = await post(`/products/${postId}/bulk-alt-text`, {});
      if (result.images) {
        setImages(result.images);
      } else {
        await fetchImages();
      }
      return result;
    } catch (e) {
      setError(e.message);
      throw e;
    } finally {
      setGenerating(false);
    }
  }, [postId, fetchImages]);

  useEffect(() => {
    fetchImages();
  }, [fetchImages]);

  const total = images.length;
  const missingAlt = images.filter((img) => !img.alt || img.alt.trim() === "").length;

  return { images, total, missingAlt, loading, refresh: fetchImages, bulkGenerate, generating, error };
}
