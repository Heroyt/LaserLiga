import * as esbuild from 'esbuild';
import {sassPlugin} from 'esbuild-sass-plugin';
import postcss from "postcss";
import autoprefixer from "autoprefixer";
import fs from 'node:fs';
import {fontawesomeSubset} from "fontawesome-subset";
import cssnanoPlugin from "cssnano";
import path from "path";
import {injectManifest} from "workbox-build";
import crypto from 'crypto';

const watch = process.argv.includes('watch');

// Font files to check
const fontFiles = [
    'fa-brands-400.woff2', 'fa-brands-400.woff', 'fa-brands-400.ttf',
    'fa-regular-400.woff2', 'fa-regular-400.woff', 'fa-regular-400.ttf',
    'fa-solid-900.woff2', 'fa-solid-900.woff', 'fa-solid-900.ttf',
];
const fontDir = path.join('assets', 'fonts');
const fontPaths = fontFiles.map(f => path.join(fontDir, f));

function getFontHashes() {
    const hashes = {};
    for (const file of fontPaths) {
        if (fs.existsSync(file)) {
            const data = fs.readFileSync(file);
            hashes[file] = crypto.createHash('sha256').update(data).digest('hex');
        } else {
            hashes[file] = null;
        }
    }
    return hashes;
}

function fontFilesChanged(before, after) {
    for (const file of fontPaths) {
        if (before[file] !== after[file]) {
            return true;
        }
    }
    return false;
}

console.time('Build');
console.time('Fontawesome');
const subset = JSON.parse(fs.readFileSync('./assets/icons/fontawesome.json', 'utf8'));

// Record hashes before subset
const fontHashesBefore = getFontHashes();

await fontawesomeSubset(
        subset,
        "assets/fonts", {
            package: 'free', targetFormats: ['woff2', "woff", 'sfnt'],
        }
);

// Record hashes after subset
const fontHashesAfter = getFontHashes();

// If any font file changed, update $fa-font-version in fontawesome.scss
if (fontFilesChanged(fontHashesBefore, fontHashesAfter)) {
    const scssPath = path.join('assets', 'scss', 'fontawesome.scss');
    let scss = fs.readFileSync(scssPath, 'utf8');
    const now = Math.floor(Date.now() / 1000);
    scss = scss.replace(/(\$fa-font-version:\s*)[^;]+;/, `$1${now};`);
    fs.writeFileSync(scssPath, scss);
    console.log(`Updated $fa-font-version in fontawesome.scss to ${now}`);
}

console.timeEnd('Fontawesome');

console.time('Prepare');

const entryPoints = [
    {out: 'main', in: 'assets/js/main.ts'},
    {out: 'main', in: 'assets/scss/main.scss'},
    {out: 'bootstrap', in: 'assets/scss/bootstrap.scss'},
    {out: 'fontawesome', in: 'assets/scss/fontawesome.scss'},
    ...fs.readdirSync('assets/scss/pages/')
            .filter(file => ['.css', '.scss'].includes(path.extname(file)))
            .map(file => {
                return {
                    out: 'pages/' + file.replace('.scss', ''), in: './assets/scss/pages/' + file
                }
            }),
];

console.log('Entrypoints:', entryPoints);

const buildOptions = {
    entryPoints,
    bundle: true,
    format: 'esm',
    splitting: true,
    chunkNames: 'chunks/[name]_[hash]',
    minify: true,
    outdir: 'dist',
    target: 'esnext',
    sourcemap: true,
    metafile: true,
    color: true,
    treeShaking: true,
    external: ['/assets/fonts/*', '/assets/images/*'],
    plugins: [
        sassPlugin({
            embedded: true,
            cssImports: true,
            quietDeps: true, // suppress deprecation warnings from dependencies
            silenceDeprecations: [
                'import',
                'global-builtin',
                'color-functions'
            ],
            async transform(source, _) {
                const {css} = await postcss([autoprefixer, cssnanoPlugin({preset: 'default'})])
                        .process(source, {from: 'assets/scss', to: 'dist/scss'})
                return css
            }
        }),
    ]
};

// const compressOptions = {
//     ...buildOptions, write: false, plugins: [...buildOptions.plugins, compress({
//         outputDir: '', brotli: false, gzip: true, exclude: ['**/*.map'],
//     }),]
// }

// Clear previous chunks
const chunkDir = path.join(buildOptions.outdir, 'chunks');
const oldChunks = [];
if (fs.existsSync(chunkDir)) {
    for (const file of fs.readdirSync(chunkDir)) {
        oldChunks.push(path.join(chunkDir, file));
    }
}

