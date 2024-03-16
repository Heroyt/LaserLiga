import * as esbuild from 'esbuild';
import {sassPlugin} from 'esbuild-sass-plugin';
import postcss from "postcss";
import autoprefixer from "autoprefixer";
import fs from 'node:fs';
import {injectManifest} from "workbox-build";
import {fontawesomeSubset} from "fontawesome-subset";
import {BuildPlugin} from "@datadog/build-plugin/dist/esbuild/index.js";
import {compress} from 'esbuild-plugin-compress';
import cssnanoPlugin from "cssnano";

const watch = process.argv.includes('watch');

await fontawesomeSubset(
        {
            brands: ['discord'],
            regular: ['calendar', 'circle-xmark', 'circle-check'],
            solid: [
                'angle-down',
                'angle-left',
                'angle-right',
                'angle-up',
                'ban',
                'cancel',
                'check',
                'circle-info',
                'circle-question',
                'download',
                'edit',
                'eye',
                'filter',
                'gear',
                'gun',
                'info',
                'list',
                'location-dot',
                'magnifying-glass-plus',
                'medal',
                'pen-to-square',
                'play',
                'plus',
                'question',
                'ranking-star',
                'right-from-bracket',
                'right-to-bracket',
                'share',
                'star',
                'stop',
                'tag',
                'trophy',
                'user',
                'user-clock',
                'user-plus',
                'xmark',
                'moon',
                'sun',
            ],
        },
        "assets/fonts",
        {
            package: 'free',
            targetFormats: ['woff2', "woff", 'sfnt'],
        }
);

const ctx = await esbuild.context({
    entryPoints: [
        {out: 'main', in: 'assets/js/main.ts'},
        {out: 'main', in: 'assets/scss/main.scss'},
        {out: 'bootstrap', in: 'assets/scss/bootstrap.scss'},
        {out: 'fontawesome', in: 'assets/scss/fontawesome.scss'},
        ...fs.readdirSync('assets/scss/pages/').map(file => {
            return {
                out: 'pages/' + file.replace('.scss', ''),
                in: './assets/scss/pages/' + file
            }
        }),
    ],
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
    write: false,
    external: [
        '/assets/fonts/*',
        '/assets/images/*'
    ],
    plugins: [
        sassPlugin({
            embedded: true,
            cssImports: true,
            async transform(source, _) {
                const {css} = await postcss([autoprefixer, cssnanoPlugin({preset: 'default'})]).process(source, {
                    from: 'assets/scss',
                    to: 'dist/scss'
                })
                return css
            }
        }),
        compress({
            outputDir: '',
            brotli: false,
            gzip: true,
            exclude: ['**/*.map'],
        }),
        BuildPlugin(),
    ]
});

if (watch) {
    await ctx.watch();
    console.log('watching...')
} else {
    const result = await ctx.rebuild();
    fs.writeFileSync('dist/meta.json', JSON.stringify(result.metafile));
    await ctx.dispose();

    await esbuild.build({
        entryPoints: ['assets/js/sw/service-worker.ts'],
        bundle: true,
        sourcemap: true,
        color: true,
        format: 'esm',
        target: 'esnext',
        minify: true,
        outfile: 'temp/service-worker.js',
        write: false,
        plugins: [
            BuildPlugin(),
            compress({
                outputDir: '',
                brotli: false,
                gzip: true,
                exclude: ['**/*.map'],
            }),
        ]
    })

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
        ]
    }).then(({count, size, warnings}) => {
        if (warnings.length > 0) {
            console.warn(
                    'Warnings encountered while injecting the manifest:',
                    warnings.join('\n')
            );
        }

        console.log(`Injected a manifest which will precache ${count} files, totaling ${size} bytes.`);
    });
}