#!/usr/bin/env node

import { readdir, readFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const SCRIPT_DIRECTORY = path.dirname(fileURLToPath(import.meta.url));
const DEFAULT_DOCS_DIRECTORY = path.resolve(SCRIPT_DIRECTORY, "..");
const SOURCE_MARKER = "superfunky-doc-source";
const PAGE_STATUSES = new Set(["draft", "publish", "pending", "private"]);
const MANAGED_STATUSES = ["publish", "future", "draft", "pending", "private"];

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function slugify(value) {
  return String(value)
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/&/g, " and ")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "") || "page";
}

function sourceMarker(source) {
  return `<!-- ${SOURCE_MARKER}: ${source} -->`;
}

function sourceFromContent(content) {
  const match = String(content ?? "").match(
    new RegExp(`<!--\\s*${SOURCE_MARKER}:\\s*([^>]+?)\\s*-->`),
  );
  return match?.[1]?.trim() ?? null;
}

function plainText(value) {
  return String(value)
    .replace(/<[^>]*>/g, "")
    .replaceAll("&amp;", "&")
    .replaceAll("&lt;", "<")
    .replaceAll("&gt;", ">")
    .replaceAll("&quot;", '"')
    .replaceAll("&#039;", "'");
}

function firstHeading(markdown, fallback) {
  return markdown.match(/^#\s+(.+)$/m)?.[1]?.trim() || fallback;
}

function markdownLinks(markdown) {
  return [...markdown.matchAll(/!?\[[^\]]*]\(([^)\s]+)(?:\s+"[^"]*")?\)/g)]
    .map((match) => match[1])
    .filter(Boolean);
}

function normalizeSource(source) {
  return source.split(path.sep).join("/");
}

function resolveSourceLink(source, href) {
  if (
    !href ||
    href.startsWith("#") ||
    href.startsWith("/") ||
    /^[a-z][a-z0-9+.-]*:/i.test(href)
  ) {
    return null;
  }
  const [target] = href.split("#");
  if (!target.toLowerCase().endsWith(".md")) return null;
  return normalizeSource(path.posix.normalize(path.posix.join(path.posix.dirname(source), target)));
}

function renderInline(value, resolveLink) {
  const tokens = [];
  const reserve = (html) => {
    const token = `\u0000${tokens.length}\u0000`;
    tokens.push(html);
    return token;
  };

  let text = String(value);
  text = text.replace(/`([^`\n]+)`/g, (_, code) => reserve(`<code>${escapeHtml(code)}</code>`));
  text = text.replace(
    /\[([^\]]+)]\(([^)\s]+)(?:\s+"([^"]*)")?\)/g,
    (_, label, href, title) => {
      const resolvedHref = resolveLink?.(href) || href;
      const titleAttribute = title ? ` title="${escapeHtml(title)}"` : "";
      return reserve(
        `<a href="${escapeHtml(resolvedHref)}"${titleAttribute}>${renderInline(label)}</a>`,
      );
    },
  );
  text = escapeHtml(text)
    .replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>")
    .replace(/__([^_]+)__/g, "<strong>$1</strong>")
    .replace(/(^|[\s(])\*([^*\n]+)\*/g, "$1<em>$2</em>")
    .replace(/(^|[\s(])_([^_\n]+)_/g, "$1<em>$2</em>");
  return text.replace(/\u0000(\d+)\u0000/g, (_, index) => tokens[Number(index)]);
}

function listMatch(line) {
  const match = line.match(/^(\s*)([-+*]|\d+\.)\s+(.+)$/);
  if (!match) return null;
  return {
    indent: match[1].replaceAll("\t", "  ").length,
    ordered: /^\d/.test(match[2]),
    content: match[3],
  };
}

function renderList(lines, start, resolveLink, indent = null) {
  const first = listMatch(lines[start]);
  const level = indent ?? first.indent;
  const ordered = first.ordered;
  const tag = ordered ? "ol" : "ul";
  let index = start;
  let html = `<${tag}>`;
  let itemOpen = false;

  while (index < lines.length) {
    const item = listMatch(lines[index]);
    if (!item || item.indent < level) break;
    if (item.indent > level) {
      if (!itemOpen) break;
      const nested = renderList(lines, index, resolveLink, item.indent);
      html += nested.html;
      index = nested.index;
      continue;
    }
    if (item.ordered !== ordered) break;
    if (itemOpen) html += "</li>";
    html += `<li>${renderInline(item.content, resolveLink)}`;
    itemOpen = true;
    index += 1;
  }

  if (itemOpen) html += "</li>";
  return { html: `${html}</${tag}>`, index };
}

function tableCells(line) {
  return line
    .trim()
    .replace(/^\||\|$/g, "")
    .split("|")
    .map((cell) => cell.trim());
}

function isTableSeparator(line) {
  const cells = tableCells(line);
  return cells.length > 0 && cells.every((cell) => /^:?-{3,}:?$/.test(cell));
}

export function markdownToHtml(markdown, { source = "README.md", title, linkMap = new Map() } = {}) {
  const lines = String(markdown).replaceAll("\r\n", "\n").split("\n");
  const rendered = [];
  const headingIds = new Map();
  let index = 0;
  let skippedTitle = false;

  const resolveLink = (href) => {
    const resolvedSource = resolveSourceLink(source, href);
    if (!resolvedSource || !linkMap.has(resolvedSource)) return href;
    const fragment = href.includes("#") ? `#${href.split("#").slice(1).join("#")}` : "";
    return `${linkMap.get(resolvedSource)}${fragment}`;
  };

  while (index < lines.length) {
    const line = lines[index];
    if (!line.trim()) {
      index += 1;
      continue;
    }

    const fence = line.match(/^```([\w-]*)\s*$/);
    if (fence) {
      const language = fence[1];
      const code = [];
      index += 1;
      while (index < lines.length && !/^```\s*$/.test(lines[index])) {
        code.push(lines[index]);
        index += 1;
      }
      if (index === lines.length) throw new Error(`${source}: unclosed code fence`);
      index += 1;
      const className = language ? ` class="language-${escapeHtml(language)}"` : "";
      rendered.push(`<pre><code${className}>${escapeHtml(code.join("\n"))}</code></pre>`);
      continue;
    }

    const heading = line.match(/^(#{1,6})\s+(.+)$/);
    if (heading) {
      const level = heading[1].length;
      const text = heading[2].trim();
      index += 1;
      if (level === 1 && !skippedTitle && (!title || text === title)) {
        skippedTitle = true;
        continue;
      }
      const baseId = slugify(text);
      const seen = headingIds.get(baseId) || 0;
      headingIds.set(baseId, seen + 1);
      const id = seen ? `${baseId}-${seen + 1}` : baseId;
      rendered.push(`<h${level} id="${id}">${renderInline(text, resolveLink)}</h${level}>`);
      continue;
    }

    if (/^\s*([-*_])(?:\s*\1){2,}\s*$/.test(line)) {
      rendered.push("<hr>");
      index += 1;
      continue;
    }

    if (line.startsWith(">")) {
      const quote = [];
      while (index < lines.length && lines[index].startsWith(">")) {
        quote.push(lines[index].replace(/^>\s?/, "").trim());
        index += 1;
      }
      rendered.push(`<blockquote><p>${renderInline(quote.join(" "), resolveLink)}</p></blockquote>`);
      continue;
    }

    if (listMatch(line)) {
      const list = renderList(lines, index, resolveLink);
      rendered.push(list.html);
      index = list.index;
      continue;
    }

    if (index + 1 < lines.length && line.includes("|") && isTableSeparator(lines[index + 1])) {
      const headers = tableCells(line);
      index += 2;
      const rows = [];
      while (index < lines.length && lines[index].includes("|") && lines[index].trim()) {
        rows.push(tableCells(lines[index]));
        index += 1;
      }
      rendered.push(
        `<figure class="wp-block-table"><table><thead><tr>${headers
          .map((cell) => `<th>${renderInline(cell, resolveLink)}</th>`)
          .join("")}</tr></thead><tbody>${rows
          .map(
            (row) =>
              `<tr>${headers
                .map((_, cellIndex) => `<td>${renderInline(row[cellIndex] || "", resolveLink)}</td>`)
                .join("")}</tr>`,
          )
          .join("")}</tbody></table></figure>`,
      );
      continue;
    }

    const paragraph = [line.trim()];
    index += 1;
    while (
      index < lines.length &&
      lines[index].trim() &&
      !/^```/.test(lines[index]) &&
      !/^(#{1,6})\s+/.test(lines[index]) &&
      !lines[index].startsWith(">") &&
      !listMatch(lines[index]) &&
      !/^\s*([-*_])(?:\s*\1){2,}\s*$/.test(lines[index]) &&
      !(index + 1 < lines.length && lines[index].includes("|") && isTableSeparator(lines[index + 1]))
    ) {
      paragraph.push(lines[index].trim());
      index += 1;
    }
    rendered.push(`<p>${renderInline(paragraph.join(" "), resolveLink)}</p>`);
  }

  return `${sourceMarker(source)}\n${rendered.join("\n")}`;
}

function contentLink(value) {
  try {
    const url = new URL(value);
    return url.search ? value : `${url.pathname}${url.hash}`;
  } catch {
    return value;
  }
}

function navigationItem(document, currentSource, linkMap) {
  const active = document.source === currentSource;
  const linkClass = active
    ? "flex rounded-xl bg-brand-50 px-3 py-2 font-semibold text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-300 dark:ring-brand-800"
    : "flex rounded-xl px-3 py-2 text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-400 dark:hover:bg-zinc-800/80 dark:hover:text-white";
  const children = document.children.length
    ? `<ul class="m-0 grid list-none gap-0.5 border-l border-zinc-200 pl-3 dark:border-zinc-800">${document.children
        .map((child) => navigationItem(child, currentSource, linkMap))
        .join("")}</ul>`
    : "";
  return `<li class="m-0 grid gap-0.5"><a href="${escapeHtml(linkMap.get(document.source))}" class="${linkClass}"${active ? ' aria-current="page"' : ""}>${escapeHtml(document.title)}</a>${children}</li>`;
}

function navigationContent(documents, currentSource, linkMap) {
  const root = documents[0];
  return `<nav aria-label="Superfunky documentation" class="grid gap-4 text-sm">
  <a href="${escapeHtml(linkMap.get(root.source))}" class="flex items-center gap-3 rounded-2xl bg-zinc-950 px-4 py-3 font-semibold text-white no-underline shadow-sm dark:bg-white dark:text-zinc-950"${root.source === currentSource ? ' aria-current="page"' : ""}>
    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-500 text-sm font-black text-white" aria-hidden="true">S</span>
    <span>Superfunky docs</span>
  </a>
  <ul class="m-0 grid list-none gap-3 p-0">${root.children
    .map((child) => navigationItem(child, currentSource, linkMap))
    .join("")}</ul>
</nav>`;
}

function tableOfContents(articleHtml) {
  return [...articleHtml.matchAll(/<h([23])\s+id="([^"]+)">([\s\S]*?)<\/h\1>/g)].map(
    (match) => ({
      level: Number(match[1]),
      id: match[2],
      label: plainText(match[3]),
    }),
  );
}

function tocContent(entries) {
  if (!entries.length) return "";
  return `<aside aria-label="On this page" class="fixed right-4 top-1/2 z-30 hidden -translate-y-1/2 xl:flex">
  <span class="absolute bottom-2 left-1/2 top-2 w-px -translate-x-1/2 bg-zinc-200 dark:bg-zinc-800" aria-hidden="true"></span>
  <ol class="relative m-0 grid list-none gap-3 p-0">${entries
    .map(
      ({ level, id, label }, index) =>
        `<li class="m-0 flex justify-end${level === 3 ? " pr-0.5" : ""}"><a href="#${escapeHtml(id)}" data-doc-toc-link data-active="${index === 0 ? "true" : "false"}" class="group relative flex h-4 w-4 items-center justify-center rounded-full outline-none" aria-label="${escapeHtml(label)}"${index === 0 ? ' aria-current="location"' : ""}>
      <span class="pointer-events-none absolute right-6 whitespace-nowrap rounded-lg bg-zinc-950 px-2 py-1 text-xs font-semibold text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-visible:opacity-100 dark:bg-white dark:text-zinc-950">${escapeHtml(label)}</span>
      <span class="${level === 3 ? "h-1.5 w-1.5" : "h-2.5 w-2.5"} rounded-full bg-zinc-300 ring-4 ring-white transition group-hover:bg-brand-500 group-focus-visible:bg-brand-500 group-data-[active=true]:bg-brand-600 group-data-[active=true]:ring-brand-100 dark:bg-zinc-700 dark:ring-zinc-950 dark:group-data-[active=true]:bg-brand-400 dark:group-data-[active=true]:ring-brand-950" aria-hidden="true"></span>
    </a></li>`,
    )
    .join("")}</ol>
</aside>`;
}

const DOCS_TOC_SCRIPT = `<script data-superfunky-docs-script>
(() => {
  window.__superfunkyDocumentationTocCleanup?.();
  const root = document.currentScript?.closest("[data-superfunky-docs-page]");
  if (!root) return;
  const links = Array.from(root.querySelectorAll("[data-doc-toc-link]"));
  const headings = links.map((link) => root.querySelector(link.hash)).filter(Boolean);
  if (!headings.length) return;
  const setActive = (id) => links.forEach((link) => {
    const active = link.hash === "#" + id;
    link.dataset.active = String(active);
    if (active) link.setAttribute("aria-current", "location");
    else link.removeAttribute("aria-current");
  });
  const update = () => {
    const threshold = Math.max(112, window.innerHeight * 0.24);
    let active = headings[0];
    for (const heading of headings) {
      if (heading.getBoundingClientRect().top <= threshold) active = heading;
      else break;
    }
    setActive(active.id);
  };
  const observer = new IntersectionObserver(update, { rootMargin: "-15% 0px -70% 0px" });
  headings.forEach((heading) => observer.observe(heading));
  window.addEventListener("hashchange", update);
  window.__superfunkyDocumentationTocCleanup = () => {
    observer.disconnect();
    window.removeEventListener("hashchange", update);
  };
  update();
})();
</script>`;

function sectionTitle(document, documentsBySource) {
  let section = document;
  while (section.parentSource && section.parentSource !== "README.md") {
    section = documentsBySource.get(section.parentSource);
  }
  return section.source === "README.md" ? "Overview" : section.title;
}

export function renderDocumentationPage(document, documents, linkMap) {
  const articleHtml = markdownToHtml(document.markdown, {
    source: document.source,
    title: document.title,
    linkMap,
  });
  const marker = sourceMarker(document.source);
  const article = articleHtml.replace(marker, "").trim();
  const navigation = navigationContent(documents, document.source, linkMap);
  const toc = tocContent(tableOfContents(article));
  const documentsBySource = new Map(documents.map((record) => [record.source, record]));

  return `<!-- wp:html -->
${marker}
<div data-superfunky-docs-page="${escapeHtml(document.source)}" class="relative mx-auto w-full max-w-[1600px]">
  <details class="group mb-6 rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:hidden">
    <summary class="flex cursor-pointer list-none items-center justify-between rounded-xl px-3 py-2 font-semibold text-zinc-950 marker:hidden dark:text-white">Browse documentation<span class="text-zinc-400 transition group-open:rotate-180" aria-hidden="true">&#8964;</span></summary>
    <div class="max-h-[70vh] overflow-y-auto px-1 pb-1 pt-3">${navigation}</div>
  </details>
  <div class="grid items-start gap-8 lg:grid-cols-[18rem_minmax(0,1fr)] xl:gap-12">
    <aside class="sticky top-24 hidden max-h-[calc(100vh-7rem)] overflow-y-auto pr-2 lg:block">${navigation}</aside>
    <main class="min-w-0">
      <header class="mb-10 border-b border-zinc-200 pb-8 dark:border-zinc-800">
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">Documentation / ${escapeHtml(sectionTitle(document, documentsBySource))}</p>
        <h1 class="m-0 text-3xl font-black tracking-tight text-zinc-950 dark:text-white sm:text-4xl">${escapeHtml(document.title)}</h1>
      </header>
      <div data-doc-article class="grid gap-5 text-base leading-7 text-zinc-700 dark:text-zinc-300 [&_a]:font-semibold [&_a]:text-brand-600 [&_a]:underline-offset-4 hover:[&_a]:underline dark:[&_a]:text-brand-400 [&_blockquote]:m-0 [&_blockquote]:rounded-r-2xl [&_blockquote]:border-l-4 [&_blockquote]:border-brand-500 [&_blockquote]:bg-brand-50/60 [&_blockquote]:px-5 [&_blockquote]:py-4 dark:[&_blockquote]:bg-brand-950/20 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-sm dark:[&_code]:bg-zinc-800 [&_figure]:m-0 [&_h2]:mb-0 [&_h2]:mt-8 [&_h2]:scroll-mt-28 [&_h2]:border-b [&_h2]:border-zinc-200 [&_h2]:pb-3 [&_h2]:text-2xl [&_h2]:font-black [&_h2]:tracking-tight [&_h2]:text-zinc-950 dark:[&_h2]:border-zinc-800 dark:[&_h2]:text-white [&_h3]:mb-0 [&_h3]:mt-6 [&_h3]:scroll-mt-28 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:text-zinc-950 dark:[&_h3]:text-white [&_li]:my-1 [&_ol]:m-0 [&_ol]:pl-6 [&_p]:m-0 [&_pre]:overflow-x-auto [&_pre]:rounded-2xl [&_pre]:bg-zinc-950 [&_pre]:p-5 [&_pre]:text-zinc-100 [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_strong]:text-zinc-950 dark:[&_strong]:text-white [&_table]:w-full [&_table]:border-collapse [&_td]:border-b [&_td]:border-zinc-200 [&_td]:p-3 [&_td]:align-top dark:[&_td]:border-zinc-800 [&_th]:border-b [&_th]:border-zinc-300 [&_th]:p-3 [&_th]:text-left [&_th]:text-zinc-950 dark:[&_th]:border-zinc-700 dark:[&_th]:text-white [&_ul]:m-0 [&_ul]:pl-6">${article}</div>
    </main>
  </div>
  ${toc}
  ${DOCS_TOC_SCRIPT}
</div>
<!-- /wp:html -->`;
}

async function markdownFiles(directory, baseDirectory = directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];
  for (const entry of entries.sort((left, right) => left.name.localeCompare(right.name))) {
    if (entry.name.startsWith(".") || entry.name === "scripts") continue;
    const absolutePath = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      files.push(...(await markdownFiles(absolutePath, baseDirectory)));
    } else if (entry.isFile() && entry.name.toLowerCase().endsWith(".md")) {
      files.push(normalizeSource(path.relative(baseDirectory, absolutePath)));
    }
  }
  return files;
}

function parentSource(source, sourceSet) {
  if (source === "README.md") return null;
  const directory = path.posix.dirname(source);
  const localReadme = directory === "." ? "README.md" : `${directory}/README.md`;
  if (source !== localReadme && sourceSet.has(localReadme)) return localReadme;

  let ancestor = directory;
  while (ancestor !== ".") {
    ancestor = path.posix.dirname(ancestor);
    const readme = ancestor === "." ? "README.md" : `${ancestor}/README.md`;
    if (sourceSet.has(readme)) return readme;
  }
  return sourceSet.has("README.md") ? "README.md" : null;
}

function documentSlug(source, rootSlug) {
  if (source === "README.md") return slugify(rootSlug);
  if (path.posix.basename(source).toLowerCase() === "readme.md") {
    return slugify(path.posix.basename(path.posix.dirname(source)));
  }
  return slugify(path.posix.basename(source, path.posix.extname(source)));
}

export async function loadDocuments(docsDirectory, { rootSlug = "documentation" } = {}) {
  const sources = await markdownFiles(docsDirectory);
  if (!sources.includes("README.md")) {
    throw new Error(`Documentation root has no README.md: ${docsDirectory}`);
  }

  const sourceSet = new Set(sources);
  const records = new Map();
  for (const source of sources) {
    const markdown = await readFile(path.join(docsDirectory, source), "utf8");
    records.set(source, {
      source,
      markdown,
      title: firstHeading(markdown, documentSlug(source, rootSlug)),
      slug: documentSlug(source, rootSlug),
      parentSource: parentSource(source, sourceSet),
      links: markdownLinks(markdown),
      children: [],
      order: 0,
    });
  }

  for (const record of records.values()) {
    if (!record.parentSource) continue;
    const parent = records.get(record.parentSource);
    if (!parent) throw new Error(`${record.source}: parent ${record.parentSource} is missing`);
    parent.children.push(record);
  }

  for (const parent of records.values()) {
    const linkedOrder = new Map();
    let position = 0;
    for (const href of parent.links) {
      const target = resolveSourceLink(parent.source, href);
      if (target && !linkedOrder.has(target)) linkedOrder.set(target, position++);
    }
    parent.children.sort((left, right) => {
      const leftOrder = linkedOrder.get(left.source) ?? Number.MAX_SAFE_INTEGER;
      const rightOrder = linkedOrder.get(right.source) ?? Number.MAX_SAFE_INTEGER;
      return leftOrder - rightOrder || left.title.localeCompare(right.title);
    });
    parent.children.forEach((child, childIndex) => {
      child.order = childIndex;
    });
  }

  const root = records.get("README.md");
  const flattened = [];
  const visit = (record, depth) => {
    record.depth = depth;
    flattened.push(record);
    record.children.forEach((child) => visit(child, depth + 1));
  };
  visit(root, 0);

  if (flattened.length !== records.size) {
    const unreachable = [...records.keys()].filter(
      (source) => !flattened.some((record) => record.source === source),
    );
    throw new Error(`Documentation files are outside the README tree: ${unreachable.join(", ")}`);
  }
  return flattened;
}

class WordPressClient {
  constructor({ wpUrl, username, appPassword, fetchImpl = fetch }) {
    const base = new URL(wpUrl);
    const loopback = new Set(["localhost", "127.0.0.1", "::1"]).has(base.hostname);
    if (base.protocol !== "https:" && !loopback) {
      throw new Error("WordPress must use HTTPS when sending an Application Password.");
    }
    base.pathname = `${base.pathname.replace(/\/+$/, "")}/wp-json/wp/v2/`;
    this.baseUrl = base;
    this.authorization = `Basic ${Buffer.from(
      `${username}:${appPassword.replace(/\s+/g, "")}`,
    ).toString("base64")}`;
    this.fetch = fetchImpl;
  }

  async request(relativePath, { method = "GET", body } = {}) {
    const url = new URL(relativePath, this.baseUrl);
    const response = await this.fetch(url, {
      method,
      headers: {
        Accept: "application/json",
        Authorization: this.authorization,
        ...(body ? { "Content-Type": "application/json" } : {}),
      },
      ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok) {
      const message = payload?.message || response.statusText || "Unknown WordPress error";
      throw new Error(`${method} ${url.pathname} failed (${response.status}): ${message}`);
    }
    return { payload, headers: response.headers };
  }

  async listPages() {
    const pages = [];
    let page = 1;
    while (true) {
      const query = new URLSearchParams({
        context: "edit",
        per_page: "100",
        page: String(page),
        orderby: "id",
        order: "asc",
      });
      for (const status of MANAGED_STATUSES) query.append("status[]", status);
      const response = await this.request(`pages?${query}`);
      pages.push(...response.payload);
      const totalPages = Number(response.headers.get("x-wp-totalpages") || "1");
      if (page >= totalPages) break;
      page += 1;
    }
    return pages;
  }

  async getPage(id) {
    return (await this.request(`pages/${id}?context=edit`)).payload;
  }

  async createPage(body) {
    return (await this.request("pages", { method: "POST", body })).payload;
  }

  async updatePage(id, body) {
    return (await this.request(`pages/${id}`, { method: "POST", body })).payload;
  }
}

function pageContent(page) {
  return page?.content?.raw ?? page?.content?.rendered ?? "";
}

function pageTitle(page) {
  return page?.title?.raw ?? page?.title?.rendered ?? "";
}

function pageStructureBody(document, parentId, status, { content } = {}) {
  return {
    title: document.title,
    slug: document.slug,
    parent: parentId,
    menu_order: document.order,
    ...(status ? { status } : {}),
    ...(content === undefined ? {} : { content }),
  };
}

function printTree(documents, logger) {
  for (const document of documents) {
    logger(`${"  ".repeat(document.depth)}- ${document.title} [${document.slug}] <- ${document.source}`);
  }
}

function previewLinkMap(documents) {
  const paths = new Map();
  for (const document of documents) {
    const parentPath = document.parentSource ? paths.get(document.parentSource) : "";
    paths.set(document.source, `${parentPath}/${document.slug}`.replace(/\/+/g, "/"));
  }
  return new Map([...paths].map(([source, pagePath]) => [source, `${pagePath}/`]));
}

export async function publishDocumentation({
  docsDirectory = DEFAULT_DOCS_DIRECTORY,
  wpUrl,
  username,
  appPassword,
  status = null,
  rootSlug = "documentation",
  rootPageId = null,
  dryRun = false,
  fetchImpl = fetch,
  logger = console.log,
} = {}) {
  if (status && !PAGE_STATUSES.has(status)) {
    throw new Error(`Unsupported page status "${status}". Use draft, publish, pending, or private.`);
  }
  const documents = await loadDocuments(docsDirectory, { rootSlug });
  const newPageStatus = status || "draft";
  if (dryRun) {
    const linkMap = previewLinkMap(documents);
    for (const document of documents) {
      renderDocumentationPage(document, documents, linkMap);
    }
    const statusDescription = status || "new pages draft; existing pages unchanged";
    logger(`WordPress documentation tree (${documents.length} pages, status: ${statusDescription}):`);
    printTree(documents, logger);
    return { created: 0, updated: 0, documents };
  }
  if (!wpUrl || !username || !appPassword) {
    throw new Error(
      "SUPERFUNKY_WP_URL, SUPERFUNKY_WP_USERNAME, and SUPERFUNKY_WP_APP_PASSWORD are required.",
    );
  }

  const client = new WordPressClient({ wpUrl, username, appPassword, fetchImpl });
  const existingPages = await client.listPages();
  const bySource = new Map();
  for (const page of existingPages) {
    const source = sourceFromContent(pageContent(page));
    if (!source) continue;
    if (bySource.has(source)) {
      throw new Error(
        `WordPress pages ${bySource.get(source).id} and ${page.id} both manage ${source}.`,
      );
    }
    bySource.set(source, page);
  }

  if (rootPageId) {
    const adoptedRoot =
      existingPages.find((page) => page.id === Number(rootPageId)) ||
      (await client.getPage(Number(rootPageId)));
    const managedRoot = bySource.get("README.md");
    if (managedRoot && managedRoot.id !== adoptedRoot.id) {
      throw new Error(
        `Page ${managedRoot.id} already manages README.md; refusing to adopt page ${rootPageId}.`,
      );
    }
    const markedSource = sourceFromContent(pageContent(adoptedRoot));
    if (markedSource && markedSource !== "README.md") {
      throw new Error(`Root page ${rootPageId} already manages ${markedSource}.`);
    }
    bySource.set("README.md", adoptedRoot);
  }

  let created = 0;
  let updated = 0;
  const publishedPages = new Map();

  for (const document of documents) {
    const parentId = document.parentSource
      ? publishedPages.get(document.parentSource)?.id
      : bySource.get("README.md")?.parent || 0;
    if (document.parentSource && !parentId) {
      throw new Error(`${document.source}: WordPress parent was not created.`);
    }

    const existing = bySource.get(document.source);
    if (existing) {
      const updatedPage = await client.updatePage(
        existing.id,
        pageStructureBody(document, parentId, status),
      );
      publishedPages.set(document.source, updatedPage);
      updated += 1;
      logger(`Updated structure: ${document.source} -> page ${updatedPage.id}`);
    } else {
      const initialContent = renderDocumentationPage(
        document,
        documents,
        previewLinkMap(documents),
      );
      const createdPage = await client.createPage(
        pageStructureBody(document, parentId, newPageStatus, { content: initialContent }),
      );
      publishedPages.set(document.source, createdPage);
      created += 1;
      logger(`Created: ${document.source} -> page ${createdPage.id}`);
    }
  }

  const linkMap = new Map(
    [...publishedPages].map(([source, page]) => [source, contentLink(page.link)]),
  );
  for (const document of documents) {
    const page = publishedPages.get(document.source);
    const content = renderDocumentationPage(document, documents, linkMap);
    const finalPage = await client.updatePage(page.id, { content });
    publishedPages.set(document.source, finalPage);
    logger(`Updated content: ${document.source} -> ${finalPage.link}`);
  }

  logger(
    `Processed ${documents.length} documentation pages: ${created} created as ${newPageStatus}, ${updated} updated${status ? ` as ${status}` : " with status preserved"}.`,
  );
  return { created, updated, documents, pages: publishedPages };
}

function optionValue(argumentsList, name) {
  const prefix = `--${name}=`;
  const inline = argumentsList.find((argument) => argument.startsWith(prefix));
  if (inline) return inline.slice(prefix.length);
  const index = argumentsList.indexOf(`--${name}`);
  return index >= 0 ? argumentsList[index + 1] : undefined;
}

function hasFlag(argumentsList, name) {
  return argumentsList.includes(`--${name}`);
}

function help() {
  return `Publish Superfunky Markdown documentation as a WordPress page tree.

Usage:
  node docs/scripts/publish-wordpress-docs.mjs --dry-run
  node docs/scripts/publish-wordpress-docs.mjs [options]

Options:
  --wp-url URL             WordPress site URL
  --username USER          WordPress Application Password user
  --status STATUS          Apply draft, publish, pending, or private to all pages
  --root-slug SLUG         Root page slug (default: documentation)
  --root-page-id ID        Adopt an existing root page
  --docs-dir PATH          Documentation directory
  --dry-run                Print the local page tree without connecting
  --help                   Show this help

The same values, except docs-dir and dry-run, can be provided through the
SUPERFUNKY_WP_URL, SUPERFUNKY_WP_USERNAME, SUPERFUNKY_WP_APP_PASSWORD,
SUPERFUNKY_WP_STATUS, SUPERFUNKY_WP_ROOT_SLUG, and SUPERFUNKY_WP_ROOT_PAGE_ID
environment variables.`;
}

export function parseOptions(argumentsList, environment = process.env) {
  const rootPageValue =
    optionValue(argumentsList, "root-page-id") || environment.SUPERFUNKY_WP_ROOT_PAGE_ID;
  const rootPageId = rootPageValue ? Number(rootPageValue) : null;
  if (rootPageValue && (!Number.isInteger(rootPageId) || rootPageId < 1)) {
    throw new Error("Root page ID must be a positive integer.");
  }
  return {
    wpUrl: optionValue(argumentsList, "wp-url") || environment.SUPERFUNKY_WP_URL,
    username: optionValue(argumentsList, "username") || environment.SUPERFUNKY_WP_USERNAME,
    appPassword: environment.SUPERFUNKY_WP_APP_PASSWORD,
    status: optionValue(argumentsList, "status") || environment.SUPERFUNKY_WP_STATUS || null,
    rootSlug:
      optionValue(argumentsList, "root-slug") ||
      environment.SUPERFUNKY_WP_ROOT_SLUG ||
      "documentation",
    rootPageId,
    docsDirectory: path.resolve(
      optionValue(argumentsList, "docs-dir") || DEFAULT_DOCS_DIRECTORY,
    ),
    dryRun: hasFlag(argumentsList, "dry-run"),
  };
}

async function main() {
  const argumentsList = process.argv.slice(2);
  if (hasFlag(argumentsList, "help")) {
    console.log(help());
    return;
  }
  await publishDocumentation(parseOptions(argumentsList));
}

if (import.meta.url === pathToFileURL(process.argv[1] || "").href) {
  main().catch((error) => {
    console.error(`Documentation publication failed: ${error.message}`);
    process.exitCode = 1;
  });
}
