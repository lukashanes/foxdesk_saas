const { execFileSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..');

const auditJson = JSON.parse(execFileSync(
  process.execPath,
  [path.join(root, 'bin', 'audit-csp-ui.js'), '--json'],
  {
    cwd: root,
    encoding: 'utf8'
  }
));

for (const file of [
  'includes/modules/email/email-renderer.php',
  'includes/report-functions.php'
]) {
  if (!auditJson.emailInlineStyleFiles.includes(file)) {
    throw new Error(`${file} must be explicitly classified as email-only inline CSS, not silently skipped.`);
  }
}

execFileSync(
  process.execPath,
  [path.join(root, 'bin', 'audit-csp-ui.js'), '--check-baseline'],
  {
    cwd: root,
    stdio: 'inherit'
  }
);
