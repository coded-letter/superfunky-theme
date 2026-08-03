import assert from "node:assert/strict";
import { mkdtemp, mkdir, rm, writeFile } from "node:fs/promises";
import http from "node:http";
import os from "node:os";
import path from "node:path";
import { afterEach, test } from "node:test";
import {
  loadDocuments,
  markdownToHtml,
  parseOptions,
  publishDocumentation,
  renderDocumentationPage,
} from "./publish-wordpress-docs.mjs";

const temporaryDirectories = [];
const servers = [];

afterEach(async () => {
  await Promise.all(servers.splice(0).map((server) => new Promise((resolve) => server.close(resolve))));
  await Promise.all(temporaryDirectories.splice(0).map((directory) => rm(directory, { recursive: true })));
});

async function createDocs() {
  const directory = await mkdtemp(path.join(os.tmpdir(), "superfunky-docs-"));
  temporaryDirectories.push(directory);
  await mkdir(path.join(directory, "setup"));
  await writeFile(
    path.join(directory, "README.md"),
    "# Documentation\n\n1. **Setup**\n   - [Setup overview](setup/README.md)\n   - [Install](setup/install.md)\n",
  );
  await writeFile(
    path.join(directory, "setup", "README.md"),
    "# Setup\n\nContinue with [Install](install.md).\n",
  );
  await writeFile(
    path.join(directory, "setup", "install.md"),
    "# Install\n\n## Requirements\n\n| Item | Value |\n|---|---|\n| Runtime | Node 22 |\n",
  );
  return directory;
}

function startWordPressServer() {
  const pages = [];
  let nextId = 1;
  const server = http.createServer(async (request, response) => {
    const url = new URL(request.url, "http://localhost");
    assert.equal(request.headers.authorization, `Basic ${Buffer.from("docs:secret").toString("base64")}`);

    if (request.method === "GET" && url.pathname === "/wp-json/wp/v2/pages") {
      response.setHeader("Content-Type", "application/json");
      response.setHeader("X-WP-TotalPages", "1");
      response.end(JSON.stringify(pages));
      return;
    }

    const body = await new Promise((resolve) => {
      let value = "";
      request.setEncoding("utf8");
      request.on("data", (chunk) => {
        value += chunk;
      });
      request.on("end", () => resolve(value ? JSON.parse(value) : {}));
    });
    const match = url.pathname.match(/^\/wp-json\/wp\/v2\/pages\/(\d+)$/);
    let page;
    if (request.method === "POST" && url.pathname === "/wp-json/wp/v2/pages") {
      page = { id: nextId++, parent: 0, content: { raw: "" }, title: { raw: "" } };
      pages.push(page);
      response.statusCode = 201;
    } else if (request.method === "POST" && match) {
      page = pages.find((candidate) => candidate.id === Number(match[1]));
    }
    if (!page) {
      response.statusCode = 404;
      response.end(JSON.stringify({ message: "Not found" }));
      return;
    }

    Object.assign(page, body);
    page.title = { raw: body.title ?? page.title.raw };
    page.content = { raw: body.content ?? page.content.raw };
    const parent = pages.find((candidate) => candidate.id === page.parent);
    const parentPath = parent ? new URL(parent.link).pathname : "/";
    page.link = new URL(
      `${parentPath.replace(/\/?$/, "/")}${page.slug}/`,
      `http://${request.headers.host}`,
    ).href;
    response.setHeader("Content-Type", "application/json");
    response.end(JSON.stringify(page));
  });
  servers.push(server);
  return new Promise((resolve) => {
    server.listen(0, "127.0.0.1", () => {
      const address = server.address();
      resolve({ url: `http://127.0.0.1:${address.port}`, pages });
    });
  });
}

test("loads the README hierarchy in navigation order", async () => {
  const docsDirectory = await createDocs();
  const documents = await loadDocuments(docsDirectory);
  assert.deepEqual(
    documents.map(({ source, parentSource, order }) => ({ source, parentSource, order })),
    [
      { source: "README.md", parentSource: null, order: 0 },
      { source: "setup/README.md", parentSource: "README.md", order: 0 },
      { source: "setup/install.md", parentSource: "setup/README.md", order: 0 },
    ],
  );
});

