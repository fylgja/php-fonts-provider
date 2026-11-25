// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

// Runs tests/parity/cases.json through @fylgja/fonts-provider and prints the
// report run.php prints, on the recorded HTTP responses of this repo.

import { createHash } from "node:crypto";
import dns from "node:dns";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { pathToFileURL } from "node:url";

const here = path.dirname(new URL(import.meta.url).pathname);
const source = process.argv[2]
	? pathToFileURL(path.resolve(process.argv[2], "index.js")).href
	: "@fylgja/fonts-provider";
const { generate, normalize } = await import(source);

const fixtures = path.join(here, "../fixtures");
const cases = JSON.parse(fs.readFileSync(path.join(here, "cases.json"), "utf8"));

/**
 * A `fetch` on the recorded responses.
 *
 * @param {boolean} offline
 * @param {Record<string, {status?: number, headers?: Record<string, string>, body?: string}>} responses - by URL, before the fixtures
 * @returns {typeof fetch}
 */
function replayFetch(offline, responses) {
	return async (url) => {
		if (offline) throw new Error("offline");

		if (responses[url]) {
			const { status = 200, headers = {}, body = "" } = responses[url];
			return new Response(body, { status, headers });
		}

		const key = createHash("sha1").update(url).digest("hex");
		const file = path.join(fixtures, "http", `${key}.json`);
		if (!fs.existsSync(file)) throw new Error(`No fixture for ${url}`);

		const meta = JSON.parse(fs.readFileSync(file, "utf8"));
		const body = meta.bodyFile
			? fs.readFileSync(path.join(fixtures, "http", meta.bodyFile))
			: Buffer.from(meta.body ?? "", "utf8");

		return new Response(body, { status: meta.status });
	};
}

/**
 * Lists the files in a folder and its subfolders.
 *
 * @param {string} dir
 * @param {string} [prefix]
 * @returns {string[]}
 */
function listFiles(dir, prefix = "") {
	return fs.readdirSync(dir, { withFileTypes: true })
		.flatMap((entry) => (entry.isDirectory()
			? listFiles(path.join(dir, entry.name), `${prefix}${entry.name}/`)
			: [`${prefix}${entry.name}`]))
		.sort();
}

const out = (line) => process.stdout.write(`${line}\n`);

for (const { name, fonts } of cases.normalize) {
	out(`### normalize: ${name}`);
	const config = normalize(fonts);
	for (const font of config.fonts) out(JSON.stringify(font));
	for (const error of config.errors) out(`ERROR: ${error}`);
}

for (const { name, fonts, options = {}, offline = false, files = {}, dns: hosts = {}, responses = {} } of cases.generate) {
	out(`### generate: ${name}`);
	const storage = fs.mkdtempSync(path.join(os.tmpdir(), "fylgja-parity-"));
	for (const [file, fixture] of Object.entries(files)) {
		fs.mkdirSync(path.dirname(path.join(storage, file)), { recursive: true });
		fs.copyFileSync(path.join(fixtures, "fonts", fixture), path.join(storage, file));
	}

	try {
		// Every host is public unless the case lists its addresses.
		dns.lookup = (host, _options, callback) => {
			callback(null, (hosts[host] ?? ["8.8.8.8"]).map((address) => ({ address, family: address.includes(":") ? 6 : 4 })));
		};
		const result = await generate(fonts, { ...options, storage, fetch: replayFetch(offline, responses) });
		out("--- css");
		out(result.css());
		out("--- warnings");
		for (const warning of result.warnings) out(warning.replaceAll(storage, "<fontsDir>"));
		out("--- downloads");
		for (const download of result.downloads) out(`${download.status} ${download.file} ${download.error ?? ""}`.trim());
		out("--- files");
		for (const file of listFiles(storage)) out(file);
		out("--- variables");
		out(JSON.stringify(result.variables()));
		out("--- metrics");
		for (const family of result.families) out(JSON.stringify(family.metrics));
		out("--- preload");
		out(result.preloadHtml());
	} catch (error) {
		out(`ERROR: ${error.message}`);
	}

	fs.rmSync(storage, { recursive: true, force: true });
}
