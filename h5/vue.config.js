const CompressionPlugin = require("compression-webpack-plugin");

module.exports = {
  css: {
    loaderOptions: {
      stylus: {
        "resolve url": true,
        import: ["./src/theme"]
      }
    }
  },
  publicPath: "./",
  // 关闭代码Eslint检测
  lintOnSave: false,
  chainWebpack: config => {
    // 开启js、css压缩
    if (process.env.NODE_ENV === "production") {
      config.plugin("compressionPlugin").use(
        new CompressionPlugin({
          test: /\.js$|\.html$|.\css/, // 匹配文件名
          threshold: 10240, // 对超过10k的数据压缩
          deleteOriginalAssets: false // 不删除源文件
        })
      );
    }
  },
  productionSourceMap: false,
  devServer: {
    proxy: {
      '': {
        // target: 'http://192.168.100.50/',
        target:'http://192.168.100.82/',
        // target: "http://hj.dlyhdz.com",
        changeOrigin: true,
        pathRewrite: {
          '^/api': ''
        } 
      }
    }
  },
};
