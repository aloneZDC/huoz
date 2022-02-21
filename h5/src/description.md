一、项目中安装lib-flexible
npm install lib-flexible --save
二、在项目的入口main.js文件中引入lib-flexible
import 'lib-flexible'
三、viewprot设置
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0,minimum-scale=1.0,maximum=scale=1.0">
lib-flexible会自动在html的head中添加一个meta name="viewport"的标签，同时会自动设置html的font-size为屏幕宽度除以10，也就是1rem等于html根节点的font-size。假如设计稿的宽度是750px，此时1rem应该等于75px。假如量的某个元素的宽度是150px，那么在css里面定义这个元素的宽度就是 width: 2rem
注意：
    1.检查一下html文件的head中，如果有 meta name="viewport"标签，需要将他注释掉，因为如果有这个标签的话，lib-flexible就会默认使用这个标签。而我们要使用lib-flexible自己生成的 meta name="viewport"来达到高清适配的效果。
    2.因为html的font-size是根据屏幕宽度除以10计算出来的，所以我们需要设置页面的最大宽度是10rem。
    3.如果每次从设计稿量出来的尺寸都手动去计算一下rem，就会导致我们效率比较慢，还有可能会计算错误，所以我们可以使用postcss-px2rem-exclude自动将css中的px转成rem
第二部分：使用postcss-px2rem-exclude自动将css中的px转换成rem




一、安装postcss-px2rem-exclude
 npm install postcss-px2rem-exclude --save
二、配置 postcss-px2rem-exclude
 1 在项目的根目录下找到文件.postcssrc.js (如果没有自己创建一个)，在里面添加如下代码
module.exports = {
 "plugins": {
    // to edit target browsers: use "browserslist" field in package.json
    "postcss-import": {},
    "autoprefixer": {},
    "postcss-px2rem-exclude": {  // 添加的代码
      remUnit: 75,
      exclude: /node_modules|folder_name/i // 忽略node_modules目录下的文件
    }
  }
}
然后重新npm run dev，打开控制台可以看到代码中的px已经被转成了rem
注意：
    1.此方法只能将.vue文件style标签中的px转成rem，不能将script标签和元素style里面定义的px转成rem
    2.如果在.vue文件style中的某一行代码不希望被转成rem，只要在后面写上注释 /* no*/就可以了
    问题：为什么要 忽略node_modules目录下的文件？
    答：有时候我们在手机端项目的时候需要导入第三方UI库，例如：VUX,MINT等，这时你就会发现第三方的组件样式都变小了，变小的主要原因是第三库 css一依据 data-dpr="1" 时写死的尺寸，我们使用的flexible引入时 data-dpr不是一个写死了的，是一个动态的，就导致这个问题。
    这里就不再修改第三方的UI样式，直接忽略掉，简单直接实用。



一.安装 npm install vuex-along --save 
yarn add vuex-along
import Vue from 'vue';
import Vuex from 'vuex';
import createVuexAlong from "vuex-along";
const moduleA = {
  state: {
    a1: "hello",
  }
};
const store = new Vuex.Store({
  state: {
    count: nll
     token:''",
  },
  mutations: {
    set_count: (state, payload) => {
      state.count= payload
    },
    set_token: (state, payload) => {
      state.token = payload
    },
  plugins: [
    createVuexAlong({
      name: "hello-vuex-along", // 设置保存的集合名字，避免同站点下的多项目数据冲突
      local: {
        list: ["ma"],
        isFilter: true // 过滤模块 ma 数据， 将其他的存入 localStorage
      },
      session: {
        list: ["count", "ma.a1"] // 保存 count 和模块 ma 中的 a1 到 sessionStorage
      }
    })
  ]
});


一. 安装npm i vant -S
npm i babel-plugin-import -D
// 在.babelrc 中添加配置
// 注意：webpack 1 无需设置 libraryDirectory
{
  "plugins": [
    ["import", {
      "libraryName": "vant",
      "libraryDirectory": "es",
      "style": true
    }]
  ]
}

// 对于使用 babel7 的用户，可以在 babel.config.js 中配置
module.exports = {
  plugins: [
    ['import', {
      libraryName: 'vant',
      libraryDirectory: 'es',
      style: true
    }, 'vant']
  ]
};