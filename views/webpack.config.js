const path = require('path');

module.exports = {
    mode: 'production',

    entry: {
        marginminds_admin: './gst-admin.js',
        marginminds_front: './gst-marginminds.js'
    },

    output: {
        filename: '[name].js',
        path: path.resolve(__dirname, '../assets/js'),
        chunkFilename: '[name].chunk.js'
    },

    module: {
        rules: [
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                },
            },
        ],
    },

    resolve: {
        extensions: ['.js', '.jsx'],
    },

    optimization: {
        minimize: true,
        minimizer: ['...'],
        splitChunks: {
            chunks: 'all',
            cacheGroups: {
                vendorsAdmin: {
                    test: /[\\/]node_modules[\\/]/,
                    name: 'vendors-admin',
                    chunks: (chunk) => chunk.name === 'marginminds_admin',
                    priority: 20,
                },
                /*vendorsFront: {
                    test: /[\\/]node_modules[\\/]/,
                    name: 'vendors-front',
                    chunks: (chunk) => chunk.name === 'marginminds_front',
                    priority: 10,
                },*/
            },
        },
    },

    performance: {
        hints: 'warning',
        maxAssetSize: 512000,
        maxEntrypointSize: 512000,
    },
};