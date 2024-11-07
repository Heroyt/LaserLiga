import * as esbuild from 'esbuild';
import {sassPlugin} from 'esbuild-sass-plugin';
import postcss from "postcss";
import autoprefixer from "autoprefixer";
import fs from 'node:fs';
import {fontawesomeSubset} from "fontawesome-subset";
import {compress} from 'esbuild-plugin-compress';
import cssnanoPlugin from "cssnano";
import path from "path";
import {injectManifest} from "workbox-build";

const watch = process.argv.includes('watch');

console.time('Build');
console.time('Fontawesome');
const subset = JSON.parse(fs.readFileSync('./assets/icons/fontawesome.json', 'utf8'));
await fontawesomeSubset(
        subset,
        "assets/fonts", {
            package: 'free', targetFormats: ['woff2', "woff", 'sfnt'],
        }
);
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
            async transform(source, _) {
                const {css} = await postcss([autoprefixer, cssnanoPlugin({preset: 'default'})])
                        .process(source, {from: 'assets/scss', to: 'dist/scss'})
                return css
            }
        }),
    ]
};

const compressOptions = {
    ...buildOptions, write: false, plugins: [...buildOptions.plugins, compress({
        outputDir: '', brotli: false, gzip: true, exclude: ['**/*.map'],
    }),]
}

// Clear previous chunks
const chunkDir = path.join(buildOptions.outdir, 'chunks');
const oldChunks = [];
if (fs.existsSync(chunkDir)) {
    for (const file of fs.readdirSync(chunkDir)) {
        oldChunks.push(path.join(chunkDir, file));
    }
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
        const result = await ctx.rebuild();
        console.timeEnd('build');

        console.log('compressing...');
        console.time('compression');
        const compressResult = await esbuild.build(compressOptions);
        console.timeEnd('compression');

        fs.writeFileSync('dist/meta.json', JSON.stringify(result.metafile));
        fs.writeFileSync('dist/meta-compress.json', JSON.stringify(compressResult.metafile));

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

console.timeEnd('Build');