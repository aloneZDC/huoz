import axios from "axios";
import qs from "qs";
import router from "../router";
import Cookies from "js-cookie";

import '@/methods/vant.js';
import { Toast } from 'vant';
axios.defaults.timeout = 20000;
let api = location.origin;
// let headerToken = ` Bearer ${token}`;
let instance = axios.create({
  headers: {
    baseURL: api,
    "content-type": "application/x-www-form-urlencoded",
  },
});
// 请求拦截器
instance.interceptors.request.use(
  (config) => {
    // if (api) {
    //   config.url = api + config.url;
    // }
    let api_white_url = [
      "/h5/Chat/get_messages",
      "/h5/Chat/send_messages",
      "/h5/FilMining/my_level",
      "/h5/Rocket/get_pay_info",
      "/h5/shop/get_goods_list",
      "/h5/Chat/get_messages",
      "/h5/Chat/send_messages",
    ];

    let flag = true;
    api_white_url.forEach((item, index) => {
      if(item==config.url) {
        flag = false;
      }
    });

    if (flag) {
      Toast.loading({
        duration: 0, // 持续展示 toast
        forbidClick: true,
        loadingType: 'spinner',
        overlay: true,
        message: "加载中..."
      });
    }

    let think_language = Cookies.get("think_language") || "zh-cn";
    let key;
    let token_id;
    if (api.indexOf('http://localhost:') >= 0) {
      key = Cookies.get("key") || "NTA5ZDY0ZTc5NmE4ZTQ2ZmVjZWMxNTFjYzBkMGUxYWJ8OTI4ODc=";
      token_id = Cookies.get("token_id") || "92887";
      // key = Cookies.get("key") || "100";
      // token_id = Cookies.get("token_id") || "100";
    } else {
      key = Cookies.get("key");
      token_id = Cookies.get("token_id");
    }
    // if (token) {
    //   // 判断是否存在token，如果存在的话，则每个http header都加上token
    //   config.headers.Authorization = headerToken; //请求头加上token
    // }
    if (config.method == "post") {
      // 判断参数类型是否为formData，如果不是的话，则参数用qs
      if (Object.prototype.toString.call(config.data) != "[object FormData]") {
        config.data = qs.stringify({
          ...config.data,
          token_id,
          key,
          think_language,
          // exchange_rate_type: unit
        });
      }
    }

    return config;
  },
  (err) => {
    return Promise.reject(err);
  }
);

instance.interceptors.response.use(

  (res) => {
    // let code = response.data.code;
    // if (code != 10000) {
    //   Toast(response.data.message);
    // }
    //拦截响应，做统一处理
    // if (response.data.code) {
    //   switch (response.data.code) {
    //     case 1002:
    //       router.replace({
    //         path: "login",
    //         query: {
    //           redirect: router.currentRoute.fullPath,
    //         },
    //       });
    //   }
    // }
    Toast.clear();
    if (res.data.code == 10100) {
      const platform = Cookies.get('platform');
      if (platform == 'ios') {
        window.webkit.messageHandlers.iosAction.postMessage('login');
      } else if (platform == 'android') {
        apps.gologin();
      } else {
        // const url = res.data.result.dowm_url;
        // location.href = url;
      }
    }
    return res;
  },
  //接口错误状态处理，也就是说无响应时的处理
  (error) => {
    // if (error.response) {
    //   if (error.response.status == "401") {
    //     router.replace({
    //       path: "mine",
    //       query: {
    //         redirect: router.currentRoute.fullPath,
    //       },
    //     });
    //   }
    // }
    // return Promise.reject(error); // 返回接口返回的错误信息


    if (error.response) {
      if (error.response.data.code == 10100) {
        const platform = Cookies.get('platform');
        if (platform == 'ios') {
          window.webkit.messageHandlers.iosAction.postMessage('login');
        } else if (platform == 'android') {
          apps.gologin();
        } else {
          alert("返回10100")
          const url = error.response.data.result.dowm_url;
          location.href = url;
        }
      }
    }
    
  });

export default instance;
