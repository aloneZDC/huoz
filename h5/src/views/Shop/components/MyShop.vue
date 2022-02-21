<template>
  <div>
    <div class="my-content">
      <div class="scroll-content">
        <van-tabs v-model="myActive" @click="tabHandler" swipeable>
          
          <van-tab
            v-for="(tabName, idx) in tabLabels"
            :key="idx"
            :title="tabName.name"
          ></van-tab>
          
          <div class="my-scroll-list" v-if="myActive == 0">
            <!-- <van-pull-refresh
              v-model="oneDownLoading"
              @refresh="onRefresh('one')"
              success-text="刷新成功"
            > -->
              <van-list
                v-model="oneUpLoading"
                :finished="oneFinished"
                :finished-text="finishedText"
                @load="onLoad('one')"
                :offset="offset"
              >
                <div v-show="isone" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div class="warpBox">
                  <div
                    class="listBox"
                    v-for="(item, index) in one"
                    :key="index"
                    @click="jumpGift(item.goods_id)"
                  >
                    <img :src="item.goods_img" class="goodsImg" />
                    <p class="title pd">{{ item.goods_title }}</p>
                    <p class="text pd">{{ item.goods_desc }}</p>
                    <div class="price pd">
                      <p class="now"><span>￥</span>{{ item.goods_price }}</p>
                      <!-- <p class="num">爆提{{ item.goods_sale_number }}件</p> -->
                    </div>
                    <div class="price_mi">
                      <div>赠与≈</div>
                      <div></div>
                      <div>{{ Math.round(item.goods_currency_give_num * item.hm_price) }}<span>&nbsp;积分</span></div>
                    </div>
                  </div>
                </div>
              </van-list>
            <!-- </van-pull-refresh> -->
          </div>

          <div class="my-scroll-list" v-else-if="myActive == 1">
            <!-- <van-pull-refresh
              v-model="twoDownLoading"
              @refresh="onRefresh('two')"
              success-text="刷新成功"
            > -->
              <van-list
                v-model="twoUpLoading"
                :finished="twoFinished"
                :finished-text="finishedText"
                @load="onLoad('two')"
                :offset="offset"
              >
                <div v-show="istwo" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div class="warpBox">
                  <div
                    class="listBox"
                    v-for="(item, index) in two"
                    :key="index"
                    @click="jumpGift(item.goods_id)"
                  >
                    <img :src="item.goods_img" class="goodsImg" />
                    <p class="title pd">{{ item.goods_title }}</p>
                    <p class="text pd">{{ item.goods_desc }}</p>
                    <div class="price pd">
                      <p class="now"><span>￥</span>{{ item.goods_price }}</p>
                      <!-- <p class="num">爆提{{ item.goods_sale_number }}件</p> -->
                    </div>
                    <div class="price_mi">
                      <div>赠与≈</div>
                      <div></div>
                      <div>{{ Math.round(item.goods_currency_give_num * item.hm_price) }}<span>&nbsp;积分</span></div>
                    </div>
                  </div>
                </div>
              </van-list>
            <!-- </van-pull-refresh> -->
          </div>

          <div class="my-scroll-list" v-else-if="myActive == 2">
            <!-- <van-pull-refresh
              v-model="threeDownLoading"
              @refresh="onRefresh('three')"
              success-text="刷新成功"
            > -->
              <van-list
                v-model="threeUpLoading"
                :finished="threeFinished"
                :finished-text="finishedText"
                @load="onLoad('three')"
                :offset="offset"
              >
                <div v-show="isthree" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div class="warpBox">
                  <div
                    class="listBox"
                    v-for="(item, index) in three"
                    :key="index"
                    @click="jumpGift(item.goods_id)"
                  >
                    <img :src="item.goods_img" class="goodsImg" />
                    <p class="title pd">{{ item.goods_title }}</p>
                    <p class="text pd">{{ item.goods_desc }}</p>
                    <div class="price pd">
                      <p class="now"><span>￥</span>{{ item.goods_price }}</p>
                      <!-- <p class="num">爆提{{ item.goods_sale_number }}件</p> -->
                    </div>
                    <div class="price_mi">
                      <div>赠与≈</div>
                      <div></div>
                      <div>{{ Math.round(item.goods_currency_give_num * item.hm_price) }}<span>&nbsp;积分</span></div>
                    </div>
                  </div>
                </div>
              </van-list>
            <!-- </van-pull-refresh> -->
          </div>

          <div class="my-scroll-list" v-else-if="myActive == 3">
            <!-- <van-pull-refresh
              v-model="fourDownLoading"
              @refresh="onRefresh('four')"
              success-text="刷新成功"
            > -->
              <van-list
                v-model="fourUpLoading"
                :finished="fourFinished"
                :finished-text="finishedText"
                @load="onLoad('four')"
                :offset="offset"
              >
                <div v-show="isfour" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div class="warpBox">
                  <div
                    class="listBox"
                    v-for="(item, index) in four"
                    :key="index"
                    @click="jumpGift(item.goods_id)"
                  >
                    <img :src="item.goods_img" class="goodsImg" />
                    <p class="title pd">{{ item.goods_title }}</p>
                    <p class="text pd">{{ item.goods_desc }}</p>
                    <div class="price pd">
                      <p class="now"><span>￥</span>{{ item.goods_price }}</p>
                      <!-- <p class="num">爆提{{ item.goods_sale_number }}件</p> -->
                    </div>
                    <div class="price_mi">
                      <div>赠与≈</div>
                      <div></div>
                      <div>{{ Math.round(item.goods_currency_give_num * item.hm_price) }}<span>&nbsp;积分</span></div>
                    </div>
                  </div>
                </div>
              </van-list>
            <!-- </van-pull-refresh> -->
          </div>

          <div class="my-scroll-list" v-else-if="myActive == 4">
            <!-- <van-pull-refresh
              v-model="fiveDownLoading"
              @refresh="onRefresh('five')"
              success-text="刷新成功"
            > -->
              <van-list
                v-model="fiveUpLoading"
                :finished="fiveFinished"
                :finished-text="finishedText"
                @load="onLoad('five')"
                :offset="offset"
              >
                <div v-show="isfive" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div class="warpBox">
                  <div
                    class="listBox"
                    v-for="(item, index) in five"
                    :key="index"
                    @click="jumpGift(item.goods_id)"
                  >
                    <img :src="item.goods_img" class="goodsImg" />
                    <p class="title pd">{{ item.goods_title }}</p>
                    <p class="text pd">{{ item.goods_desc }}</p>
                    <div class="price pd">
                      <p class="now"><span>￥</span>{{ item.goods_price }}</p>
                      <!-- <p class="num">爆提{{ item.goods_sale_number }}件</p> -->
                    </div>
                    <div class="price_mi">
                      <div>赠与≈</div>
                      <div></div>
                      <div>{{ Math.round(item.goods_currency_give_num * item.hm_price) }}<span>&nbsp;积分</span></div>
                    </div>
                  </div>
                </div>
              </van-list>
            <!-- </van-pull-refresh> -->
          </div>

          <div class="my-scroll-list" v-else-if="myActive == 5">
            <!-- <van-pull-refresh
              v-model="fiveDownLoading"
              @refresh="onRefresh('five')"
              success-text="刷新成功"
            > -->
              <van-list
                v-model="sixUpLoading"
                :finished="sixFinished"
                :finished-text="finishedText"
                @load="onLoad('six')"
                :offset="offset"
              >
                <div v-show="issix" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div class="warpBox">
                  <div
                    class="listBox"
                    v-for="(item, index) in six"
                    :key="index"
                    @click="jumpGift(item.goods_id)"
                  >
                    <img :src="item.goods_img" class="goodsImg" />
                    <p class="title pd">{{ item.goods_title }}</p>
                    <p class="text pd">{{ item.goods_desc }}</p>
                    <div class="price pd">
                      <p class="now"><span>￥</span>{{ item.goods_price }}</p>
                      <!-- <p class="num">爆提{{ item.goods_sale_number }}件</p> -->
                    </div>
                    <div class="price_mi">
                      <div>赠与≈</div>
                      <div></div>
                      <div>{{ Math.round(item.goods_currency_give_num * item.hm_price) }}<span>&nbsp;积分</span></div>
                    </div>
                  </div>
                </div>
              </van-list>
            <!-- </van-pull-refresh> -->
          </div>

          <div class="my-scroll-list" v-else-if="myActive == 6">
            <!-- <van-pull-refresh
              v-model="fiveDownLoading"
              @refresh="onRefresh('five')"
              success-text="刷新成功"
            > -->
              <van-list
                v-model="sevUpLoading"
                :finished="sevFinished"
                :finished-text="finishedText"
                @load="onLoad('sev')"
                :offset="offset"
              >
                <div v-show="issev" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div class="warpBox">
                  <div
                    class="listBox"
                    v-for="(item, index) in sev"
                    :key="index"
                    @click="jumpGift(item.goods_id)"
                  >
                    <img :src="item.goods_img" class="goodsImg" />
                    <p class="title pd">{{ item.goods_title }}</p>
                    <p class="text pd">{{ item.goods_desc }}</p>
                    <div class="price pd">
                      <p class="now"><span>￥</span>{{ item.goods_price }}</p>
                      <!-- <p class="num">爆提{{ item.goods_sale_number }}件</p> -->
                    </div>
                    <div class="price_mi">
                      <div>赠与≈</div>
                      <div></div>
                      <div>{{ Math.round(item.goods_currency_give_num * item.hm_price) }}<span>&nbsp;积分</span></div>
                    </div>
                  </div>
                </div>
              </van-list>
            <!-- </van-pull-refresh> -->
          </div>

          <div class="my-scroll-list" v-else-if="myActive == 7">
            <!-- <van-pull-refresh
              v-model="fiveDownLoading"
              @refresh="onRefresh('five')"
              success-text="刷新成功"
            > -->
              <van-list
                v-model="eigUpLoading"
                :finished="eigFinished"
                :finished-text="finishedText"
                @load="onLoad('eig')"
                :offset="offset"
              >
                <div v-show="iseig" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div class="warpBox">
                  <div
                    class="listBox"
                    v-for="(item, index) in eig"
                    :key="index"
                    @click="jumpGift(item.goods_id)"
                  >
                    <img :src="item.goods_img" class="goodsImg" />
                    <p class="title pd">{{ item.goods_title }}</p>
                    <p class="text pd">{{ item.goods_desc }}</p>
                    <div class="price pd">
                      <p class="now"><span>￥</span>{{ item.goods_price }}</p>
                      <!-- <p class="num">爆提{{ item.goods_sale_number }}件</p> -->
                    </div>
                    <div class="price_mi">
                      <div>赠与≈</div>
                      <div></div>
                      <div>{{ Math.round(item.goods_currency_give_num * item.hm_price) }}<span>&nbsp;积分</span></div>
                    </div>
                  </div>
                </div>
              </van-list>
            <!-- </van-pull-refresh> -->
          </div>
        </van-tabs>
      </div>
    </div>
  </div>
