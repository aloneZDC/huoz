<template>
  <div class="shop">
    <div class="content">
      <div class="topBox">
        <div class="headerBox">
          <img src="../../assets/shop/home/logo.png" class="logo" />
          <!-- <div class="search">
            <input
              type="text"
              placeholder="请输入商品名称"
              @click="goSearch"
              readonly
            />
            <img src="../../assets/shop/soso.png" alt="" />
          </div> -->
        </div>
        <div class="cont-swiper">
          <van-swipe :autoplay="3000">
            <van-swipe-item
              v-for="(image, index) in images"
              :key="index"
              @click="change(index)"
            >
              <img v-lazy="image.pic" />
            </van-swipe-item>
          </van-swipe>
        </div>
      </div>
      <div class="cont-bot-tips">
        <div>
          <img src="../../assets/shop/home/xiaoxi_icon_1.png" alt="" />
          <div @click="jump('/noticeList')">
            <van-notice-bar :scrollable="false">
              <van-swipe
                vertical
                class="notice-swipe"
                :autoplay="3000"
                :show-indicators="false"
              >
                <van-swipe-item
                  v-for="(item, index) in dataConfig.new_list"
                  :key="index"
                  >{{ item.title }}</van-swipe-item
                >
              </van-swipe>
            </van-notice-bar>
          </div>
          <span>></span>
        </div>
      </div>
      <div class="titleBox">
        <!-- <div @click="goMobile">
          <img src="../../assets/shop/home/home_yaoqhy_img.png" alt="" />
        </div>
        <div @click="goZero">
          <img src="../../assets/shop/home/home_lyg_img.png" alt="" />
        </div>
        <div @click="goKf">
          <img src="../../assets/shop/home/home_lianxikef_img.png" alt="" />
        </div> -->
        <div v-for="(item, index) in shopImg" :key="index" @click="goMyZShop(index)">
          <img :src="item.imgs" alt="" />
          <p>{{ item.name }}</p>
        </div>
        <!-- <div @click="goMyShop">
          <img src="../../assets/shop/home/home_bpzq_icon.png" alt="" />
          <p>爆品专区</p>
        </div>
        <div @click="goMyShop">
          <img src="../../assets/shop/home/home_xrzq_icon.png" alt="" />
          <p>新人专区</p>
        </div>
        <div @click="goMyShop">
          <img src="../../assets/shop/home/home_xcfw_icon.png" alt="" />
          <p>出行服务</p>
        </div>
        <div @click="goMyShop">
          <img src="../../assets/shop/home/home_hotel_icon.png" alt="" />
          <p>酒店住宿</p>
        </div>
        <div @click="goMyShop">
          <img src="../../assets/shop/home/home_czzx_icon.png" alt="" />
          <p>充值中心</p>
        </div> -->
      </div>
      <div class="titleContent" @click="goMyShop">
        <img src="../../assets/shop/shop/home_zysc_img.png" alt="">
      </div>
      <div class="scroll-content">
        <van-tabs v-model="activeId" @click="tabHandler">
        <van-tab
          v-for="(tabName, idx) in tabLabels"
          :key="idx"
          :title="tabName.name"
        >
        </van-tab>
        <my-shop :msgLists="tabLabels" :isLoginList="isLogin" v-if="activeId == 1 && flag"></my-shop>
        <happy-shop :msgLists="tabLabels" :isLoginList="isLogin" v-else-if="activeId == 0 && flag"></happy-shop>
        <change-shop :msgLists="tabLabels" :isLoginList="isLogin" v-else-if="activeId == 2 && flag"></change-shop>
          
        </van-tabs>
      </div>
    </div>
  </div>
</template>

