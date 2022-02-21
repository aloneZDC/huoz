import Vue from 'vue'
import App from './App.vue'
import router from './router'
import store from './store'
import Cookies from 'js-cookie'
import 'lib-flexible'
import "vant/lib/index.css"
import "../static/css/reset.css"
import '../static/font/iconfont.css'
import  '@/methods/vant.js'
import { Toast } from 'vant'
import { Locale } from 'vant'
import VueI18n from 'vue-i18n'
import enUS from '../node_modules/vant/lib/locale/lang/en-US'
import components from '@/methods/commonComponent.js';//全局引用公共组件
import cal from '@/methods/cal'//+-*/ 浮点数计算
import '@/methods/directive'//自定义指令
import instance from './http/interceptors'//axios
import * as custom from '@/methods/filter.js'//自定义过滤器
import vueSwiper from 'vue-awesome-swiper'
import 'swiper/dist/css/swiper.css'
import clipboard from 'clipboard';
import vueEsign from "vue-esign";



Vue.use(Locale);
Vue.use(VueI18n);
Vue.use(Toast);
Vue.use(vueEsign);

Object.keys(custom).forEach(key => {
  Vue.filter(key, custom[key])
})
Vue.use(components);
Vue.use(vueSwiper);

let lang = Cookies.get('think_language') || 'zh-cn';
if (lang == 'zh-cn') {
  Locale.use('zh-CN');
} else {
  Locale.use('en-US', enUS)
}

const i18n = new VueI18n({
  locale: lang,
  messages: {
    'zh-cn': require('../static/lang/zh-cn'),
    'en-us': require('../static/lang/en-us')
  }
})

Vue.config.productionTip = false
Vue.prototype.$http = instance;
Vue.prototype.cal = cal;
Vue.prototype.$cookie = Cookies;
Vue.prototype.$platform = Cookies.get('platform');
Vue.prototype.clipboard = clipboard;
Vue.prototype.$mobileFrom = "//hzc.zzyykk.com";
router.beforeEach((to, from, next) => {
  if (to.path) {
    to;
  }
  next();
});





new Vue({
  i18n,
  router,
  store,
  render: h => h(App)
}).$mount('#app')