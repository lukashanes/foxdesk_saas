const fs = require('fs');
const path = require('path');

const root = process.cwd();
const roots = ['README.md', 'INSTALL.md', 'MANUAL.md', 'docs', 'examples/agent-api', 'cloudflare/email-router'];
const markdownFiles = [];

function walk(target) {
  const absolute = path.join(root, target);
  if (!fs.existsSync(absolute)) {
    return;
  }
  const stat = fs.statSync(absolute);
  if (stat.isFile() && target.endsWith('.md')) {
    markdownFiles.push(absolute);
    return;
  }
  if (!stat.isDirectory()) {
    return;
  }
  for (const entry of fs.readdirSync(absolute)) {
    if (entry === 'node_modules' || entry === '.git') {
      continue;
    }
    walk(path.join(target, entry));
  }
}

for (const target of roots) {
  walk(target);
}

function isExternal(link) {
  return /^(https?:|mailto:|tel:|#)/i.test(link);
}

function normalizeTarget(sourceFile, link) {
  const withoutAnchor = link.split('#')[0];
  if (withoutAnchor === '') {
    return null;
  }
  if (withoutAnchor.startsWith('/')) {
    return path.join(root, withoutAnchor);
  }
  return path.resolve(path.dirname(sourceFile), withoutAnchor);
}

const failures = [];
const linkPattern = /(?<!!)\[[^\]]+\]\(([^)]+)\)/g;

for (const file of markdownFiles) {
  const body = fs.readFileSync(file, 'utf8');
  for (const match of body.matchAll(linkPattern)) {
    const rawLink = match[1].trim();
    const link = rawLink.replace(/^<|>$/g, '');
    if (isExternal(link)) {
      continue;
    }
    const target = normalizeTarget(file, link);
    if (!target || fs.existsSync(target)) {
      continue;
    }
    failures.push(`${path.relative(root, file)} -> ${rawLink}`);
  }
}

if (failures.length > 0) {
  console.error('Broken documentation links:');
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log(`Documentation links OK (${markdownFiles.length} files checked).`);
