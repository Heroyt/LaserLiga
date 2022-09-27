const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CompressionPlugin = require("compression-webpack-plugin");
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
const isDevelopment = true;

module.exports = {
	mode: isDevelopment ? 'development' : 'production',
	entry: [
		'./assets/js/main.js',
		'./assets/scss/main.scss',
	],
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
				test: /\.(scss)$/,
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
		new BundleAnalyzerPlugin({
			analyzerMode: 'json',
		}),
		new ForkTsCheckerWebpackPlugin(),
		new MiniCssExtractPlugin({
			filename: isDevelopment ? '[name].css' : '[name].[hash].css',
			chunkFilename: isDevelopment ? '[id].css' : '[id].[hash].css'
		}),
		new CompressionPlugin({
			test: /\.(js|css)/
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
			cacheGroups: {
				vendor: {
					test: /[\\/]node_modules[\\/]/,
					name: 'vendors',
					//chunks: 'all',
				}
			},
		},
	},
};
