<template>
  <div class="dets">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="accountBox">
        <div class="account" v-if="infoData">
          <div class="accountTitle">{{ infoData[1].currency_name }}</div>
          <div class="accountNum">{{ infoData[1].num - 0 }} Y令牌</div>
        </div>
        <div class="exchange" @click="showPop">划转</div>
      </div>
      <div class="recordTitle">———&nbsp;记录明细&nbsp;———</div>
      <div class="scroll">
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
            <ul class="list-warp">
              <div v-show="isNo" class="no-data">
                <img :src="noDataImg" />
              </div>
              <li class="detail" v-for="(item, index) in items" :key="index">
                <div class="d-type">
                  <div>
                    <div>
                      <span>{{ item.title }} </span>
                    </div>
                    <p>{{ item.add_time }}</p>
                  </div>
                  <p>
                    <span>{{ item.num }}</span>
                    <span>Y令牌</span>
                  </p>
                </div>
              </li>
            </ul>
          </van-list>
        </van-pull-refresh>
      </div>
      <van-popup
        v-model="show"
        position="bottom"
        round
      >
        <div class="block">
          <img
            src="../../assets/rocket/cwu-img.png"
            @click="showPop"
            class="close"
          />
          <div class="title">划转</div>
          <div class="warn">提示:便捷账户只能T+1转出至钱包账户</div>
          <div class="tabBox">
            <div class="left df">
              <p>从</p>
              <img src="../../assets/rocket/efug-img.png" />
              <p>到</p>
            </div>
            <div class="main df">
              <p>{{ judge ? "钱包账户" : "便捷账户" }}</p>
              <p>{{ judge ? "便捷账户" : "钱包账户" }}</p>
            </div>
            <div class="right">
              <img
                src="../../assets/rocket/eg-img.png"
                @click="handeleSwitch"
              />
            </div>
          </div>
          <div class="inputBox">
            <p>数量</p>
            <!-- <input type="text" placeholder="输入数量" v-model="Num" /> -->
            <div class="num">500</div>
            <span>Y令牌</span>
          </div>
          <div class="balance" v-if="infoData">
            可用余额:{{
              judge ? infoData[0].num - 0 : infoData[1].num - 0
            }}
            Y令牌
          </div>
          <div class="exchangeBtn" @click="submitPay">确定划转</div>
        </div>
      </van-popup>
    </div>
  </div>
</template>

