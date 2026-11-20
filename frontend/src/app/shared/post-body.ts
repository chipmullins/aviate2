/**
 * Turns post text into pieces Angular can render safely. We never use innerHTML:
 * text stays text, and only http(s) links become <a> tags.
 */
export type Segment = { kind: 'text'; text: string } | { kind: 'link'; text: string; href: string };

const URL_RE = /\bhttps?:\/\/[^\s<>"']+/gi;
const TRAILING_PUNCT = /[.,!?;:)\]]+$/;

export function parseBody(text: string): Segment[] {
  const out: Segment[] = [];
  let last = 0;
  for (const match of text.matchAll(URL_RE)) {
    let url = match[0];
    const trail = url.match(TRAILING_PUNCT)?.[0] ?? '';
    if (trail) url = url.slice(0, -trail.length);

    const start = match.index ?? 0;
    if (start > last) out.push({ kind: 'text', text: text.slice(last, start) });

    const safe = safeHttpUrl(url);
    out.push(safe ? { kind: 'link', text: url, href: safe } : { kind: 'text', text: url });
    last = start + url.length;
  }
  if (last < text.length) out.push({ kind: 'text', text: text.slice(last) });
  return out;
}

export function safeHttpUrl(raw: string): string | null {
  try {
    const u = new URL(raw);
    return u.protocol === 'http:' || u.protocol === 'https:' ? u.href : null;
  } catch {
    return null;
  }
}

/**
 * Finds the first YouTube link in the text and returns its 11-character video ID.
 * Only the ID is used to build the embed URL, so nothing from the post itself
 * ever reaches the iframe's src.
 */
export function youtubeId(text: string): string | null {
  for (const match of text.matchAll(URL_RE)) {
    const href = safeHttpUrl(match[0].replace(TRAILING_PUNCT, ''));
    if (!href) continue;
    const u = new URL(href);
    const host = u.hostname.replace(/^www\.|^m\./, '');
    let id: string | null = null;
    if (host === 'youtube.com') {
      id = u.pathname === '/watch' ? u.searchParams.get('v') : (u.pathname.match(/^\/(?:shorts|embed)\/([^/]+)/)?.[1] ?? null);
    } else if (host === 'youtu.be') {
      id = u.pathname.slice(1).split('/')[0] || null;
    }
    if (id && /^[A-Za-z0-9_-]{11}$/.test(id)) return id;
  }
  return null;
}
