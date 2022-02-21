<template>
  <div class="rocket">
    <van-pull-refresh
      v-model="isDownLoading"
      @refresh="onRefresh"
      success-text="刷新成功"
    >
      <van-list
        v-model="isUpLoading"
        :finished="finished"
        :finished-text="finishedText"
        @load="onLoad"
        :offset="offset"
        :immediate-check="false"
      >
        <div class="content">
          <div class="topBox">
            <!-- <div class="top">
              <p>火箭游戏</p>
              <img
                src="../../assets/rocket/icon-rocket.png"
                @click="goPush('exchange')"
                class="goIcon"
                v-if="status"
              />
            </div> -->
            <van-tabs v-model="active" @click="tabHandler" swipeable>
              <van-tab
                v-for="(tabName, idx) in tabLabels"
                :key="idx"
                :title="tabName.label"
              ></van-tab>
            </van-tabs>
            <div class="recordBox" :class="{arkBg:active == 1}">
              <div class="listBox">
                <div class="list" @click="goPush('warehouse', 1)">
                  <img src="../../assets/rocket/icon1.png" />
                  <p v-if="active == 0">抱彩值(火米)</p>
                  <p v-else>幸运舱(L令牌)</p>
                  <span>{{ lucky_num - 0 }}</span>
                </div>
                <div class="list" @click="goPush('advance')">
                  <img src="../../assets/rocket/icon2.png" />
                  <p>我的推进</p>
                  <span>战绩排行</span>
                </div>
                <div class="list" @click="goPush('community')">
                  <img src="../../assets/rocket/icon3.png" />
                  <p>推进贡献</p>
                  <span>共赢共利</span>
                </div>
              </div>

              <!-- <div class="buttonTurn">
                <p @click="goPush('lineup')">预购排单 <span>></span></p>
                <img src="../../assets/rocket/go.png" />
                <p @click="goPush('reward')">预购奖励 <span>></span></p>
              </div> -->

              <div class="button" @click="goPush('lineup')">
                <p>预约排单</p>
                <!-- <img src="../../assets/rocket/go.png" /> -->
                <span>></span>
              </div>
            </div>
          </div>
          <div class="titleBox">
            <p>共赢共享</p>
            <span>我们不是神话,但我们创造了无限的神话</span>
          </div>

          <div class="scroll-list">
            <div v-show="isNo" class="no-data">
              <img :src="noDataImg" />
            </div>
            <div
              class="listBox"
              v-for="(item, index) in items"
              :key="index"
              @click="goRocketList(item.id, item.name, item.rocket_status)"
            >
              <img :src="require(`@/assets/rocket/img${active}.png`)" />
              <div class="main">
                <p>
                  <img src="../../assets/rocket/fv-img.png" class="icon" />{{
                    item.name
                  }}
                </p>
                <p v-if="active == 0">抱彩值:{{ item.warehouse1 - 0 }}火米</p>
                <p v-else>幸运舱:{{ item.warehouse1 - 0 }}L令牌</p>
                <div
                  class="button disable"
                  v-if="item.rocket_status == 0"
                  :class="{ view: item.flicker_status == 1 }"
                >
                  {{ item.game_start_time }}
                </div>
                <div
                  class="button"
                  v-else
                  :class="{ view: item.flicker_status == 1 }"
                >
                  发射中
                </div>
              </div>
            </div>
          </div>
        </div>
      </van-list>
    </van-pull-refresh>
  </div>
