const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const pluginName = 'bill-vektor-salary';
const dist = path.join(root, 'dist', pluginName);
const lock = JSON.parse(fs.readFileSync(path.join(root, 'composer.lock'), 'utf8'));

// 実際の zip に必要ファイルがあり、開発用ファイルが混入していないことを確認する。
const entries = execFileSync('unzip', ['-Z1', path.join(root, 'dist', `${pluginName}.zip`)], {
	encoding: 'utf8',
	maxBuffer: 10 * 1024 * 1024,
}).trim().split('\n');
const archiveFiles = new Set(entries.filter((entry) => !entry.endsWith('/')));
for (const entry of entries) {
	assert.ok(entry.startsWith(`${pluginName}/`), `zip のルートが不正: ${entry}`);
}
for (const name of ['tests', 'node_modules', '.github', 'bin']) {
	assert.ok(!fs.existsSync(path.join(dist, name)), `配布対象外: ${name}`);
	assert.ok(!entries.some((entry) => entry.startsWith(`${pluginName}/${name}/`)), `zip に配布対象外: ${name}`);
}
for (const file of ['bill-vektor-salary.php', 'vendor/autoload.php', 'vendor/mpdf/mpdf/src/Mpdf.php']) {
	assert.ok(fs.statSync(path.join(dist, file)).size > 0, `必要ファイルが空: ${file}`);
	assert.ok(archiveFiles.has(`${pluginName}/${file}`), `zip に必要ファイルがない: ${file}`);
}

// lock の本番依存だけがインストールされ、PHPUnit などの開発依存が除かれていることを確認する。
const installed = JSON.parse(fs.readFileSync(path.join(dist, 'vendor/composer/installed.json'), 'utf8'));
assert.equal(installed.dev, false, 'composer install --no-dev が必要');
assert.deepEqual(installed.packages.map((pkg) => pkg.name).sort(), lock.packages.map((pkg) => pkg.name).sort());
const productionNames = new Set(lock.packages.map((pkg) => pkg.name));
for (const pkg of lock['packages-dev']) {
	if (!productionNames.has(pkg.name)) {
		assert.ok(!fs.existsSync(path.join(dist, 'vendor', pkg.name)), `開発依存が混入: ${pkg.name}`);
		assert.ok(!entries.some((entry) => entry.startsWith(`${pluginName}/vendor/${pkg.name}/`)), `zip に開発依存: ${pkg.name}`);
	}
}

// vendor 全体を比較し、拡張子による取りこぼしやバイナリの破損も検出する。
const vendor = path.join(root, 'vendor');
const vendorFiles = fs.readdirSync(vendor, { recursive: true, withFileTypes: true })
	.filter((entry) => entry.isFile())
	.map((entry) => path.relative(root, path.join(entry.parentPath, entry.name)));
for (const file of vendorFiles) {
	assert.ok(archiveFiles.has(`${pluginName}/${file}`), `zip にライブラリファイルがない: ${file}`);
	assert.ok(fs.readFileSync(path.join(dist, file)).equals(fs.readFileSync(path.join(root, file))), `コピー内容が不一致: ${file}`);
}
for (const extension of ['.dat', '.otf']) {
	assert.ok(vendorFiles.some((file) => file.startsWith('vendor/mpdf/mpdf/') && file.endsWith(extension)), `mPDF の ${extension} がない`);
}
console.log(`配布物の検証成功: 本番依存 ${installed.packages.length} 個、vendor ${vendorFiles.length} ファイル、開発依存なし`);