</template>
<script>
import { get_goods_list } from "@/http/api.js";
export default {
  name: "myShop",
  props: ['msgLists', 'isLoginList'],
  data() {
    return {
      demo: this.tabLists,
      myActive: Number(localStorage.getItem("activeMyShopIdx"))
        ? Number(localStorage.getItem("activeMyShopIdx"))
        : 0,
      tabLabels: [], // 首页专区
      oneUpLoading: false, //上拉加载
      oneFinished: false, //上拉加载完毕
      oneDownLoading: false, //下拉刷新
      twoUpLoading: false, //上拉加载
      twoFinished: false, //上拉加载完毕
      twoDownLoading: false, //下拉刷新
      threeUpLoading: false, //上拉加载
      threeFinished: false, //上拉加载完毕
      threeDownLoading: false, //下拉刷新
      fourUpLoading: false, //上拉加载
      fourFinished: false, //上拉加载完毕
      fourDownLoading: false, //下拉刷新
      fiveUpLoading: false, //上拉加载
      fiveFinished: false, //上拉加载完毕
      fiveDownLoading: false, //下拉刷新
      sixUpLoading: false, //上拉加载
      sixFinished: false, //上拉加载完毕
      sixDownLoading: false, //下拉刷新
      sevUpLoading: false, //上拉加载
      sevFinished: false, //上拉加载完毕
      sevDownLoading: false, //下拉刷新
      eigUpLoading: false, //上拉加载
      eigFinished: false, //上拉加载完毕
      eigDownLoading: false, //下拉刷新
      noDataImg: require("../../../assets/shop/no_data.png"),
      offset: 100,
      finishedText: "沒有更多了",
      isone: false,
      istwo: false,
      isthree: false,
      isfour: false,
      isfive: false,
      issix: false,
      issev: false,
      iseig: false,
      one: [],
      two: [],
      three: [],
      four: [],
      five: [],
      six: [],
      sev: [],
      eig: [],
      oneOption: {
        type: 2,
        children_id: 0,
        page: 1,
        rows: 10,
      },
      twoOption: {
        type: 2,
        children_id: 5,
        page: 1,
        rows: 10,
      },
      threeOption: {
        type: 2,
        children_id: 10,
        page: 1,
        rows: 10,
      },
      fourOption: {
        type: 2,
        children_id: 9,
        page: 1,
        rows: 10,
      },
      fiveOption: {
        type: 2,
        children_id: 6,
        page: 1,
        rows: 10,
      },
      sixOption: {
        type: 2,
        page: 1,
        rows: 10,
      },
      sevOption: {
        type: 2,
        page: 1,
        rows: 10,
        children_id: "",
      },
      eigOption: {
        type: 2,
        page: 1,
        rows: 10,
        children_id: "",
      },
      isLogin: true,
    };
  },
  methods: {
    getToken() {
      //未登录禁止点击
      
      if (this.isLogin == false) {
        
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
        }
        return false;
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
    onLoad (type) {
      let key = type + "Option",
        tempUp = type + "UpLoading",
        tempDown = type + "DownLoading",
        tempFin = type + "Finished",
        isShow = "is" + type;
      this[tempUp] = true;
      this.fetchList(this[key]).then((data) => {
        this[tempUp] = false;
        this[tempDown] = false;
        this[type] = this[type].concat(data);
        if (this[type].length == 0) {
          this[isShow] = true;
        }
        this[key].page++;
        this[tempFin] = false;
        if (data.length < this[key].rows) {
          // 數據小于10條，已全部加載完畢finished設置爲true
          this[tempFin] = true;
          return;
        }
      });
    },
    onRefresh (type) {
      let key = type + "Option";
      this[key].page = 1;
      this[type] = [];
      this.onLoad(type);
    },
    tabHandler (idx) {
      this.tabLabels.forEach((item, index) => {
        this[item.type] = [];
        let key = item.type + "Option";
        let tempFin = item.type + "Finished";
        this[tempFin] = false;
        this[key].page = 1;
        // this.tabLabels[this.active].isFirst = false;
        if (index == idx) {
          this.myActive = idx;
          this.onLoad(item.type);
        }
      });
      // 存儲ID
      this.$nextTick(() => {
        localStorage.setItem("activeMyShopIdx", idx);
      });
    },
    fetchList (obj) {
      return new Promise((resolve, reject) => {
        let option = {
          ...obj,
        };
        this.$http.post(get_goods_list, option).then(({ data }) => {
          if (data.code == 10000) {
            if (data.result.length >= 0) {
              resolve(data.result);
            }
          } else {
            resolve([]);
          }
        });
      });
    },
  },
  mounted() {

  },
  created() {
    this.tabLabels = this.msgLists[1].children;
    this.tabLabels.forEach((item, index) => {
      switch (index) {
        case 0:
          this.oneOption.children_id = item.id;
          item.type = 'one';
          break;
        case 1:
          this.twoOption.children_id = item.id;
          item.type = 'two';
          break;
        case 2:
          this.threeOption.children_id = item.id;
          item.type = 'three';
          break;
        case 3:
          this.fourOption.children_id = item.id;
          item.type = 'four';
          break;
        case 4:
          this.fiveOption.children_id = item.id;
          item.type = 'five';
          break;
        case 5:
          this.sixOption.children_id = item.id;
          item.type = 'six';
          break;
        case 6:
          this.sevOption.children_id = item.id;
          item.type = 'sev';
          break;
        case 7:
          this.eigOption.children_id = item.id;
          item.type = 'eig';
      } 
    })
    this.isLogin = this.isLoginList;
  },
};
</script>
<style lang="scss" scoped>
.my-content {
  position: absolute;
  top: 40px;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  background: #fff;
  -webkit-overflow-scrolling: touch;
  box-sizing: border-box;
  height: auto;
  .my-scroll-list {
    padding: 0 12px;
    box-sizing: border-box;
    background: #fff;
    height: 100%;
    .shareBox {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background-color: #d9dade;
      border-radius: 6px;
      height: 38px;
      padding: 0 15px;
      box-sizing: border-box;
      img {
        width: 24px;
        height: 21px;
      }
      p {
        font-size: 14px;
        color: #494949;
      }
      span {
        font-size: 14px;
        color: #494949;
      }
    }
    .fication {
      margin-top: 10px;
      padding: 16px 0 6px;
      box-sizing: border-box;
      width: 100%;
      background: #ffffff;
      border-radius: 8px;
      display: flex;
      flex-wrap: wrap;
      > div {
        width: 86px;
        height: 70px;
        text-align: center;
        > img {
          width: 36px;
        }
        > p {
          font-family: "PingFang SC";
          font-weight: bold;
          color: #414141;
          font-size: 12px;
        }
      }
    }
    .titleBox {
      margin-top: 10px;
      font-size: 15px;
      color: #b47c55;
      height: 34px;
      line-height: 34px;
      text-align: center;
      border: 1px solid #b47c55;
      font-family: "Source Han Sans CN";
      font-weight: bold;
    }
    .no-data {
      margin-top: 30px;
      text-align: center;
      font-size: 14px;
      color: #999;
      p {
        margin-top: 24px;
      }
      img {
        width: 160px;
      }
    }
    .warpBox {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      .listBox {
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        width: 170px;
        background-color: #fff;
        margin-bottom: 8px;
        padding-bottom: 15px;
        box-shadow: 0px 6px 18px 0px rgba(199, 198, 197, 0.26);
        border-radius: 10px;
        .goodsImg {
          width: 100%;
          height: 181px;
          border-top-left-radius: 10px;
          border-top-right-radius: 10px;
        }
        .pd {
          padding: 0 8px;
          box-sizing: border-box;
        }
        .title {
          margin-top: 10px;
          font-size: 15px;
          font-weight: bold;
          color: #363636;
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
        }
        .text {
          font-size: 10px;
          color: #5c5c5c;
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
          // width: 140px; //宽度
        }
        .price {
          margin-top: 5px;
          display: flex;
          align-items: center;
          justify-content: flex-start;
          justify-content: space-between;
          .now {
            font-family: SourceHanSansCN-Bold;
            font-size: 18px;
            color: #fb3f6a;
            font-weight: bold;
            span {
              font-size: 10px;
            }
          }
          .num {
            font-size: 10px;
            color: #aeaeae;
          }
        }
        .price_mi {
          margin-top: 8px;
          display: flex;
          font-family: "Source Han Sans CN";
          font-weight: 400;
          font-size: 12px;
          justify-content: center;
          > div:nth-child(1) {
            background: url("../../../assets/shop/shop/home_but_bgone.png");
            width: 90px;
            height: 31px;
            background-size: 100% 100%;
            color: #FADA97;
            display: flex;
            align-items: center;
            padding-left: 10px;
            box-sizing: border-box;
          }
          > div:nth-child(2) {
            background: url("../../../assets/shop/shop/home_but_bgtwo.png");
            width: 21px;
            height: 31px;
            background-size: 100% 100%;
            margin-left: -20px;
          }
          > div:nth-child(3) {
            background: url("../../../assets/shop/shop/home_but_bgthr.png");
            width: 90px;
            height: 31px;
            background-size: 100% 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: -10px;
            color: #FFE060;
          }
        }
      }
    }
  }
}
/deep/ .scroll-content .van-tab {
  font-size: 13px;
  height: auto;
}
/deep/ .scroll-content .van-tab--active {
  width: fit-content;
  height: 12px;
  font-size: 14px;
  font-family: "Source Han Sans CN";
  font-weight: 400;
  color: #ff461e;
  font-weight: bold;
}
/deep/ .scroll-content .van-tabs__nav--line.van-tabs__nav--complete {
  display: flex;
  align-items: center;
}
/deep/ .scroll-content .van-tabs__line {
  // background: #fff;
  bottom: 10px;
  height: 0;
}
/deep/ .van-tabs__nav--line {
  align-items: center;
}
/deep/ .scroll-content .van-tabs--line .van-tabs__wrap {
  height: 21px;
}
</style>