const BASE =
  (window.novaToolsSEO?.apiUrl || "/wp-json/") + "novatools-seo/v1";

function headers() {
  const h = { "Content-Type": "application/json" };
  if (window.novaToolsSEO?.nonce) {
    h["X-WP-Nonce"] = window.novaToolsSEO.nonce;
  }
  return h;
}

export async function get(path) {
  const res = await fetch(BASE + path, { headers: headers() });
  if (!res.ok) throw new Error("API error: " + res.status);
  return res.json();
}

export async function post(path, body) {
  const res = await fetch(BASE + path, {
    method: "POST",
    headers: headers(),
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error("API error: " + res.status);
  return res.json();
}

export async function del(path) {
  const res = await fetch(BASE + path, {
    method: "DELETE",
    headers: headers(),
  });
  if (!res.ok) throw new Error("API error: " + res.status);
  return res.json();
}
