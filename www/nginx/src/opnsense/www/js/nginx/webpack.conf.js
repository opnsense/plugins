const path = require('path'), webpack = require('webpack');

module.exports = {
    entry: {
        'logviewer': './src/logviewer.js',
        'configuration': './src/nginx_config.js',
        'tls_handshakes': './src/tls_handshakes.js'
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: '[name].min.js'
    },
    mode: 'production',
    module: {
        rules: [
            {
                test: /\.(txt)$/,
                use: "raw-loader"
            },
            {
                test: /(\.html)$/,
                use: 'lodash-template-webpack-loader'
            }
        ],
    },
    plugins: [
        new webpack.LoaderOptionsPlugin({
            options: {
                lodashTemplateLoader: {}
            }
        })
    ]
};
