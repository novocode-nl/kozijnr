#!/usr/bin/env node
/**
 * Cross-tree contract checks that can't live inside either test suite
 * (the backend/frontend containers each mount only their own tree):
 *
 * 1. The three copies of contracts/shared-constants.json must be identical.
 * 2. Every backend error key (the `<domain>.error.<name>` literals passed to
 *    ValidationException::create / ExceptionResponsePayload::withKey / any
 *    HasErrorKey implementation) must exist in BOTH frontend i18n catalogs —
 *    a renamed key otherwise silently degrades to the English fallback.
 *
 * Known limitation (deliberate): the check is one-way. Frontend-only keys
 * (Zod-form keys like tenants.error.subdomainPattern, frontend fallbacks
 * like tenantSettings.error.saveFailed) are legitimate, so unused catalog
 * keys are NOT flagged. The extraction also only sees full literals: never
 * build an error key by string concatenation in api/src — pass complete
 * literal keys (see StoredImageErrorKeys), or this check silently loses
 * coverage for them.
 *
 * Run from the repo root: `node scripts/check-contracts.mjs` (also wired
 * into CI and `make check-contracts`).
 */
import { readFileSync, readdirSync, statSync } from "node:fs"
import { join } from "node:path"

const root = new URL("..", import.meta.url).pathname
let failed = false
const fail = (msg) => { console.error(`FAIL: ${msg}`); failed = true }

// 1. Identical copies.
const canonical = readFileSync(join(root, "contracts/shared-constants.json"), "utf8")
for (const copy of ["api/config/contract/shared-constants.json", "web/lib/contract/shared-constants.json"]) {
  if (readFileSync(join(root, copy), "utf8") !== canonical) {
    fail(`${copy} differs from contracts/shared-constants.json — copy the canonical file over it.`)
  }
}

// 2. Error keys.
const phpFiles = []
const walk = (dir) => {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    if (statSync(full).isDirectory()) walk(full)
    else if (full.endsWith(".php")) phpFiles.push(full)
  }
}
walk(join(root, "api/src"))

const keyPattern = /['"]([a-zA-Z][a-zA-Z0-9]*\.error\.[a-zA-Z][a-zA-Z0-9]*)['"]/g
const backendKeys = new Set()
for (const file of phpFiles) {
  for (const match of readFileSync(file, "utf8").matchAll(keyPattern)) {
    backendKeys.add(match[1])
  }
}
if (backendKeys.size === 0) fail("no backend error keys found at all — the extraction regex is broken")

const flatten = (obj, prefix = "", out = {}) => {
  for (const [key, value] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${key}` : key
    if (typeof value === "string") out[path] = value
    else if (value && typeof value === "object") flatten(value, path, out)
  }
  return out
}
const catalogs = Object.fromEntries(
  ["nl", "en"].map((locale) => [
    locale,
    flatten(JSON.parse(readFileSync(join(root, `web/lib/i18n/resources/${locale}.json`), "utf8"))),
  ])
)

for (const key of [...backendKeys].sort()) {
  for (const locale of ["nl", "en"]) {
    if (!(key in catalogs[locale])) fail(`backend error key "${key}" missing from ${locale}.json`)
  }
}

if (failed) process.exit(1)
console.log(`OK: contract copies identical; ${backendKeys.size} backend error keys present in nl+en catalogs.`)
