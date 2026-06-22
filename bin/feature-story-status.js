#!/usr/bin/env node
/**
 * Summarize the canonical feature user-story tracker.
 *
 * Default mode is informational and exits 0 so it can be used during active
 * work. Use --strict when the release/goal requires every non-deprecated story
 * to be fully retested.
 */

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const args = process.argv.slice(2);

function argValue(name, fallback = '') {
  const prefix = `${name}=`;
  const inline = args.find((arg) => arg.startsWith(prefix));
  if (inline) return inline.slice(prefix.length);
  const index = args.indexOf(name);
  if (index >= 0 && args[index + 1] && !args[index + 1].startsWith('--')) return args[index + 1];
  return fallback;
}

const json = args.includes('--json');
const strict = args.includes('--strict');
const file = path.resolve(root, argValue('--file', 'docs/feature-user-stories.csv'));

function parseCsv(source) {
  const rows = [];
  let row = [];
  let field = '';
  let quoted = false;

  for (let i = 0; i < source.length; i += 1) {
    const char = source[i];
    const next = source[i + 1];

    if (quoted) {
      if (char === '"' && next === '"') {
        field += '"';
        i += 1;
      } else if (char === '"') {
        quoted = false;
      } else {
        field += char;
      }
      continue;
    }

    if (char === '"') {
      quoted = true;
    } else if (char === ',') {
      row.push(field);
      field = '';
    } else if (char === '\n') {
      row.push(field.replace(/\r$/, ''));
      rows.push(row);
      row = [];
      field = '';
    } else {
      field += char;
    }
  }

  if (field !== '' || row.length > 0) {
    row.push(field.replace(/\r$/, ''));
    rows.push(row);
  }

  return rows.filter((item) => item.length > 1 || item[0] !== '');
}

function summarize(records) {
  const total = records.length;
  const complete = records.filter((record) => record.feature_status === 'retested_pass' && record.test_status === 'retested_pass');
  const deprecated = records.filter((record) => record.feature_status === 'deprecated');
  const open = records.filter((record) => record.feature_status !== 'deprecated' && (record.feature_status !== 'retested_pass' || record.test_status !== 'retested_pass'));
  const byStatus = {};
  const byArea = {};
  const byPriority = {};

  for (const record of records) {
    const statusKey = `${record.feature_status}/${record.test_status}`;
    byStatus[statusKey] = (byStatus[statusKey] || 0) + 1;
    byArea[record.area] = (byArea[record.area] || 0) + 1;
    byPriority[record.priority] = (byPriority[record.priority] || 0) + 1;
  }

  return {
    status: open.length === 0 ? 'complete' : 'incomplete',
    total,
    complete: complete.length,
    deprecated: deprecated.length,
    open: open.length,
    by_status: byStatus,
    by_area: byArea,
    by_priority: byPriority,
    open_items: open.map((record) => ({
      feature_id: record.feature_id,
      area: record.area,
      feature: record.feature,
      priority: record.priority,
      feature_status: record.feature_status,
      test_status: record.test_status,
      error_status: record.error_status,
      fix_status: record.fix_status,
      retest_status: record.retest_status,
      test_evidence: record.test_evidence,
      notes: record.notes,
      last_reviewed: record.last_reviewed,
    })),
  };
}

function loadRecords() {
  const source = fs.readFileSync(file, 'utf8');
  const rows = parseCsv(source);
  if (rows.length === 0) {
    throw new Error(`No rows found in ${file}`);
  }
  const header = rows[0];
  const required = ['feature_id', 'area', 'feature', 'feature_status', 'test_status', 'priority', 'test_evidence', 'last_reviewed'];
  for (const column of required) {
    if (!header.includes(column)) {
      throw new Error(`Missing required column ${column} in ${file}`);
    }
  }

  return rows.slice(1).map((row, index) => {
    if (row.length !== header.length) {
      throw new Error(`Row ${index + 2} has ${row.length} columns, expected ${header.length}.`);
    }
    return Object.fromEntries(header.map((column, columnIndex) => [column, row[columnIndex] || '']));
  });
}

function printText(result) {
  console.log(`Feature story tracker: ${result.status}`);
  console.log(`Rows: ${result.total}; retested: ${result.complete}; deprecated: ${result.deprecated}; open: ${result.open}`);
  console.log('');
  if (result.open_items.length === 0) {
    console.log('All non-deprecated feature stories are retested_pass/retested_pass.');
    return;
  }

  console.log('Open feature stories:');
  for (const item of result.open_items) {
    console.log(`- ${item.feature_id} ${item.priority} ${item.area}: ${item.feature}`);
    console.log(`  status: ${item.feature_status}/${item.test_status}`);
    if (item.retest_status) console.log(`  retest: ${item.retest_status}`);
    if (item.notes) console.log(`  notes: ${item.notes}`);
  }
}

try {
  const result = summarize(loadRecords());
  if (json) {
    console.log(JSON.stringify(result, null, 2));
  } else {
    printText(result);
  }
  process.exit(strict && result.open > 0 ? 1 : 0);
} catch (error) {
  const failure = { status: 'error', error: error.message };
  if (json) {
    console.log(JSON.stringify(failure, null, 2));
  } else {
    console.error(`Feature story tracker failed: ${error.message}`);
  }
  process.exit(1);
}