// Copy locale files from date-fsn and flatpickr
console.log('Coping locale files...');
const dateFnsLocales = fs.readdirSync('node_modules/date-fns/locale', {recursive: true})
    .filter(file => file.endsWith('.js'));
for (const locale of dateFnsLocales) {
    // Make sure the target directory exists
    if (!fs.existsSync(path.join(buildOptions.outdir, 'locales', 'date-fns', path.dirname(locale)))) {
        fs.mkdirSync(path.join(buildOptions.outdir, 'locales', 'date-fns', path.dirname(locale)), {recursive: true});
    }
    fs.copyFileSync(
            path.join('node_modules/date-fns/locale', locale),
            path.join(buildOptions.outdir, 'locales', 'date-fns', locale)
    );
}
const flatpickrLocales = fs.readdirSync('node_modules/flatpickr/dist/l10n')
        .filter(file => file.endsWith('.js'));
// Make sure the locales directory exists
if (!fs.existsSync(path.join(buildOptions.outdir, 'locales', 'flatpickr'))) {
    fs.mkdirSync(path.join(buildOptions.outdir, 'locales', 'flatpickr'), {recursive: true});
}
for (const locale of flatpickrLocales) {
    fs.copyFileSync(
            path.join('node_modules/flatpickr/dist/l10n', locale),
            path.join(buildOptions.outdir, 'locales', 'flatpickr', locale)
    );
}

try {
    const ctx = await esbuild.context(buildOptions);

    let count = 0;
    for (const oldChunk of oldChunks) {
        fs.unlinkSync(oldChunk);
        count++;
    }
    console.log(`Removed ${count} old chunk files`);

    console.timeEnd('Prepare');

    if (watch) {
        await ctx.watch();
        console.log('watching...');
    } else {
        console.log('building...');
        console.time('build');
        const result = await esbuild.build(buildOptions);
        console.timeEnd('build');

        // console.log('compressing...');
        // console.time('compression');
        // const compressResult = await esbuild.build(compressOptions);
        // console.timeEnd('compression');

        fs.writeFileSync('dist/meta.json', JSON.stringify(result.metafile));
        // fs.writeFileSync('dist/meta-compress.json', JSON.stringify(compressResult.metafile));

        await ctx.dispose();

        console.log('building service worker...');
        console.time('Service worker');
        await esbuild.build({
            entryPoints: ['assets/js/sw/service-worker.ts'],
            bundle: true,
            sourcemap: true,
            color: true,
            format: 'esm',
            target: 'esnext',
            minify: true,
            outfile: 'temp/service-worker.js',
            define: {
                '__USE_SUBTITLES__': 'true',
                '__USE_ALT_AUDIO__': 'true',
                '__USE_EME_DRM__': 'true',
                '__USE_CMCD__': 'true',
                '__USE_CONTENT_STEERING__': 'true',
                '__USE_VARIABLE_SUBSTITUTION__': 'true',
                '__USE_M2TS_ADVANCED_CODECS__': 'true',
                '__USE_MEDIA_CAPABILITIES__': 'true',
            }
        });

        injectManifest({
            swDest: 'dist/service-worker.js',
            swSrc: 'temp/service-worker.js',
            globDirectory: './dist',
            globPatterns: [
                'pages/*',
                'chunks/*',
                '*',
                '../assets/fonts/*',
                '../assets/images/*',
            ],

        })
                .then(({count, size, warnings}) => {
                    if (warnings.length > 0) {
                        console.warn('Warnings encountered while injecting the manifest:', warnings.join('\n'));
                    }

                    console.log(`Injected a manifest which will precache ${count} files, totaling ${size} bytes.`);
                });
        console.timeEnd('Service worker');
    }
} catch (e) {
    console.error(e);
}

// Update CACHE_VERSION in config.ini to current timestamp
const configPath = './private/config.ini';
if (fs.existsSync(configPath)) {
    let config = fs.readFileSync(configPath, 'utf8');
    const now = Math.floor(Date.now() / 1000);
    config = config.replace(/(CACHE_VERSION\s*=\s*)\d+/, `$1${now}`);
    fs.writeFileSync(configPath, config);
    console.log(`Updated CACHE_VERSION in config.ini to ${now}`);
}

// Set SASS_LOG_LEVEL to error to silence deprecation warnings globally
process.env.SASS_LOG_LEVEL = 'error';

console.timeEnd('Build');