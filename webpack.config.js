// Webpack uses this to work with directories
const path = require('path');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const HtmlWebpackPlugin = require("html-webpack-plugin");
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const { name } = require('file-loader');
const glob = require('glob');
const RemovePlugin = require('remove-files-webpack-plugin');
const Noop = require('noop-webpack-plugin');
const fs = require('fs');
const ImageminPlugin = require('imagemin-webpack-plugin').default;
const pngquant = require('imagemin-pngquant');
const CopyWebpackPlugin = require('copy-webpack-plugin');

// let multipleHtmlPlugins = htmlPageNames.map(name => {
//   return new HtmlWebpackPlugin({
//     inject: (name.includes('layouts') ? true : false),
//     template: `./assets/views/${name}.html`, // relative path to the HTML files
//     filename: `../../assets/views/compiled/${name}.html`, // output HTML files
//     publicPath: '',
//   })
// });
// This is the main configuration object.
// Here, you write different options and tell Webpack what to do



class CreateDirectoryPlugin {
  constructor(directoryPath) {
    this.directoryPath = directoryPath;
  }

  apply(compiler) {
    compiler.hooks.emit.tap('CreateDirectoryPlugin', compilation => {
      if (!fs.existsSync(this.directoryPath)) {
        fs.mkdirSync(this.directoryPath, { recursive: true });
      }
    });
  }
}

module.exports = {

  // Path to your entry point. From this file Webpack will begin its work
  entry: {
    app:'./assets/js/index.js',
  },

  // Path and filename of your result bundle.
  // Webpack will bundle all JavaScript into this file
  output: {
    path: path.resolve(__dirname, 'resources/assets'),
    filename: 'js/[contenthash].js',
    clean: true,
  },
  resolve: {
    extensions: ['.mjs', '.js'],
  },

  module: {
    rules: [
        {
            test: /\.m?js$/,
            exclude: /(node_modules|bower_components)/,
            use: {
              loader: 'babel-loader',
              options: {
                presets: ['@babel/preset-env']
              }
            }
          },
          {
            // Apply rule for .sass, .scss or .css files
            test: /\.(sa|sc|c)ss$/,
      
            // Set loaders to transform files.
            // Loaders are applying from right to left(!)
            // The first loader will be applied after others
            use: [
                {
                    // After all CSS loaders, we use a plugin to do its work.
                    // It gets all transformed CSS and extracts it into separate
                    // single bundled file
                    loader: MiniCssExtractPlugin.loader
                  },
                   {
                     // This loader resolves url() and @imports inside CSS
                     loader: "css-loader",options: {
                      sourceMap: true,
                      
                  },
                   },
                   {
                     // Then we apply postCSS fixes like autoprefixer and minifying
                     loader: "postcss-loader",
                     options: {
                      sourceMap: true,
                  },
                   },
                   {
                     // First we transform SASS to standard CSS
                     loader: "sass-loader",
                     options: {
                       implementation: require("sass"),
                        sourceMap: true,
                     }
                   }
                 ]
          },
          {
            // Now we apply rule for images
            test: /\.(flv|vob|mp4|wmv)$/,
            type: 'asset/resource',
            generator:{
              filename: 'videos/[contenthash][ext]',
            }
          },
          {
            // Now we apply rule for images
            test: /\.(png|jpe?g|gif|svg)$/,
            type: 'asset/resource',
            generator:{
              filename: 'images/[contenthash][ext]',
            }
          },
          {
            // Apply rule for fonts files
            test: /\.(woff|woff2|ttf|otf|eot)$/,
            type: 'asset/resource',
            generator:{
              filename: 'fonts/[contenthash][ext]',
            }
          },
          {
            test: /\.html$/i,
            loader: "html-loader",
          },
    ]
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: "css/[contenthash].css",
    }),
    

    new ImageminPlugin({
      plugins: [
        pngquant({
          quality: [0.5, 0.5],
        }),
      ],
    }),
    new RemovePlugin({
      /**
       * Before compilation permanently removes
       * entire `./dist` folder.
       */
      before: {
          include: [
              './assets/views/compiled',
              './resources/views'
          ]
      }
    }),
    /** 
    new CopyWebpackPlugin({
      patterns: [
          { from: 'assets/js/templates', to: 'templates'}
      ]
    }),
    
    **/
    new CreateDirectoryPlugin(path.resolve(__dirname, 'resources', 'views')),
    // new HtmlWebpackPlugin({
    //     //Permite trabajar con los archivos HTML
    //     // hash: true,
    //     inject: true, //Cómo vamos a inyectar un valor a un archivo HTML.
    //     template: "./assets/views/layouts/main.html", //Dirección donde se encuentra el template principal
    //     filename: "../../assets/views/compiled/layouts/main.html", //El nombre que tendrá el archivo
    //     publicPath: '',
    //     // favicon:"./resources/images/favicon.ico",
    //   }),
    ...glob.sync('./assets/views/**/*.html').map((htmlFile) => {
      if(!htmlFile.includes('compiled')){
        return new HtmlWebpackPlugin({
        
          inject:(htmlFile.includes('layouts') ? true : false),
          template: htmlFile,
          filename: ("../../"+ htmlFile.replace('assets\\views\\', 'assets/views/compiled/')),
          publicPath: '@url',
        });
      }else{
        return new Noop();
      }
      
    }),
  
  ],

  // Default mode for Webpack is production.
  // Depending on mode Webpack will apply different things
  // on the final bundle. For now, we don't need production's JavaScript 
  // minifying and other things, so let's set mode to development
  mode: 'production',
};
