const path = require('path'), webpack = require('webpack');

module.exports = {
    entry: './src/logviewer.js',
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: 'bundle.js'
    },
    mode: 'development',
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