<script>
import { shop_index, get_goods_list } from "@/http/api.js";
import MyShop from "./components/MyShop.vue";
import HappyShop from "./components/HappyShop.vue";
import ChangeShop from "./components/ChangeShop.vue";
export default {
  name: "shop",
  components: {
    MyShop,
    HappyShop,
    ChangeShop
  },
  
  data() {
    return {
      activeId: Number(localStorage.getItem("activeHomeIdx"))
        ? Number(localStorage.getItem("activeHomeIdx"))
        : 0,
      images: [], // 轮播图
      dataConfig: {}, // 所有
      picItem: [],
      tabLabels: [], // 首页专区
      flag: false,
      isLogin: true,
      shopImg: [
        {
          name: "石墨烯区",
          imgs: require("../../assets/shop/home/home_newyo_icon.png"),
        },
        {
          name: "新人专区",
          imgs: require("../../assets/shop/home/home_newyt_icon.png"),
        },
        {
          name: "出行服务",
          imgs: require("../../assets/shop/home/home_newyth_icon.png"),
        },
        {
          name: "酒店住宿",
          imgs: require("../../assets/shop/home/home_newyfo_icon.png"),
        },
        {
          name: "充值中心",
          imgs: require("../../assets/shop/home/home_newyfi_icon.png"),
        },
      ]
    };
  },
  methods: {
    // 邀请好友
    goMobile() {
      //未登录禁止点击
      if (this.isLogin == false) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
        }
        return false;
      }else {
        if (this.$platform == "android") {
          apps.goInv();
        }
      }
    },
    // 去其他区
    goMyZShop(indexs) {
      if (this.isLogin == false) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
        }
        return false;
      }else {
        let url = "";
        if (this.$platform == "android") {
          if(indexs == 0) {
          url = "/highShop";
          apps.newfullscreenwebview(url);
          }else if(indexs == 1) {
          url = "/zeroShop";
          apps.newfullscreenwebview(url);
          }else {
            this.$toast("敬请期待");
            return;
          }
        }else {
          if(indexs == 0) {
            url = "/highShop";
            this.$router.push({ path: url });
          }else if(indexs == 1) {
            url = "/zeroShop"
            this.$router.push({ path: url });
          }else {
            this.$toast("敬请期待");
            return;
          }
        } 
        
      }
      
    },
    // 去自营商城
    goMyShop() {
      this.$toast("敬请期待");
      return;
    },
    // 去客服
    goKf() {
      // this.$toast("敬请期待");
      // return;
      //未登录禁止点击
      if (this.isLogin == false) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
        }
        return false;
      }else {
        let url = "/customer";
        if (this.$platform == "android") {
          apps.newfullscreenwebview(url);
        }else {
          this.$router.push({ path: url });
        }
      }
    },
    goZero() {
      // //未登录禁止点击
      // if (this.isLogin == false) {
      //   if (this.$platform == "ios") {
      //     window.webkit.messageHandlers.iosAction.postMessage("login");
      //   } else if (this.$platform == "android") {
      //     apps.gologin();
      //   }
      //   return false;
      // }
      this.$toast("敬请期待");
      return;
    },
    getToken() {
      //未登录禁止点击
      if (this.isLogin == false) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
          return false;
        }
      }
    },
    goSearch() {
      let url = "/search";
      if (this.$platform == "ios") {
        window.webkit.messageHandlers.iOSShopWebView.postMessage(url);
      } else if (this.$platform == "android") {
        apps.newfullscreenwebview(url);
      } else {
        this.$router.push({ path: url });
      }
    },
  async _index() {
    await this.$http.post(shop_index).then(({ data }) => {
        if (data.code == 10000) {
          this.dataConfig = {
            ...data.result,
          };
          this.images = data.result.banners;
          this.picItem = data.result.category_list;
          this.tabLabels = data.result.categories;
          this.flag = true;
        }
      });
    },
    jump(_url) {
      // window.toast_txt('敬请期待！')
      // return;
      //未登录禁止点击
      if (this.isLogin == false) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
        }
        return false;
      }else {
        let url = _url;
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("exit");
        } else if (this.$platform == "android") {
          apps.newfullscreenwebview(url);
        } else {
          this.$router.push({ path: url });
        }
      }
      

    },
    jumpGift(_id, type) {
      //       window.toast_txt('敬请期待！')
      // return;
      //未登录禁止点击
      if (this.isLogin == false) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
        }
        return false;
      }else {
        let url = "";
        //跳转积分/拼团商品
        if (type == 2 || type == 3) {
          url = "/detail/" + _id + "?type=" + type;
        } else {
          url = "/detail/" + _id;
        }
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("exit");
        } else if (this.$platform == "android") {
          apps.newfullscreenwebview(url);
        } else {
          this.$router.push({ path: url });
        }
      }
    },
    change(_id) {
      console.log(_id);
    },
    fun() {
      console.log("监听到了");
    },
    goFclass(id, name) {
      let url = `/category?id=${id}&name=${name}`;
      this.$router.push({ path: url });
    },
    tabHandler (idx) {
      this.activeId = idx;
      // 存儲ID
      this.$nextTick(() => {
        localStorage.setItem("activeHomeIdx", idx);
      });
    },
  },
  created() {
    this._index();
    localStorage.removeItem("highIdx");
    localStorage.removeItem("zeroIdx");
  },
  mounted() {
    //获取登录态
    const tokenId = this.$cookie.get("token_id");
    if (tokenId == "" || tokenId == undefined) {
      if (this.$platform == "android") {
        this.isLogin = false;
      }else {
        this.isLogin = true;
      }
    }
    if (window.history && window.history.pushState) {
      history.pushState(null, null, document.URL);
      window.addEventListener("popstate", this.fun, false); //false阻止默认事件
    }
  },
  destroyed() {
    window.removeEventListener("popstate", this.fun, false); //false阻止默认事件
  },
};
</script>

