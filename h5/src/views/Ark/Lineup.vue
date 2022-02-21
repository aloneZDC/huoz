<template >
  <div class="routerList">
    <header :class="info.background">
      <button
        class="iconfont iconfanhui back"
        v-if="info.isBack"
        @click="goBack"
      >
        <i class="iconfont icon-return"></i>
      </button>
      <h3>{{ info.title }}</h3>
      <span @click="$router.push(`/turnArk?num=${Usdt}`)">互转</span>
    </header>
    <div class="content">
      <div class="topBox"  @click="$router.push('/linerecordArk')">
        <div class="top-main">
          <div class="mainBox">
              <p>预约池余额 (Y令牌)</p>
            <p class="num">{{ Usdt }}</p>
          </div>
          <div class="mainBox autoBox" style="visibility: hidden;">
              <p>自动中</p>
            <p class="num">{{ AutoNum }}</p>
          </div>
         <span>></span>
        </div>
      </div>
      <div class="main">
        <van-tabs v-model="active" @click="tabHandler" swipeable>
          <van-tab
            v-for="(tabName, idx) in tabLabels"
            :key="idx"
            :title="tabName.label"
          ></van-tab>
        </van-tabs>
        <div class="oneBox" v-show="active == '0'">
          <div class="inputBox">
            <p>预约类型:</p>
            <p class="text">点火推进</p>
          </div>
          <div class="inputBox">
            <p>预约金额:</p>
            <input
              type="text"
              v-model="num"
              placeholder="请输入数量"
              @input="getPayInfo"
            />
            <span>Y令牌</span>
            <span @click="getPayInfo(1)" class="icon">|全部</span>
          </div>
          <div class="inputBox">
            <p>Y令牌消耗:</p>
            <p class="text">{{ mtkNum }}</p>
            <span>Y令牌</span>
          </div>
          <!-- <p class="usdtnum">钱包可用:&nbsp;{{ usdtNum }}&nbsp;Y令牌</p> -->
          <p class="usdtnum">钱包可用:&nbsp;{{ kmtInfo.num - 0 }}&nbsp;Y令牌</p>
          <div class="button" @click="goPay" v-show="ifBtn">立即提交</div>
          <p class="txt">
            预约池规则：</br>
             1：本平台会员可通过预约排单参与点火推进；</br>
            2：会员通过预约池，授权平台自动参与即将开始的每一 轮点火推进；</br>
            3：预约池余额不可转回钱包账户；</br>
            4：如果点火推进成功，清算时，已投燃料本金和固态燃 料退回钱包账户；</br>
            5：如果点火推进失败，按清退规则进行相应比例退回预 约池余额中；</br>
            6：请保持预约池余额＞10 Y令牌 ；</br>
          </p>
        </div>
        <div class="twoBox" v-show="active == '1'">
          <div class="scrollList">
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
                <div v-show="isNo" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <div
                  class="listBox"
                  v-for="(item, index) in items"
                  :key="index"
                >
                  <div class="left">
                    <p>{{ item.name }}</p>
                    <p>排单数量 {{ item.num }} Y令牌</p>
                  </div>
                  <div class="right">
                    <div class="top">
                      <p>{{ item.add_time }}</p>
                      <p>{{ item.status_name }}</p>
                    </div>
                    <div class="bottom">
                      <p>燃料消耗</p>
                      <p>{{ item.kmt_num }} Y令牌</p>
                    </div>
                  </div>
                </div>
              </van-list>
            </van-pull-refresh>
          </div>
        </div>
      </div>
      <div class="userinfoBox" v-show="active == '0'">
        <div class="title">{{ userinfo.date }}</div>
        <div class="ulBox">
          <div class="listBox">
            <p>当日L社区预约</p>
            <span>{{ userinfo.big_num }} Y令牌</span>
          </div>
          <div class="listBox">
            <p>L社区累计预约</p>
            <span>{{ userinfo.total_big_num }} Y令牌</span>
          </div>
          <div class="listBox">
            <p>当日M社区预约</p>
            <span>{{ userinfo.small_num }} Y令牌</span>
          </div>
          <div class="listBox">
            <p>M社区累计预约</p>
            <span>{{ userinfo.total_small_num }} Y令牌</span>
          </div>
          <div class="listBox">
            <p>当日本人预约</p>
            <span>{{ userinfo.self_num }} Y令牌</span>
          </div>
          <div class="listBox">
            <p>本人累计预约</p>
            <span>{{ userinfo.total_self_num }} Y令牌</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import {
  ark_get_user_info,
  ark_add_subscribe_transfer,
  ark_queue_log,
  ark_user_info
} from '@/http/api.js'
export default {
  data () {
    return {
      info: {
        title: '预约排单',
        isBack: true,
        exit: false,
      },
      items: [],
      isUpLoading: false, //上拉加载
      finished: false, //上拉加载完毕
      isDownLoading: false, //下拉刷新
      isNo: false,
      offset: 100,
      finishedText: "没有更多了",
      noDataImg: require('../../assets/rocket/no-data.png'),
      dataOption: {
        page: 1,
        rows: 10,
      },
      payObj: {
        product_id: '',
        num: 0
      },
      payData: {},
      timer: null,
      getListTimer: null,
      mtkNum: 0,
      Usdt: 0,
      AutoNum:0,
      usdtNum: 0,
      orderList: [],
      active: 0,
      num: '',
      userinfo: '',
      ifBtn: true,
      tabLabels: [
        {
          label: '预约池充值',
          isFirst: true,
          type: "one",
        },
        {
          label: '排单记录',
          isFirst: true,
          type: "two",
        }
      ],
      mtkNum: '---',
      kmtInfo: {},
    }
  },
  created () {
    this.get_user_info();
    this.onRefresh();
    this.getKmtInfo();
  },

  methods: {
    getKmtInfo () {
      this.$http.post(ark_user_info, { currency_id: '103' })
        .then(({ data }) => {
          if (data.code == '10000') {
            this.kmtInfo = data.result;
          }
        })
    },
    getPayInfo (_num) {
      // debugger; 
      if (_num == '1') {
        this.num = this.usdtNum;
      } else if (
        this.num == "00" ||
        this.num.substring(0, 1) == "" ||
        /[^0-9]/g.test(this.num)
      ) {
        this.num = '';
      }
      this.mtkNum = this.cal.accMul(this.num, this.kmtInfo.mtk_rate);
      // this.mtkNum = this.cal.accDiv(num, this.kmtInfo.mtk_price).toFixed(4);
    },
    goPay () {
      if (this.num == '') {
        this.$toast.fail('请输入数量');
        return;
      }
      this.ifBtn = false;
      this.$http.post(ark_add_subscribe_transfer, { num: this.num, mtk_num: this.mtkNum })
        .then(({ data }) => {
          if (data.code == '10000') {
            this.$toast.success(data.message);
            setTimeout(() => {
              this.num = '';
              this.mtkNum = '---';
              this.ifBtn = true;
              this.get_user_info();
              this.getKmtInfo();
              this.onRefresh();
            }, 500)
          } else {
            this.$toast.fail(data.message);
            setTimeout(() => {
              this.num = '';
              this.mtkNum = '---';
              this.ifBtn = true;
            }, 500)

          }
        })
    },
    get_user_info () {
      this.$http.post(ark_get_user_info)
        .then(({ data }) => {
          if (data.code == '10000') {
            this.userinfo = data.result;
            this.Usdt = data.result.num;
            this.AutoNum = data.result.forzen_num;
            this.usdtNum = data.result.usdt_num;
          }
        })
    },
    tabHandler (idx) {
      this.active = idx;
      // 存儲ID
      this.$nextTick(() => {
        localStorage.setItem("activeIdx", idx);
      });
    },
    onLoad () {
      this.isUpLoading = true;
      this.$http
        .post(ark_queue_log, this.dataOption)
        .then(({ data }) => {
          this.isDownLoading = false;
          this.isUpLoading = false;
          if (data.code == 10001) {
            this.finished = true;
            if (this.items.length == 0) {
              this.isNo = true;
            } else {
              this.isNo = false;
            }
            return;
          }
          // this.items = this.items.concat(data.result);
          this.items = data.result;
          // this.items.forEach((item, index, arr) => {
          //   item.progress = this.getPercent(Number(item.finish_money), Number(item.price));
          // })
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
    goBack () {
      if (this.$platform == "android") {
        apps.exit();
      } else {
        if (this.$route.query.type == 1) {
          this.$router.back();
        } else {
          apps.exit()
        }
      }
    }
  },
}
</script>
<style lang="scss" scoped>
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
  color: #848484;
}
/deep/ .van-tabs__nav {
  font-size: 16px;
  height: 40px;
  color: #ff461e;
}
/deep/ .van-tab--active {
  color: #ff461e;
  // border: 1px solid #B47C55;
}
/deep/ .van-tabs__line {
  width: 60px;
  height: 3px;
  background-color: #ff461e;
}
header {
  background: url("../../assets/rocket/header.png") no-repeat center;
  background-size: 100% 100%;
  color: #0f0f0f;
  height: 44px;
  text-align: center;
  line-height: 44px;
  display: flex;
  align-items: center;
  header button.back,
  header span,
  header div.right_btn {
    position: absolute;
    top: 0;
    height: 44px;
    border: none;
    background: transparent;
    padding: 0;
    margin: 0;
    display: flex;
    align-items: center;
  }
  h3 {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: bold;
  }
  button.back {
    left: 0;
    padding-left: 12px;
    color: #0f0f0f;
    border: none;
    background: none;
    > i {
      font-size: 20px;
      font-weight: bold;
    }
  }
  span {
    right: 0;
    width: 50px;
    font-size: 16px;
    color: #0f0f0f;
    padding-right: 12px;
  }
}
.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  width: auto;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang SC";
  font-size: 16px;
  color: #ffffff;
  background: #0f0f0f;
  padding-bottom: 10px;
  // padding: 0 15px;
  // box-sizing: border-box;
  .topBox {
    width: 100%;
    height: 172px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: url("../../assets/rocket/content.png") no-repeat center;
    background-size: 100% 100%;
    .top-main {
      position: relative;
      width: 345px;
      height: 94px;
      display: flex;
      align-items: center;
      padding: 20px;
      box-sizing: border-box;
      font-size: 16px;
      color: #0f0f0f;
      background-color: #fff;
      border-radius: 12px;
      .mainBox {
        display: flex;
        flex-direction: column;
        .num {
          margin-top: 10px;
          font-weight: bolder;
        }
      }
      .autoBox {
        margin-left: 40px;
      }
      span {
        margin-left: auto;
        font-weight: bolder;
      }
    }
  }
  .main {
    display: flex;
    flex-direction: column;
    width: 345px;
    margin: 0 auto;
    margin-top: -25px;
    min-height: 540px;
    background: #ffffff;
    border-radius: 12px;
    padding: 35px 20px;
    box-sizing: border-box;
    .oneBox {
      .inputBox {
        margin-top: 25px;
        width: 100%;
        display: flex;
        align-items: center;
        height: 40px;
        border-bottom: 1px solid #c6c6c6;
        color: #585858;
        font-size: 14px;
        .text {
          margin-left: 10px;
          color: #272727;
          font-size: 18px;
          // font-weight: bold;
        }
        input {
          width: 120px;
          height: 90%;
          // 清除input样式
          -webkit-appearance: none;
          -moz-appearance: none;
          outline: 0;
          border: none;
          margin-left: 10px;
        }
        // 更改样式placeholder
        input::-webkit-input-placeholder {
          color: #b2b2b2;
          font-size: 18px;
        }
        span {
          display: flex;
          align-items: center;
          text-align: left;
          margin-left: auto;
          color: #272727;
          font-size: 18px;
          // font-weight: bold;
        }
        .icon {
          color: #ff461e;
        }
      }
      .usdtnum {
        margin-top: 10px;
        color: #b2b2b2;
        font-size: 14px;
      }
      .button {
        margin-top: 40px;
        width: 301px;
        height: 50px;
        text-align: center;
        line-height: 50px;
        background: #ff461e;
        border-radius: 8px;
        color: #fff;
        font-size: 17px;
      }
      .txt {
        margin-top: 25px;
        color: #db9645;
        font-size: 12px;
      }
    }
    .twoBox {
      .scrollList {
        padding-top: 20px;
        .no-data {
          text-align: center;
          display: block;
          margin: 40px auto;
        }
        .listBox {
          margin-top: 10px;
          display: flex;
          justify-content: space-between;
          width: 100%;
          height: 89px;
          border: 1px solid #fad66b;
          border-radius: 12px;
          padding: 20px 15px;
          box-sizing: border-box;
          .left {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 16px;
            color: #333333;
            p:last-child {
              font-size: 12px;
              color: #2c281c;
            }
          }
          .right {
            display: flex;
            flex-direction: column;
            justify-content: space-between;

            font-size: 12px;
            color: #2c281c;
            .top {
              display: flex;
              align-items: center;
              p {
                color: #999999;
              }
              p:last-child {
                margin-left: 5px;
                font-size: 16px;
                font-weight: bolder;
                color: #f9cc43;
              }
            }
            .bottom {
              display: flex;
              align-items: center;
              justify-content: flex-end;
              p:last-child {
                margin-left: 5px;
              }
            }
          }
        }
      }
    }
  }
  .userinfoBox {
    margin: 0 auto;
    margin-top: 10px;
    width: 345px;
    height: 213px;
    display: flex;
    flex-direction: column;
    align-items: center;
    background-color: #fff;
    border-radius: 12px;
    .title {
      margin-top: 10px;
      font-size: 16px;
      color: #0f0f0f;
    }
    .ulBox {
      display: flex;
      flex-wrap: wrap;
      .listBox {
        margin-top: 10px;
        width: 50%;
        display: flex;
        flex-direction: column;
        text-align: center;
        p {
          font-size: 14px;
          color: #717171;
        }
        span {
          font-size: 16px;
          color: #0f0f0f;
          margin-top: 5px;
          font-weight: bolder;
        }
      }
    }
  }
}
</style>