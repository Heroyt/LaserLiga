const {fontawesomeSubset} = require("fontawesome-subset");
const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CompressionPlugin = require("compression-webpack-plugin");
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
const WorkboxPlugin = require('workbox-webpack-plugin');
const fs = require("fs");
const ImageMinimizerPlugin = require("image-minimizer-webpack-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");
const TerserPlugin = require("terser-webpack-plugin");
const {GoogleClosureLibraryWebpackPlugin} = require("google-closure-library-webpack-plugin");
const isDevelopment = false;

const files = fs.readdirSync(path.resolve(__dirname, 'assets/scss/pages/'))
    .map(file => {
        const name = 'pages/' + file.replace('.scss', '');
        return [
            name,
            {
                import: './assets/scss/pages/' + file,
                runtime: false,
            }
        ]
    });

let entry = {
    main: [
        './assets/js/main.ts',
        './assets/scss/main.scss',
    ],
    fontawesome: ['./assets/scss/fontawesome.scss'],
    bootstrap: ['./assets/scss/bootstrap.scss'],
};

files.forEach(([name, data]) => {
    entry[name] = data;
});

console.log(entry);

fontawesomeSubset(
    {
        brands: ['discord'],
        regular: ['calendar', 'circle-xmark', 'circle-check'],
        solid: [
            'medal',
            'location-dot',
            'star',
            'ranking-star',
            'trophy',
            'gear',
            'gun',
            'user',
            'right-to-bracket',
            'right-from-bracket',
            'angle-down',
            'angle-up',
            'angle-left',
            'angle-right',
            'user-plus',
            'share',
            'info',
            'circle-info',
            'xmark',
            'filter',
            'cancel',
            'user-clock',
            'edit',
            'eye',
            'pen-to-square',
            'question',
            'circle-question',
            'magnifying-glass-plus',
            'download',
            'tag',
            'list',
            'plus',
            'ban',
        ],
    },
    "assets/fonts",
    {
        package: 'free',
        targetFormats: ['woff2', "woff", 'sfnt'],
    }
);

module.exports = {
    mode: isDevelopment ? 'development' : 'production',
    entry,
    output: {
        filename: '[name].js',
        path: path.resolve(__dirname, 'dist'),
        publicPath: "/dist/"
    },
    module: {
        rules: [
            {
                test: /\.(jpe?g|png|gif|svg)$/i,
                type: "asset",
            },
            {
                test: /\.tsx?$/,
                loader: 'ts-loader'
            },
            {
                mimetype: 'image/svg+xml',
                scheme: 'data',
                type: 'asset/resource',
                generator: {
                    filename: 'icons/[hash].svg'
                }
            },
            {
                test: /\.(jpe?g|png|gif|svg)$/i,
                use: [
                    {
                        loader: ImageMinimizerPlugin.loader,
                        options: {
                            minimizer: {
                                implementation: ImageMinimizerPlugin.imageminMinify,
                                options: {
                                    plugins: [
                                        "imagemin-gifsicle",
                                        "imagemin-mozjpeg",
                                        "imagemin-pngquant",
                                        "imagemin-svgo",
                                        "imagemin-webp",
                                    ],
                                },
                            },
                        },
                    },
                ],
            },
            {
                test: /\.(s?css)$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    {
                        loader: "css-loader",
                        options: {
                            sourceMap: true,
                            importLoaders: 1,
                        }
                    },
                    {
                        loader: "postcss-loader",
                        options: {
                            sourceMap: true,
                        }
                    },
                    {
                        loader: "sass-loader",
                        options: {
                            //sourceMap: true
                        }
                    }
                ]
            },
        ],
    },
    resolve: {
        modules: [
            "node_modules",
            path.resolve(__dirname, "dist")
        ],
        extensions: [".ts", ".tsx", ".js", ".json", ".jsx", ".css", ".scss"]
    },
    plugins: [
        new GoogleClosureLibraryWebpackPlugin({
            sources: [
                path.resolve(__dirname, 'assets/**.ts')
            ],
            debug: {
                logTransformed: true
            }
        }),
        new ForkTsCheckerWebpackPlugin(),
        new WorkboxPlugin.InjectManifest({
            swSrc: './assets/js/sw/service-worker.ts',
            swDest: 'service-worker.js'
        }),
        new MiniCssExtractPlugin({
            filename: '[name].css',
            chunkFilename: '[id].css'
        }),
        new CompressionPlugin({
            test: /\.(js|ts|css)/
        }),
        new BundleAnalyzerPlugin({
            analyzerMode: 'static',
            generateStatsFile: true,
            openAnalyzer: false,
        }),
    ],
    cache: {
        type: 'filesystem',
        cacheDirectory: path.resolve(__dirname, 'temp/webpack'),
    },
    devtool: "source-map",
    optimization: {
        minimize: true,
        usedExports: true,
        runtimeChunk: true,
        moduleIds: 'deterministic',
        splitChunks: {
            //chunks: 'all',
            //usedExports: true,
            cacheGroups: {
                chart: {
                    test: /[\\/]node_modules[\\/](chart.js|chartjs-adapter-date-fns|chartjs-plugin-annotation|date-fns)[\\/]/,
                    name: 'chart',
                    chunks: 'all',
                },
                bootstrap: {
                    test: /[\\/]node_modules[\\/](bootstrap|@popperjs)[\\/]/,
                    name: 'bootstrap-lib',
                    chunks: 'all',
                }
            },
        },
        minimizer: [
            new TerserPlugin(),
            new CssMinimizerPlugin(),
            new ImageMinimizerPlugin({
                minimizer: {
                    implementation: ImageMinimizerPlugin.imageminMinify,
                    options: {
                        plugins: [
                            ["gifsicle", {interlaced: true}],
                            ["jpegtran", {progressive: true}],
                            ["optipng", {optimizationLevel: 5}],
                            ["webp", {quality: 50}],
                            ["svgo", {
                                plugins: [{name: 'preset-default', params: {overrides: {removeViewBox: false}}}]
                            }]
                        ],
                    }
                }
            }),
        ]
    },
};