<style lang="scss" scoped>
.content {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  overflow: auto;
  background: #f3f5f7;
  -webkit-overflow-scrolling: touch;
  // 遮罩层样式
  .wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    .block {
      width: 318px;
      height: 296px;
      border-radius: 12px;
      background-color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 43px;
      box-sizing: border-box;
      p {
        margin-top: 20px;
        font-size: 18px;
        color: #1c1c1c;
        font-weight: bold;
        span {
          color: #fb3f6a;
        }
      }
      .prompt {
        margin-top: 40px;
        font-size: 12px;
        color: #9b9b9b;
      }
      .button {
        margin-top: 30px;
        width: 191px;
        height: 40px;
        background: linear-gradient(88deg, #ffa2b5 0%, #fc3f69 100%);
        border-radius: 22px;
        line-height: 40px;
        text-align: center;
        font-size: 16px;
        color: white;
      }
    }
  }
  .topBox {
    padding-top: 10px;
    width: 100%;
    height: 244px;
    background: url("../../assets/shop/home/xiaoxi_icon.png") no-repeat center;
    background-size: 100% 100%;
    .headerBox {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 40px;
      width: 100%;
      // line-height: 44px;
      padding: 12px;
      box-sizing: border-box;
      .logo {
        margin-top: 20px;
        width: 125px;
        // height: 36px;
      }
      .search {
        padding: 0 8px;
        box-sizing: border-box;
        width: 100%;
        height: 30px;
        position: relative;
        top: 15px;
        > input {
          width: 100%;
          height: 100%;
          background: #ffffff;
          border: none;
          border-radius: 30px;
          padding-left: 46px;
          box-sizing: border-box;
        }
        > img {
          width: 16px;
          position: absolute;
          top: 8px;
          left: 30px;
          height: 14px;
        }
      }
    }
    .cont-swiper {
      padding: 0 12px;
      box-sizing: border-box;
      margin-top: 15px;
      border-radius: 8px;
      img {
        width: 100%;
        height: 100%;
        border: 8px;
      }
    }
  }
  .titleBox {
    margin: 2px 12px 14px;
    display: flex;
    padding: 10px 12px;
    box-sizing: border-box;
    justify-content: space-between;
    text-align: center;
    background: #fff;
    box-shadow: 0px 3px 9px 0px rgba(199, 198, 197, 0.26);
    border-radius: 6px;
    > div {
      > img {
        width: 34px;
        padding-bottom: 2px;
      }
    }
  }
  .titleContent {
    margin: 10px 0;
    padding: 0 12px;
    box-sizing: border-box;
    > img {
      width: 100%;
      height: 92px;
    }
  }
  .cont-bot-tips {
    padding: 5px 12px 10px;
    box-sizing: border-box;
    margin-top: 5px;
    > div {
      width: 100%;
      height: 38px;
      background: #fff;
      border-radius: 6px;
      padding-left: 15px;
      box-sizing: border-box;
      display: flex;
      align-items: center;
      box-shadow: 0px 6px 18px 0px rgba(199, 198, 197, 0.26);
      > img {
        width: 19px;
      }
      > span {
        display: inline-block;
        margin-left: auto;
        font-size: 14px;
        color: #494949;
        margin-right: 10px;
      }
      > div {
        font-size: 14px;
        font-family: "PingFang TC";
        font-weight: 400;
        color: #fff;
        width: 100%;
      }
      .notice-swipe {
        height: 34px;
        line-height: 34px;
        background: transparent;
      }
    }
  }
  .scroll-content {
    width: 100%;
    background: #ffffff;
    padding: 20px 0 25px;
    border-top-left-radius: 25px;
    border-top-right-radius: 25px;
    min-height: calc(100% - 400px);
  }
}

/deep/ .van-swipe-item {
  height: 185px;
  border-radius: 6px;
}
/deep/ .van-swipe__indicator {
  width: 10px;
  height: 2.5px;
  background: #e8e6e6;
  border-radius: 1px;
  opacity: 1;
}
/deep/ .van-swipe__indicator--active {
  background: #a17c62;
}
/deep/ .van-notice-bar {
  background: transparent;
  color: #494949;
}
// .scroll-list {
//   height: 100%;
// }
/deep/ .van-tabs__wrap--scrollable .van-tab {
  padding: 0 8px;
}
/deep/ .van-tab {
  font-size: 17px;
  height: 26px;
}
/deep/ .van-tab--active {
  color: #232323;
  font-weight: bold;
  font-size: 18px;
}
/deep/ .van-tabs__line {
  background: #FF461E;
  bottom: 32px;
  width: 65px;
  height: 6px;
  border-radius: 0;
}
</style>