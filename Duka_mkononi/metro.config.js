// Learn more: https://docs.expo.dev/guides/customizing-metro/
const { getDefaultConfig } = require('expo/metro-config');
const path = require('path');
const fs = require('fs');

const config = getDefaultConfig(__dirname);

// Project-local cache dirs. metro-file-map's DiskCacheManager does NOT create
// its cacheDirectory (it assumes os.tmpdir() exists), so pointing it at a
// non-existent project dir caused 'ENOENT: Cache write error'. Create them now.
const transformerCacheDir = path.join(__dirname, '.metro-cache');
const fileMapCacheDir = path.join(__dirname, '.metro-file-map-cache');
fs.mkdirSync(transformerCacheDir, { recursive: true });
fs.mkdirSync(fileMapCacheDir, { recursive: true });

// 🛠️ Fix for "EMFILE: too many open files" on Windows (low RAM machines).
// Metro spawns one worker per CPU by default; on 4-core/4GB machines that
// opens too many file handles at once. Capping workers drastically reduces
// concurrent file descriptors. 1 worker = fewest simultaneous file handles
// (slower first bundle, but avoids EMFILE cache-write errors on this machine).
config.maxWorkers = 1;

// Keep the Metro caches inside the project instead of the OS temp folder
// (which was C:\eas-temp). Easier to clear and avoids stale-cache issues.
// NOTE: 'cacheDirectory' only relocates the transformer cache; the
// metro-file-map index cache is a SEPARATE key ('fileMapCacheDirectory'),
// confirmed in metro/src/node-haste/DependencyGraph/createFileMap.js.
config.cacheDirectory = transformerCacheDir;
config.fileMapCacheDirectory = fileMapCacheDir;

module.exports = config;
