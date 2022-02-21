<template >
  <div class="routerList">
    <top-header :info="info" />
    <div class="content">
      <!-- <div @click="goNext">
        <van-notice-bar mode="link" :scrollable="false">
          <van-swipe
            vertical
            class="notice-swipe"
            :autoplay="3000"
            :show-indicators="false"
          >
            <van-swipe-item v-for="(lists, index) in orderList" :key="index">{{
              lists.name
            }}</van-swipe-item>
          </van-swipe>
        </van-notice-bar>
      </div> -->
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
            <div class="listBox" v-for="(item, index) in items" :key="index">
              <div class="listTop">
                <img src="../../assets/rocket/fv-img.png" />
                <p class="title">{{ item.name }} | {{ item.level_name }}</p>
                <!-- <div class="level">{{ item.level_name }}</div> -->
                <div class="status end" v-if="item.rocket_status == 0">
                  待<span v-if="item.level == 1">点火</span>
                  <span v-else>推进</span>
                </div>
                <div class="status" v-if="item.rocket_status == 1">
                  <span v-if="item.level == 1">点火</span>
                  <span v-else>推进</span>中
                </div>
                <div class="status complete" v-if="item.rocket_status == 2">
                  <span v-if="item.level == 1">点火</span>
                  <span v-else>推进</span>成功
                </div>
                <div class="status end" v-if="item.rocket_status == 3">
                  已清算
                </div>
                <div class="status end" v-if="item.rocket_status == 4">
                  已清退
                </div>
                <div class="status end" v-if="item.rocket_status == 5">
                  推进失败
                </div>
              </div>
              <div class="listMain">
                <div class="main">
                  <p style="display: none;">
                    燃料总量:<span>{{ item.price - 0 }}&nbsp;Y令牌</span>
                  </p>
                  <p>
                    收益率:<span>{{ item.profit - 0 }}&nbsp;%</span>
                  </p>
                  <p style="display: none;">
                    已&nbsp; 推 &nbsp;进:<span
                      >{{ item.finish_money - 0 }}&nbsp;Y令牌</span
                    >
                  </p>
                  <p>
                    推进率:<span>{{ item.progress }}&nbsp;%</span>
                  </p>

                  <div class="rocket">
                     <van-progress
                      :percentage="item.progress"
                      pivot-text=" "
                      track-color="#A5A5A5"
                      color="#FF461E"
                    />
                  </div>
                  <p class="topbox">
                    可投燃料:<span>
                      {{ item.min_payment - 0 }}~{{
                        item.max_payment - 0
                      }}
                      Y令牌&nbsp;</span
                    >
                  </p>

                  <p class="topbox">
                    消耗Y令牌:<span>{{ item.kmt_rate - 0 }}&nbsp;%</span>
                  </p>
                </div>
              </div>
              <p class="time" v-if="[0,1].includes(item.rocket_status)">起止:{{ item.game_start_time }}</p>
              <div class="buttonBox">
                <div
                  class="button"
                  @click="showPop(1, item.id)"
                  v-if="item.rocket_status == 1"
                >
                  参与<span v-if="item.level == 1">点火</span
                  ><span v-else>推进</span>
                </div>
                <div class="button disable" v-else>
                  参与<span v-if="item.level == 1">点火</span>
                  <span v-else>推进</span>
                </div>
                <!-- <div
                  class="button lineButton yellow"
                  @click="$router.push('/lineup?type=1')"
                >
                  预约排单
                </div> -->
              </div>
            </div>
          </van-list>
        </van-pull-refresh>
      </div>
      <!-- 遮罩层  推进-->
      <van-overlay :show="show">
        <div class="wrapper" v-if="payData">
          <div class="block">
            <img
              src="../../assets/rocket/cwu-img.png"
              @click="showPop"
              class="close"
            />
            <div class="top">
              <img src="../../assets/rocket/fv-img.png" />
              <p class="title">{{ payData.name }} | {{ payData.level_name }}</p>
            </div>
            <p class="txt">燃料总量剩余:&nbsp;{{ payData.balance - 0 }} Y令牌</p>
            <div class="inputBox">
              <input
                type="text"
                :placeholder="`可投燃料:${payData.min_payment - 0} - ${
                  payData.max_payment - 0
                } Y令牌`"
                v-model="rlNum"
                @input="getPayInfo"
              />
              <span>Y令牌</span>
            </div>
            <div class="total">
              <p>{{ ifLine ? "需冻结金额" : "支付金额" }}</p>
              <!-- <p>{{ payData.num - 0 }}&nbsp;USDT</p> -->
              <p>{{ Usdt }}&nbsp;Y令牌</p>
            </div>
            <p class="consumption">
              消耗: <span>{{ payListId == 103 ? mtkNum : "0.0000" }}&nbsp;Y令牌</span>
            </p>
            <div class="payLines"></div>
            <div class="payType" v-if="payListId">
              <p>选择支付方式:</p>
              <div v-for="(item, index) in payList" :key="index" @click="changePay(item.id)" class="payTypeLists">
                <div>
                  <img :src="item.id == payListId ? isCheck : isCheckNo" alt="" />
                </div>
                <div>
                  {{ item.name }}{{item.id == 103 ? `(${item.num - 0} Y令牌)` : `可用(${item.num - 0} Y令牌)` }}
                </div>
              </div>
            </div>
            <p class="warn" v-if="ifLine">
              每关卡在开始前15分钟,可提前预约排队最大额,开启
              后排队推进。下一关需重新预约排队。
            </p>
            <div class="paybutton" @click="goPay" v-if="showBtn">确定</div>
            <!-- <div class="promptBox">
              <p>可用:{{ payData.user_num - 0 }}&nbsp;Y令牌</p>
              <span @click="gofilling">去充值</span>
            </div> -->
            <!-- <p class="prompt">可用:{{ payData.usre_kmt_num - 0 }}&nbsp;Y令牌</p> -->
          </div>
        </div>
      </van-overlay>
    </div>
  </div>
</template>
<script>
import { ark_getList, ark_get_pay_info, ark_Pay, ark_order_index, ark_subscribe, ark_user_info } from '@/http/api.js'
export default {
  data () {
    return {
      info: {
        title: this.$route.query.name,
        isBack: true,
        exit: true,
      },
      show: false,
      ifLine: false,
      rlNum: '',
      activeId: '',
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
        rows: 15,
        goods_id: this.$route.query.id
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
      orderList: [],
      kmtInfo: {},
      showBtn: true,
      payList: [], // 支付方式数组
      payListId: "", // 支付方式id初始值
      isCheck: require("../../assets/rocket/xzrfb-img.png"),
      isCheckNo: require("../../assets/rocket/wzfg-img.png"),
    }
  },
  created () {
    this.onRefresh();
    this.getLists();
    this.getKmtInfo();
  },
  mounted () {
    this.getListTimer = setInterval(() => {
      // this.dataOption.page = 1;
      // this.onLoad();
      this.onRefresh();
    }, 30000)
  },
  destroyed () {
    clearInterval(this.getListTimer);
  },
  // watch: {
  //   rlNum (newValue, oldValue) {
  //     let patten = /^[+-]?(0|([1-9]\d*))(\.\d+)?$/g;
  //     if (!patten.test(newValue)) {
  //       // this.$toast.fail('请输入合适的数值')
  //       this.rlNum = '';
  //       return;
  //     }
  //     // 防抖
  //     if (this.timer) {
  //       clearTimeout(this.timer)
  //     } else {
  //       let timer = setTimeout(() => {
  //         this.getPayInfo();
  //         this.timer = null;
  //       }, 1000)
  //     }
  //   }
  // },
  methods: {
    changePay(_index) {
      if (Number(this.payListId) != Number(_index)) {
        this.payListId = _index;
      }
    },
    toastWarn () {
      // 暂时不上线
      this.$toast('敬请期待');
    },
    getKmtInfo () {
      this.$http.post(ark_user_info, { currency_id: '103' })
        .then(({ data }) => {
          if (data.code == '10000') {
            this.kmtInfo = data.result;
          }
        })
    },
    gofilling () {
      if (this.$platform == "ios") {
        window.webkit.messageHandlers.iosAction.postMessage("exit");
      } else if (this.$platform == "android") {
        apps.gotoWalletCharge();
      }
    },
    // 订单购买人记录
    getLists () {
      let opption = {
        goods_id: this.$route.query.id
      };
      this.$http.post(ark_order_index, opption).then(({ data }) => {
        if (data.code == 10000) {
          this.orderList = data.result;
        }
      });
    },
    goNext () {
      let url = `/notice?id=${this.$route.query.id}`;
      this.$router.push({ path: url });
    },
    getpayFn () {
      this.payObj.num = 0;
      this.payObj.product_id = this.activeId
      this.$http.post(ark_get_pay_info, this.payObj)
        .then(({ data }) => {
          if (data.code == '10000') {
            this.payData = data.result;
            this.payListId = data.result.pay_type[0].id;
            this.payList = [];
            data.result.pay_type.forEach((item, index) =>{
              let obj = {
                id: item.id,
                num: item.num,
                name: '',
              };
              if(item.id == 103) {
                obj.name = 'H账户可用';
              }else {
                obj.name = '预购余额'; 
              }
              // 105是预约池 103是Y令牌
              // 只需要预购余额支付，特殊H账户可用
              if(data.result.is_special == 1) {
                this.payList.push(obj);
              }else {
                if(obj.id == 105) {
                  this.payList.push(obj);
                }
              }
              
            })
            //赋值最大值
            // this.rlNum = String(data.result.max_payment - 0);
            // this.getPayInfo();
          } else {
            this.$toast.fail(data.message)
          }
        })
    },
    getPayInfo () {
      // if (
      //   this.rlNum == "00" ||
      //   this.rlNum.substring(0, 1) == ""
      // ) {
      //   this.rlNum = '';
      // } else {
      //   this.rlNum = this.rlNum.replace(/[^0-9]/g, "");
      // }
      if(this.rlNum == "00") {
        this.rlNum = '0';
      };
      // 限制输入2位小数
      this.rlNum = this.cal.clearNoNum2(this.rlNum);
      this.Usdt = this.rlNum;
      if (this.rlNum == '') { this.Usdt = 0; }
      this.mtkNum = this.cal.accMul(this.rlNum, this.kmtInfo.mtk_rate);
      // this.mtkNum = this.cal.accDiv(num, this.kmtInfo.mtk_price).toFixed(4);
    },

    showPop (close, id, status) {
      //是否预约排队弹窗
      if (status == 0) {
        return;
      } else {
        if (status && status == 1) {
          this.ifLine = true;
        }
        this.show = !this.show;
        if (close == 1) {
          this.activeId = id;
          this.getpayFn();
          this.getPayInfo()
        } else {
          // 恢复初始id
          this.payListId = this.payList[0].id;
          // this.rlNum = '';
        }
      }
    },
    onLoad () {
      this.isUpLoading = true;
      this.$http
        .post(ark_getList, this.dataOption)
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
          this.items = this.items.concat(data.result);
          // this.items = data.result;
          this.items.forEach((item, index, arr) => {
            item.progress = this.getPercent(Number(item.finish_money), Number(item.price));
          })
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
    //计算推进速率
    getPercent (num, total) {
      num = parseFloat(num);
      total = parseFloat(total);
      if (isNaN(num) || isNaN(total)) {
        return "-";
      }
      return total <= 0 ? "0" : Math.round((num / total) * 10000) / 100.0;
    },
    goPay () {
      if (this.rlNum == '') {
        this.$toast.fail('请输入燃料数值');
        return;
      }
      // if (Number(this.rlNum) < Number(this.payData.min_payment)) {
      //   this.$toast.fail(`燃料数值不能小于${this.payData.min_payment - 0}`);
      //   return;
      // }
      if (Number(this.rlNum) > Number(this.payData.max_payment)) {
        this.$toast.fail(`燃料数值不能大于${this.payData.max_payment - 0}`);
        return;
      }

      let obj = {
        product_id: this.activeId,
        num: this.Usdt,
        kmt_num: this.mtkNum,
        type: this.payListId
      }
      // 判断是否预约排队
      if (this.ifLine) {
        this.showBtn = false;
        this.$http.post(ark_subscribe, obj)
          .then(({ data }) => {
            if (data.code == '10000') {
              this.$toast.success(data.message);
              setTimeout(() => {
                this.show = false;
                this.rlNum = '';
                this.onRefresh();
                this.showBtn = true;
                // 重制支付初始id
                this.payListId = this.payList[0].id;
              }, 500)
            } else {
              this.rlNum = '';
              this.showBtn = true;
              this.show = false;
              this.$toast.fail(data.message);
              // 重制支付初始id
                this.payListId = this.payList[0].id;
            }
          })
      } else {
        this.showBtn = false;
        this.$http.post(ark_Pay, obj)
          .then(({ data }) => {
            if (data.code == '10000') {
              this.$toast.success(data.message);
              setTimeout(() => {
                this.rlNum = '';
                this.show = false;
                this.onRefresh();
                this.showBtn = true;
                // 重制支付初始id
                this.payListId = this.payList[0].id;
              }, 500)
            } else {
              this.rlNum = '';
              this.showBtn = true;
              this.show = false;
              this.$toast.fail(data.message);
              // 重制支付初始id
              this.payListId = this.payList[0].id;
            }
          })
      }
    },
  },
}
</script>
<style lang="scss" scoped>
/deep/ header {
  // background: url("../../assets/rocket/headerBg.png") no-repeat center;
  // background-size: 100% 100%;
  background-color: #e94927;
  color: #fff;
  i {
    color: #fff;
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
  color: #151515;
  background: #e94927;

  .scrollList {
    // margin-top: -130px;
    padding: 0 15px;
    box-sizing: border-box;
    z-index: 2;
    .no-data {
      margin-top: 30px;
      text-align: center;
      font-size: 14px;
      color: #eaeaeb;
      font-size: 20px;
    }
    .listBox {
      position: relative;
      margin-top: 10px;
      display: flex;
      flex-direction: column;
      background-color: #ffffff;
      border-radius: 12px;
      padding: 15px 10px;
      box-sizing: border-box;
      .listTop {
        display: flex;
        align-items: center;
        justify-content: space-between;
        img {
          width: 8px;
          height: 8px;
        }
        p {
          margin-left: 10px;
          color: #151515;
          font-weight: bolder;
        }
        .status {
          margin-left: auto;
          // width: 72px;
          padding: 0 17px;
          box-sizing: border-box;
          height: 28px;
          line-height: 28px;
          text-align: center;
          background-color: #f9cc43;
          color: #010101;
          font-size: 14px;
          border-radius: 6px;
        }
        .end {
          background-color: #a5a5a5;
          color: #151515;
        }
        .complete {
          background-color: #439ef9;
          color: #ffffff;
        }
      }
      .listMain {
        width: 100%;
        margin-top: 10px;

        .main {
          // margin-left: 88px;
          display: flex;
          flex-wrap: wrap;
          p {
            width: 50%;
            margin-top: 7px;
            font-size: 12px;
            color: #acacac;
            span {
              color: #212121;
              margin-left: 7px;
              font-size: 14px;
              // font-weight: bolder;
            }
          }
          .topbox {
            margin-top: 60px;
          }
        }
      }
      .progress {
        margin-top: 10px;
        margin-left: 22px;
        font-size: 14px;
        width: 100%;
        text-align: left;
        color: #fff;
      }
      .time {
        margin-top: 15px;
        font-size: 10px;
        width: 100%;
        text-align: center;
        color: #f97b43;
      }
      .buttonBox {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        .button {
          margin-top: 15px;
          font-size: 16px;
          // width: 174px;
          width: 100%;
          height: 32px;
          background: #ff461e;
          border-radius: 23px;
          line-height: 32px;
          text-align: center;
          color: white;
        }
        .disable {
          background: #a5a5a5;
        }
        .lineButton {
          width: 100px;
          background: none;
          background-color: #4d4d4d;
        }
        .yellow {
          background-color: #f99743;
        }
        .orange {
          background-color: #f24a00;
        }
      }
      .rocket {
        height: 40px;
        width: 100%;
        padding: 0 10px;
        box-sizing: border-box;
        position: absolute;
        // transform: rotate(270deg);
        left: 0;
        top: 105px;
        > .van-progress {
          height: 9px;
          background: rgba(5, 55, 99, 1);
          border-radius: 6px;
          width: 100%;
        }
      }
    }
  }
  // 遮罩层样式
  .wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    .block {
      position: relative;
      width: 326px;
      height: 470px;
      border-radius: 12px;
      background-color: #ffffff;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 0 20px;
      box-sizing: border-box;
      .close {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 25px;
        height: 25px;
      }
      .top {
        margin: 35px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        img {
          width: 8px;
          height: 8px;
        }
        p {
          margin-left: 10px;
          color: #151515;
          font-weight: bolder;
        }
      }
      .txt {
        width: 100%;
        text-align: left;
        margin-top: 10px;
      }
      .inputBox {
        position: relative;
        margin-top: 15px;
        width: 286px;
        border: 1px solid #e64a33;
        border-radius: 8px;
        overflow: hidden;
        input {
          // 清除默认样式
          -webkit-appearance: none;
          -moz-appearance: none;
          outline: 0;
          border: none;
          z-index: 3;
          width: 100%;
          height: 46px;
          line-height: 46px;
          padding-left: 15px;
          // padding-right: 100px;
          padding-right: 60px;
          box-sizing: border-box;
          color: #1a1a1a;
          font-size: 18px;
          font-weight: bolder;
          background: #ffffff;
        }
        input::-webkit-input-placeholder {
          color: #bcbcbc;
          font-size: 16px;
          font-weight: bold;
        }
        span {
          position: absolute;
          top: 15px;
          right: 10px;
          font-size: 16px;
          color: #262626;
          // font-weight: bolder;
        }
      }
      .total {
        width: 100%;
        margin-top: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        p:nth-child(2) {
          color: #e64a33;
        }
      }
      .consumption {
        width: 100%;
        text-align: right;
        font-size: 12px;
        margin-top: 15px;
        span {
          font-size: 16px;
          color: #e64a33;
        }
      }.payLines {
        border:1px dashed #D8D8D8;
        margin: 10px 0;
        width: 100%;
      }
      .payType {
        font-size: 14px;
        font-weight: 500;
        width: 100%;
        > p {
          color: #E64A33;
          text-align: left;
        }
        .payTypeLists {
          display: flex;
          font-size: 12px;
          > div {
            margin-top: 10px;
            > img {
              margin-right: 10px;
              width: 14px;
            }
          }
        }
      }
      .warn {
        font-size: 12px;
        color: #ff3636;
        margin-top: 25px;
      }
      .paybutton {
        margin-top: 40px;
        width: 298px;
        height: 39px;
        font-size: 16px;
        font-weight: bold;
        line-height: 39px;
        color: #ffffff;
        text-align: center;
        background-color: #ff461e;
        border-radius: 23px;
      }
      .promptBox {
        width: 100%;
        margin-top: 15px;
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #c3c3c3;
        span {
          color: #ff1212;
        }
      }
      .prompt {
        width: 100%;
        margin-top: 5px;
        font-size: 12px;
        color: #c3c3c3;
      }
    }
  }
}
/deep/ .notice-swipe {
  height: 50px;
  line-height: 50px;
}
/deep/ .van-notice-bar {
  background: rgba(255, 255, 255, 0.43);
  border-radius: 6px;
  color: #101010;
  font-size: 14px;
  font-weight: 500;
  margin: 0 15px;
}
/deep/ .van-progress__pivot {
  background: url("../../assets/rocket/ark.png") no-repeat center !important;
  background-size: 100% 100% !important;
  width: 24.6px;
  min-width: 24.6px;
  height: 20.51px;
  padding: 0px;
  margin-left: 5px;
}
</style>