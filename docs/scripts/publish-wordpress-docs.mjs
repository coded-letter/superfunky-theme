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
      markdownToHtml(document.markdown, {
        source: document.source,
        title: document.title,
        linkMap,
      });
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
      const initialContent = markdownToHtml(document.markdown, {
        source: document.source,
        title: document.title,
      });
      const createdPage = await client.createPage(
        pageStructureBody(document, parentId, newPageStatus, { content: initialContent }),
      );
      publishedPages.set(document.source, createdPage);
      created += 1;
      logger(`Created: ${document.source} -> page ${createdPage.id}`);
    }
  }

  const linkMap = new Map(
    [...publishedPages].map(([source, page]) => [source, page.link]),
  );
  for (const document of documents) {
    const page = publishedPages.get(document.source);
    const content = markdownToHtml(document.markdown, {
      source: document.source,
      title: document.title,
      linkMap,
    });
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