test("converts supported Markdown and rewrites documentation links", () => {
  const html = markdownToHtml(
    "# Setup\n\nRead [Install](install.md).\n\n- First\n  - Nested\n\n| A | B |\n|---|---|\n| 1 | 2 |\n",
    {
      source: "setup/README.md",
      title: "Setup",
      linkMap: new Map([["setup/install.md", "https://example.com/documentation/setup/install/"]]),
    },
  );
  assert.match(html, /superfunky-doc-source: setup\/README\.md/);
  assert.match(html, /href="https:\/\/example\.com\/documentation\/setup\/install\/"/);
  assert.match(html, /<ul><li>First<ul><li>Nested<\/li><\/ul><\/li><\/ul>/);
  assert.match(html, /<table>/);
  assert.doesNotMatch(html, /<h1/);
});

test("renders a responsive documentation shell with complete navigation and TOC", async () => {
  const docsDirectory = await createDocs();
  const documents = await loadDocuments(docsDirectory);
  const linkMap = new Map([
    ["README.md", "/documentation/"],
    ["setup/README.md", "/documentation/setup/"],
    ["setup/install.md", "/documentation/setup/install/"],
  ]);
  const html = renderDocumentationPage(documents[2], documents, linkMap);

  assert.equal((html.match(/aria-current="page"/g) || []).length, 2);
  assert.equal((html.match(/<a href="\/documentation(?:\/setup(?:\/install)?)?\/"/g) || []).length, 6);
  assert.equal((html.match(/href="\/documentation\/setup\/"/g) || []).length, 2);
  assert.equal((html.match(/href="\/documentation\/setup\/install\/"/g) || []).length, 2);
  assert.match(html, /<h1[^>]*>Install<\/h1>/);
  assert.match(html, /href="#requirements" data-doc-toc-link/);
  assert.match(html, /aria-label="Requirements"/);
  assert.match(html, /data-superfunky-docs-script/);
  assert.match(html, /IntersectionObserver/);
  assert.match(html, /window\.__superfunkyDocumentationTocCleanup/);
  assert.match(html, /data-superfunky-docs-page="setup\/install\.md"/);
  assert.match(html, /^<!-- wp:html -->/);
  assert.match(html, /<!-- \/wp:html -->$/);
});

test("publishes idempotently and preserves the page tree", async () => {
  const docsDirectory = await createDocs();
  const wordpress = await startWordPressServer();
  const options = {
    docsDirectory,
    wpUrl: wordpress.url,
    username: "docs",
    appPassword: "se cr et",
    logger: () => {},
  };

  const first = await publishDocumentation(options);
  assert.equal(first.created, 3);
  assert.equal(first.updated, 0);
  assert.equal(wordpress.pages.length, 3);
  assert.equal(wordpress.pages[1].parent, wordpress.pages[0].id);
  assert.equal(wordpress.pages[2].parent, wordpress.pages[1].id);
  assert.match(wordpress.pages[0].content.raw, /\/documentation\/setup\/install\//);
  assert.ok(wordpress.pages.every((page) => page.content.raw.includes("data-superfunky-docs-page")));
  assert.ok(wordpress.pages.every((page) => page.content.raw.includes("data-superfunky-docs-script")));

  const second = await publishDocumentation(options);
  assert.equal(second.created, 0);
  assert.equal(second.updated, 3);
  assert.equal(wordpress.pages.length, 3);
  assert.ok(wordpress.pages.every((page) => page.status === "draft"));

  await publishDocumentation({ ...options, status: "publish" });
  assert.ok(wordpress.pages.every((page) => page.status === "publish"));
  await publishDocumentation(options);
  assert.ok(wordpress.pages.every((page) => page.status === "publish"));
});

test("dry-run requires no credentials and CLI parsing validates root IDs", async () => {
  const docsDirectory = await createDocs();
  const output = [];
  const result = await publishDocumentation({
    docsDirectory,
    dryRun: true,
    logger: (line) => output.push(line),
  });
  assert.equal(result.documents.length, 3);
  assert.match(output.join("\n"), /Install/);
  assert.throws(() => parseOptions(["--root-page-id=nope"], {}), /positive integer/);
});

test("rejects insecure non-local WordPress URLs before sending credentials", async () => {
  const docsDirectory = await createDocs();
  await assert.rejects(
    publishDocumentation({
      docsDirectory,
      wpUrl: "http://backend.example.com",
      username: "docs",
      appPassword: "secret",
      logger: () => {},
    }),
    /must use HTTPS/,
  );
});
