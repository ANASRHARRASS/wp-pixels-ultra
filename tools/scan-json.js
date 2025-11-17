/**
 * Node.js tool to scan the plugin folder for .json files and validate them.
 * Usage from VS Code Terminal:
 *   node tools/scan-json.js
 * Output:
 *   - Console summary
 *   - writes scan-report.json into plugin root
 */
const fs = require('fs');
const path = require('path');

const pluginRoot = path.resolve(__dirname, '..');
function findJsonFiles(dir) {
    const res = [];
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const e of entries) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) {
            res.push(...findJsonFiles(p));
        } else if (e.isFile() && /\.json$/i.test(e.name)) {
            res.push(p);
        }
    }
    return res;
}

const files = findJsonFiles(pluginRoot);
const results = {
    scanned_at: new Date().toISOString(),
    plugin_root: pluginRoot,
    total_files: files.length,
    valid: [],
    invalid: [],
};

for (const f of files) {
    const rel = path.relative(pluginRoot, f);
    let content;
    try {
        content = fs.readFileSync(f, 'utf8');
    } catch (err) {
        results.invalid.push({ file: rel, error: 'Cannot read file' });
        continue;
    }
    if (!content || !content.trim()) {
        results.invalid.push({ file: rel, error: 'Empty file' });
        continue;
    }
    try {
        JSON.parse(content);
        results.valid.push(rel);
    } catch (err) {
        results.invalid.push({ file: rel, error: err.message });
    }
}

console.log('JSON Scan Report');
console.log('================');
console.log('Plugin root: %s', results.plugin_root);
console.log('Scanned at : %s', results.scanned_at);
console.log('Total files: %d', results.total_files);
console.log('Valid files: %d', results.valid.length);
console.log('Invalid files: %d', results.invalid.length);
if (results.invalid.length) {
    console.log('\nInvalid files detail:');
    for (const inv of results.invalid) {
        console.log('- %s: %s', inv.file, inv.error);
    }
}

const reportPath = path.join(pluginRoot, 'scan-report.json');
try {
    fs.writeFileSync(reportPath, JSON.stringify(results, null, 2), 'utf8');
    console.log('\nReport written to: %s', reportPath);
} catch (err) {
    console.error('Failed to write report:', err.message);
    process.exit(2);
}

process.exit(results.invalid.length > 0 ? 2 : 0);
