const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CompressionPlugin = require("compression-webpack-plugin");
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
const WorkboxPlugin = require('workbox-webpack-plugin');
const fs = require("fs");
const isDevelopment = false;

const genRanHex = (size = 24) => [...Array(size)].map(() => Math.floor(Math.random() * 16).toString(16)).join('');

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
};

files.forEach(([name, data]) => {
	entry[name] = data;
});

console.log(entry);

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
		usedExports: true,
		runtimeChunk: true,
		moduleIds: 'deterministic',
		splitChunks: {
			chunks: 'all',
			usedExports: true,
			cacheGroups: {
				vendor: {
					test: /[\\/]node_modules[\\/](axios|flatpickr|@fortawesome)[\\/]/,
					name: 'vendors',
					chunks: 'all',
				},
				bootstrap: {
					test: /[\\/]node_modules[\\/](bootstrap|@popperjs)[\\/]/,
					name: 'bootstrap',
					chunks: 'all',
				}
			},
		},
	},
};