</template>
<script>
import { Index, ark_Index, game_type } from "@/http/api.js"
export default {
  data () {
    return {
      items: [],
      isUpLoading: false, //上拉加载
      finished: false, //上拉加载完毕
      isDownLoading: false, //下拉刷新
      isNo: false,
      offset: 100,
      finishedText: "没有更多了",
      noDataImg: require('../../assets/rocket/fg-img.png'),
      dataOption: {
        page: 1,
        rows: 10
      },
      timer: null,
      kmtNum: '',
      lucky_num: '',
      status: true,
      tabLabels: [
        // {
        //   label: '火箭',
        //   isFirst: true,
        //   type: "one",
        // },
        // {
        //   label: '方舟',
        //   isFirst: true,
        //   type: "two",
        // }
      ],
      active: 0,
    }
  },
  created () {
    this.gameType();
    this.onRefresh();
    // this.getPayInfo();
  },
  mounted () {
    this.timer = setInterval(() => {
      this.onRefresh();
    }, 60000);
  },
  destroyed () {
    clearInterval(this.timer);
  },
  methods: {
    async gameType() {
      await this.$http.post(game_type).then(({ data }) => {
        if(data.code == 10000) {
          this.tabLabels = [];
          let arr1 = {
            label: '火箭',
            isFirst: true,
            type: "one",
          };
          let arr2 = {
            label: '方舟',
            isFirst: true,
            type: "two",
          }
          this.tabLabels.push(arr1);
          // is_ark == 1，方舟隐藏
          // if(data.result.is_ark == 1) {
          //   this.tabLabels.push(arr2);
          // }
        }
      })
    },
    // tab
    tabHandler (idx) {
      this.active = idx;
      // 存儲ID
      this.$nextTick(() => {
        localStorage.setItem("activeIdx", idx);
      });
      this.onRefresh();

    },
    //跳转页面
    goPush (address, query) {
      let url
      if(this.active == 0){
        url = `/${address}`;
      }else{
        url = `/${address}Ark`;
      }
      if(address == 'lineup'){
        url = `${url}?type=1`
      }
      if (query) {
        url = `${url}?id=${query}`;
      }
      if (this.$platform == "ios") {
        window.webkit.messageHandlers.iOSShopWebView.postMessage(url);
      } else if (this.$platform == "android") {
        apps.newfullscreenwebview(url);
      } else {
        this.$router.push({ path: url });
      }
    },
    // getPayInfo () {
    //   this.$http.post(user_info, { currency_id: '99' })
    //     .then(({ data }) => {
    //       if (data.code == '10000') {
    //         this.kmtNum = data.result.num;
    //         if (data.result.level == 0) {
    //           this.status = false;
    //         }
    //       }
    //     })
    // },
    onLoad () {
      let url;
      if (this.active == 0) {
        url = Index
      } else {
        url = ark_Index;
      }
      this.isUpLoading = true;
      this.$http
        .post(url, this.dataOption)
        .then(({ data }) => {
          this.isDownLoading = false;
          this.isUpLoading = false;
          this.lucky_num = data.lucky_num;
          if (data.code == 10001) {
            this.finished = true;
            if (this.items.length == 0) {
              this.isNo = true;
            } else {
              this.isNo = false;
            }
            return;
          }
          this.items = this.items.concat(data.result);
          if (data.result.length < this.dataOption.rows) {
            this.finished = true;
            return;
          } else {
            this.finished = false;
          }
          // this.finished = false;
          this.dataOption.page += 1;
        })
        .catch(err => {
          err;
          this.items = [];
        });
    },
    onRefresh () {
      this.dataOption.page = 1;
      // 清空列表数据
      this.items = [];
      this.finished = false;
      // 重新加载数据
      // 将 loading 设置为 true，表示处于加载状态
      // this.loading = false;
      this.onLoad();
    },
    //go
    goRocketList (id, name, status) {
      // if (status == '0') { return false; }
      let url;
      if(this.active == 0){
        url = `/rocketList?id=${id}&name=${name}`;
      }else{
        url = `/rocketListArk?id=${id}&name=${name}`;
      }
      if (this.$platform == "ios") {
        window.webkit.messageHandlers.iOSShopWebView.postMessage(url);
      } else if (this.$platform == "android") {
        apps.newfullscreenwebview(url);
      } else {
        this.$router.push({ path: url });
      }
    }
  },
}
</script>
<style lang="scss" scoped>
@keyframes light {
  from {
    opacity: 1;
  }
  to {
    opacity: 0.2;
  }
}
/deep/ .van-tabs__wrap {
  margin-top: 10px;
  height: 44px;
}
/deep/ .van-tab {
  display: flex;
  align-items: center;
  justify-content: space-around;
  font-size: 16px;
  height: 30px;
  line-height: 30px;
  width: 100px;
  color: white;
}
/deep/ .van-tabs__nav {
  font-size: 16px;
  height: 40px;
  color: white;
  background-color: #E94927;
}
/deep/ .van-tab--active {
  color: white;
  // border: 1px solid #B47C55;
}
/deep/ .van-tabs__line {
  width: 60px;
  height: 3px;
  background-color: white;
}
.rocket {
  .content {
    width: auto;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    font-family: "PingFang SC";
    font-size: 16px;
    color: #000;
    background: #e94927;
    padding: 0 17px;
    box-sizing: border-box;
    .topBox {
      height: 282px;
      width: 100%;
      .top {
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: white;
        p {
          font-size: 16px;
          font-weight: bolder;
        }
        .goIcon {
          width: 16.25px;
          height: 27px;
        }
      }
      .recordBox {
        margin-top: 10px;
        width: 100%;
        background: linear-gradient(180deg, #f7e8e7 0%, #0e1632 100%);
        opacity: 1;
        border-radius: 12px;
        font-size: 16px;
        padding: 40px 25px 15px 25px;
        box-sizing: border-box;

        .listBox {
          display: flex;
          align-items: center;
          justify-content: space-between;
          .list {
            display: flex;
            flex-direction: column;
            align-items: center;
            img {
              width: 41px;
              height: 41px;
            }
            p {
              margin-top: 20px;
              color: #d5d5d5;
              font-size: 14px;
            }
            span {
              margin-top: 5px;
              font-size: 16px;
              color: #ffffff;
            }
          }
        }
        .buttonTurn {
          margin-top: 14px;
          padding: 0 15px;
          box-sizing: border-box;
          width: 295px;
          height: 40px;
          line-height: 40px;
          background: #ff461e;
          color: #ffffff;
          border-radius: 24px;
          display: flex;
          align-items: center;
          justify-content: space-between;
          > p {
            > span {
              font-size: 18px;
            }
          }
        }
        .button {
          margin: 0 auto;
          margin-top: 14px;
          padding: 0 15px;
          box-sizing: border-box;
          width: 295px;
          height: 40px;
          line-height: 40px;
          background: #ff461e;
          color: #ffffff;
          border-radius: 24px;
          display: flex;
          align-items: center;
          justify-content: space-between;
          img {
            width: 24.72px;
            height: 24.72;
          }
          span {
            font-size: 18px;
          }
        }
      }
      .arkBg{
        background: linear-gradient(180deg, #F7E8E7 0%, #002193 100%);
      }
    }
    .titleBox {
      display: flex;
      align-items: center;
      color: #fff;
      p {
        font-size: 16px;
        font-weight: bold;
      }
      span {
        margin-left: 5px;
        padding: 0 10px;
        height: 21px;
        line-height: 21px;
        box-sizing: border-box;
        border-radius: 2px;
        font-size: 12px;
        opacity: 0.5;
      }
    }

    .scroll-list {
      margin-top: 15px;
      display: flex;
      flex-direction: column;
      // justify-content: space-between;
      padding-bottom: 50px;
      min-height: 300px;
      .no-data {
        display: block;
        margin: 0 auto;
        margin-top: 60px;
      }
      .listBox {
        display: flex;
        margin-top: 10px;
        // flex-direction: column;
        align-items: center;
        height: 117px;
        background: white;
        border-radius: 8px;
        img {
          margin-left: 22px;
          width: 94px;
          height: 91px;
        }
        .main {
          margin-left: 30px;
          display: flex;
          flex-direction: column;
          // align-items: flex-start;
          justify-content: space-between;
          p {
            font-size: 16px;
            color: #464646;
            margin-top: 5px;
          }
          p:first-child {
            margin-top: 0;
            font-weight: 800;
            color: #1f1f1f;
            .icon {
              width: 8px;
              height: 8px;
              margin-right: 5px;
              margin-left: 0px;
            }
          }
          .button {
            margin-top: 7px;
            width: 135px;
            height: 32px;
            line-height: 32px;
            text-align: center;
            background: linear-gradient(180deg, #ffffff 0%, #ff461e 100%);
            border-radius: 23px;
            color: white;
            span {
              font-size: 10px;
            }
          }
          .disable {
            background: #cccccc;
            color: #ff4921;
          }
          .view {
            animation-name: light;
            animation-duration: 1s;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            animation-direction: alternate;
            background: #ff461e;
            color: white;
          }
        }
      }

      .bg2 {
        background: url("../../assets/rocket/bg2.png") no-repeat center;
      }
      .bg3 {
        background: url("../../assets/rocket/bg3.png") no-repeat center;
      }
    }
  }
}
</style>