<script>  
import { ark_transfer_info, ark_add_transfer, ark_transfer_log } from "@/http/api.js";
export default {
  name: "dets",
  components: {},
  data () {
    return {
      info: {
        title: "便捷通道",
        isBack: true,
        exit: true,
      },
      textStr: "",
      items: [],
      isUpLoading: false, //上拉加载
      finished: false, //上拉加载完毕
      isDownLoading: false, //下拉刷新
      isNo: false,
      offset: 100,
      finishedText: "没有更多了",
      noDataImg: require("../../assets/rocket/fg-img.png"),
      dataOption: {
        goods_id: 2,
        page: 1,
        rows: 10,
        type: 1,
      },
      imgList: require("../../assets/rocket/fv-img.png"),
      show: false,
      Num: '',
      judge: true,
      infoData: '',
    };
  },
  created () {
    this.onRefresh();
    this.getInfo();
  },
  methods: {
    getInfo () {
      this.$http.post(ark_transfer_info)
        .then(({ data }) => {
          this.infoData = data.result;
        })
    },
    onLoad () {
      this.isUpLoading = true;
      this.$http
        .post(ark_transfer_log, this.dataOption)
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
          if (data.result.length < this.dataOption.rows) {
            this.finished = true;
            return;
          } else {
            this.finished = false;
          }
          // this.finished = false;
          this.dataOption.page += 1;
        })
        .catch((err) => {
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
    showPop () {
      this.show = !this.show;
      this.Num = '';
    },
    handeleSwitch () {
      this.judge = !this.judge;
      this.Num = '';
    },
    submitPay () {
      let obj = {
        currency_id : this.judge ? this.infoData[1].currency_id : this.infoData[0].currency_id,
        out_currency_id : this.judge ? this.infoData[0].currency_id : this.infoData[1].currency_id,
        num:500
      }
      this.$http.post(ark_add_transfer, obj)
        .then(({ data }) => {
        if (data.code == "10000") {
          this.$toast.success(data.message);
          setTimeout(() => {
            this.show = false;
            this.getInfo();
            this.onRefresh();
          }, 500);
        } else {
          this.$toast.fail(data.message);
        }
      })
    }
  },

};
</script>

<style lang="scss" scoped>
/deep/ header {
  background: #fff;
  color: #0f0f0f;
  i {
    color: #0f0f0f;
  }
}
.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  overflow: auto;
  background: #FFFFFF;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang TC";
  padding: 0 15px;
  box-sizing: border-box;
  font-weight: 500;
  .accountBox {
    margin-top: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 15px;
    box-sizing: border-box;
    width: 345px;
    height: 101px;
    background: linear-gradient(180deg, #FFE6E0 0%, #FF461E 100%);
    border-radius: 12px;
    .account {
      display: flex;
      flex-direction: column;
      align-items: center;
      color: #0f0f0f;
      font-size: 16px;
      .accountTitle {
        margin-bottom: 7px;
      }
      .accountNum {
        font-weight: bolder;
      }
    }
    .exchange {
      width: 90px;
      height: 33px;
      line-height: 33px;
      text-align: center;
      background: #FFD065;
      border-radius: 8px;
      color: #fff;
      font-size: 16px;
    }
  }
  .recordTitle {
    margin: 25px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    color: #000;
  }
  .detail {
    width: 100%;
    height: 90px;
    background: #FFD1C7;
    border-radius: 12px;
    padding: 20px 15px;
    box-sizing: border-box;
    margin-bottom: 10px;
    > .d-type {
      font-weight: 500;
      color: #000000;
      font-size: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      > div:nth-child(1) {
        > div {
          font-size: 16px;
        }
        > p {
          margin-top: 10px;
          font-size: 12px;
          color: #999999;
        }
      }
      > p {
        > span:nth-child(1) {
          font-size: 18px;
          // font-weight: bold;
          margin-right: 5px;
        }
        > span:nth-child(2) {
          font-size: 14px;
          color: #999999;
        }
      }
    }
  }
  .list-warp {
    margin-top: 10px;
    > li:last-child {
      margin-bottom: 0;
    }
    .no-data {
      margin-top: 60px;
      text-align: center;
    }
  }
  // 遮罩层样式
  // .wrapper {
  //   display: flex;
  //   align-items: center;
  //   justify-content: center;
  //   height: 100%;
  .block {
    position: relative;
    // height: 450px;
    border-radius: 12px;
    background-color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 15px;
    box-sizing: border-box;
    .close {
      position: absolute;
      top: 15px;
      right: 15px;
      width: 25px;
      height: 25px;
    }
    .title {
      margin-top: 20px;
      font-size: 16px;
      color: #000000;
    }
    .warn {
      margin-top: 15px;
      font-size: 14px;
      color: #fc4f4f;
    }
    .tabBox {
      margin-top: 25px;
      width: 100%;
      display: flex;
      align-items: center;
      height: 134px;
      background-color: #FFD1C7;
      padding: 10px 0;
      box-sizing: border-box;
      border-radius: 8px;
      .df {
        display: flex;
        align-items: center;
        flex-direction: column;
        height: 100%;
        justify-content: space-between;
      }
      .left {
        margin-left: 20px;
        font-size: 16px;
        color: #000000;
        img {
          width: 6px;
          height: 46px;
        }
      }
      .main {
        margin-left: 55px;
        width: 160px;
        p {
          width: 100%;
          display: flex;
          align-items: center;
          height: 50%;
          font-size: 16px;
          color: #000000;
        }
        p:first-child {
          border-bottom: 1px solid #6e6e6e;
        }
      }
      .right {
        margin-left: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        img {
          width: 27.59px;
          height: 26.98px;
        }
      }
    }
    .inputBox {
      margin-top: 15px;
      width: 100%;
      height: 50px;
      display: flex;
      align-items: center;
      border-bottom: 1px solid #6e6e6e;
      p {
        margin-left: 10px;
        font-size: 14px;
        color: #c9c9c9;
      }
      span {
        margin-left: auto;
        font-size: 16px;
        color: #000 ;
        // font-weight: bolder;
      }
      input {
        // 清除默认样式
        -webkit-appearance: none;
        -moz-appearance: none;
        outline: 0;
        border: none;
        z-index: 3;
        width: 140px;
        height: 46px;
        line-height: 46px;
        padding-left: 15px;
        padding-right: 100px;
        color: #000;
        font-size: 18px;
        font-weight: bolder;
        background: #2c281c;
      }
      input::-webkit-input-placeholder {
        color: #c9c9c9;
        font-size: 16px;
      }
      .num {
        margin-left: 40px;
        color: #000;
        font-size: 16px;
        font-weight: bolder;
      }
    }
    .balance {
      margin-top: 10px;
      font-size: 12px;
      color: #c3c3c3;
      width: 100%;
      text-align: left;
    }
    .exchangeBtn {
      margin: 25px 0;
      width: 315px;
      height: 44px;
      line-height: 44px;
      text-align: center;
      background: #FF461E;
      border-radius: 22px;
      font-size: 13px;
      color: #fff;
    }
  }
  // }
}
</style>