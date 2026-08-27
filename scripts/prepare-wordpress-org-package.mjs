#!/usr/bin/env node

import { cp, lstat, mkdir, readFile, readdir, rm, writeFile } from "node:fs/promises";
import path from "node:path";

const excludedRootEntries = new Set([
  ".git",
  ".github",
  ".gitignore",
  ".monorepo-source.json",
  ".wordpress-org",
  "CODE_OF_CONDUCT.md",
  "CONTRIBUTING.md",
  "EXTRACTION_STATUS.md",
  "README.md",
  "SECURITY.md",
  "docs",
  "node_modules",
  "scripts",
]);

async function copyTree(source, destination) {
  const sourceStat = await lstat(source);
  if (sourceStat.isSymbolicLink()) {
    throw new Error(`WordPress.org packages must not contain symlinks: ${source}`);
  }
  if (sourceStat.isDirectory()) {
    await mkdir(destination, { recursive: true });
    for (const entry of await readdir(source, { withFileTypes: true })) {
      await copyTree(path.join(source, entry.name), path.join(destination, entry.name));
    }
    return;
  }
  if (!sourceStat.isFile()) {
    throw new Error(`Unsupported WordPress.org package entry: ${source}`);
  }
  await mkdir(path.dirname(destination), { recursive: true });
  await cp(source, destination);
}

function removeExternalUpdater(source) {
  return source
    .replace(/^[^\n]*require_once[^\n]*superfunky-update-client\.php';\r?\n/m, "")
    .replace(
      /^Superfunky_Update_Client::register_product\(\r?\n[\s\S]*?^\);\r?\n/m,
      "",
    );
}

export async function prepareWordPressOrgPackage({ outRoot, repositoryRoot }) {
  const resolvedRepositoryRoot = path.resolve(repositoryRoot);
  const resolvedOutRoot = path.resolve(outRoot);
  if (
    resolvedRepositoryRoot === resolvedOutRoot ||
    resolvedOutRoot.startsWith(`${resolvedRepositoryRoot}${path.sep}`)
  ) {
    throw new Error("The WordPress.org output must be outside the source repository.");
  }

  const metadata = JSON.parse(
    await readFile(path.join(resolvedRepositoryRoot, ".monorepo-source.json"), "utf8"),
  );
  if (
    !["plugin", "theme"].includes(metadata.kind) ||
    !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(metadata.installSlug ?? "") ||
    typeof metadata.versionFile !== "string"
  ) {
    throw new Error("The public package provenance is invalid.");
  }

  await rm(resolvedOutRoot, { force: true, recursive: true });
  await mkdir(resolvedOutRoot, { recursive: true });
  for (const entry of await readdir(resolvedRepositoryRoot, { withFileTypes: true })) {
    if (excludedRootEntries.has(entry.name)) continue;
    await copyTree(
      path.join(resolvedRepositoryRoot, entry.name),
      path.join(resolvedOutRoot, entry.name),
    );
  }

  const versionPath = path.join(resolvedOutRoot, metadata.versionFile);
  const versionSource = await readFile(versionPath, "utf8");
  await writeFile(
    versionPath,
    versionSource.replace(
      /^[ \t]*(?:\*[ \t]*)?Update URI:[ \t]*https?:\/\/[^\r\n]+\r?\n/m,
      "",
    ),
  );
  const entrypointPath =
    metadata.kind === "theme"
      ? path.join(resolvedOutRoot, "functions.php")
      : versionPath;
  const entrypointSource = await readFile(entrypointPath, "utf8");
  await writeFile(entrypointPath, removeExternalUpdater(entrypointSource));

  const updaterPath = path.join(
    resolvedOutRoot,
    metadata.kind === "theme" ? "inc" : "includes",
    "superfunky-update-client.php",
  );
  await rm(updaterPath, { force: true });

  const preparedSources = `${await readFile(versionPath, "utf8")}\n${await readFile(
    entrypointPath,
    "utf8",
  )}`;
  if (
    /Update URI:|Superfunky_Update_Client|superfunky-update-client\.php/.test(
      preparedSources,
    )
  ) {
    throw new Error("The external update client was not fully removed.");
  }

  return {
    installSlug: metadata.installSlug,
    kind: metadata.kind,
    outRoot: resolvedOutRoot,
    versionFile: metadata.versionFile,
  };
}

function parseArguments(arguments_) {
  const outIndex = arguments_.indexOf("--out");
  if (outIndex === -1 || !arguments_[outIndex + 1] || arguments_.length !== 2) {
    throw new Error(
      "Usage: node scripts/prepare-wordpress-org-package.mjs --out <directory>",
    );
  }
  return path.resolve(arguments_[outIndex + 1]);
}

if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(import.meta.filename)) {
  const result = await prepareWordPressOrgPackage({
    outRoot: parseArguments(process.argv.slice(2)),
    repositoryRoot: process.cwd(),
  });
  console.log(`Prepared WordPress.org ${result.kind} package at ${result.outRoot}`);
}
