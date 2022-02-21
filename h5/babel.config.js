module.exports = {
  presets: [
    '@vue/cli-plugin-babel/preset'
  ],
  plugins: [
    ['import', {
      libraryName: 'vant',
      libraryDirectory: 'es',
      style: true
    }, 'vant']
  ]
  // "plugins": [{
  //   "postcss-import": {},
  //   "autoprefixer": {},
  //   "postcss-px2rem-exclude": {  // 添加的代码
  //     remUnit: 75,
  //     exclude: /node_modules|folder_name/i // 忽略node_modules目录下的文件
  //   }
  // }]
}
