<template>
  <div class="advance">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="scroll">
        <van-tabs abs v-model="active" @click="tabHandler">
          <van-tab
            v-for="(tabName, idx) in tabLabels"
            :key="idx"
            :title="tabName.label"
          ></van-tab>
          <div class="scroll-list">
            <van-pull-refresh
              v-model="onDownLoading"
              @refresh="onRefresh(type)"
              success-text="刷新成功"
            >
              <van-list
                v-model="onUpLoading"
                :finished="onFinished"
                :finished-text="finishedText"
                @load="onLoad(type)"
                :offset="offset"
              >
                <div class="lists">
                  <div>
                    <span>累计固态燃料 (L令牌)</span>
                    <p>{{ total_num - 0 }}</p>
                  </div>
                  <div>
                    <span>可用L令牌 (L令牌)</span>
                    <p>{{ integral_num - 0 }}</p>
                  </div>
                </div>
                <div v-show="isNo" class="no-data">
                  <img :src="noDataImg" />
                </div>
                <ul class="list-wrap">
                  <li
                    class="detail"
                    v-for="(item, index) in items"
                    :key="index"
                  >
                    <div class="listTop">
                      <img src="../../assets/rocket/fv-img.png" />
                      <p class="title">
                        {{ item.name }} | {{ item.level_name }}
                      </p>
                      <div
                        class="status"
                        :class="{
                          t1: Number(item.status) == 0,
                          t2: Number(item.status) == 1,
                          t3: Number(item.status) == 2,
                        }"
                      >
                        {{ item.status | getStatus(item.status) }}
                      </div>
                    </div>
                    <div class="listMain">
                      <p>
                        类型:<span>{{ item.type_name }}</span>
                      </p>
                      <p>
                        已投燃料:<span>{{ item.money - 0 }} Y令牌</span>
                      </p>
                      <p v-show="item.status != 0">
                        已退燃料:<span>{{ item.capital - 0 }} Y令牌</span>
                      </p>
                      <p v-show="item.status == 0">
                        预估固态燃料:<span
                          >{{ item.estimate_statics_reward - 0 }} L令牌</span
                        >
                      </p>
                      <p v-show="item.status == 1">
                        已获固态燃料:<span
                          >{{ item.statics_reward - 0 }} L令牌</span
                        >
                      </p>
                      <!-- <p v-show="item.status == 2">
                        释放L令牌:<span>{{ item.integral - 0 }} L令牌</span>
                      </p> -->
                      <p>
                        参与时间:<span>{{ item.add_time }}</span>
                      </p>
                      <p v-show="item.status != 0">
                        结算时间:<span>{{ item.settlement_time }}</span>
                      </p>
                      <!-- <p>
                        自动:<span class="autoTxt">{{ item.auto_name }}</span>
                      </p> -->
                    </div>
                    <!-- <div
                      class="autoBox"
                      :class="{ disable: item.show_status == 0 }"
                      @click="showPop(item.show_status, item.id)"
                    >
                      {{ item.auto_status == 1 ? "自动中" : "已取消" }}
                    </div> -->
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div>
        </van-tabs>
      </div>
      <!-- 遮罩层  推进-->
      <van-overlay :show="show">
        <div class="wrapper">
          <div class="block">
            <img
              src="../../assets/rocket/cwu-img.png"
              @click="show = false"
              class="close"
            />
            <p>提示</p>
            <p class="warn">您确定要取消自动吗?</p>
            <div class="buttonBox">
              <div class="button" @click="cancelAuto">是</div>
              <div class="button non" @click="show = false">否</div>
            </div>
          </div>
        </div>
      </van-overlay>
    </div>
  </div>
</template>

<script>
import { ark_my_advance, ark_sub_cancel } from "@/http/api.js";
import Income from '@/components/Income.vue';
export default {
  name: "advance",
  components: {
    Income
  },
  data () {
    return {
      info: {
        title: '我的推进',
        isBack: true,
        exit: true,
      },
      onUpLoading: false,
      onDownLoading: false,
      onFinished: false,
      finishedText: "沒有更多了",
      noDataImg: require("../../assets/rocket/fg-img.png"),
      offset: 100,
      isNo: false,
      items: [],
      oneOption: {
        type: "1",
        page: 1,
        rows: 10,
      },
      twoOption: {
        type: "2",
        page: 1,
        rows: 10,
      },
      threeOption: {
        type: "3",
        page: 1,
        rows: 10,
      },
      fourOption: {
        type: "4",
        page: 1,
        rows: 10,
      },
      tabLabels: [
        {
          label: "全部",
          isFirst: true,
          type: "one",
        },
        {
          label: "待结算",
          isFirst: true,
          type: "two",
        },
        {
          label: "已清算",
          isFirst: true,
          type: "three",
        },
        {
          label: "已清退",
          isFirst: true,
          type: "four",
        },
      ],
      active: Number(localStorage.getItem("advanceId"))
        ? Number(localStorage.getItem("advanceId"))
        : 0,
      dataConfig: {},
      total_num: '',
      integral_num: '',
      type: '',
      show: false,
      id: '',
    };
  },
  filters: {
    getStatus (key) {
      let status = "";
      switch (key) {
        case 0:
          status = "待结算";
          break;
        case 1:
          status = "已清算";
          break;
        case 2:
          status = "已清退";
          break;
      }
      return status;
    },
  },
  methods: {
    cancelAuto () {
      let obj = {
        order_id: this.id
      }
      this.$http.post(ark_sub_cancel, obj)
        .then(({ data }) => {
          if (data.code == '10000') {
            this.$toast.success(data.message);
            setTimeout(() => {
              this.show = false;
              this.onRefresh(this.type);
            }, 500)
          } else {
            this.$toast.fail(data.message);
          }
        })
    },
    showPop (status, id) {
      if (status == 0) return;
      this.show = true;
      this.id = id;
    },
    goNext () {
      this.$router.push({ path: "/income" });
    },
    tabHandler (idx) {
      this.tabLabels.forEach((item, index) => {
        let key = item.type + "Option";
        this[key].page = 1;

        // this.tabLabels[this.active].isFirst = false;
        if (index == idx) {
          this.type = item.type;
          this.onRefresh(item.type);
        }
      });
      this.active = idx;
      // 存儲ID
      this.$nextTick(() => {
        localStorage.setItem("incomeId", idx);
      });
    },
    onLoad (type) {
      let key = type + "Option";
      this.onUpLoading = true;
      this.fetchList(this[key]).then((data) => {
        this.onUpLoading = false;
        this.onDownLoading = false;
        this.items = this.items.concat(data);
        if (this.items.length == 0) {
          this.isNo = true;
        }
        this[key].page++;
        this.onFinished = false;
        if (data.length < this[key].rows) {
          // 數據小于10條，已全部加載完畢finished設置爲true
          this.onFinished = true;
          return;
        }
      });
    },
    onRefresh (type) {
      let key = type + "Option";
      this[key].page = 1;
      this.items = [];
      this.isNo = false;
      this.onLoad(type);
    },
    fetchList (obj) {
      return new Promise((resolve, reject) => {
        let option = {
          ...obj,
        };
        this.$http.post(ark_my_advance, option).then(({ data }) => {
          if (data.code == 10000) {
            if (data.result.length >= 0) {
              const { total_num, integral_num } = data;
              this.total_num = total_num;
              this.integral_num = integral_num;
              resolve(data.result);
            }
          } else {
            resolve([]);
          }
        });
      });
    },
  },
  created () {
    if (this.active == 0) { this.type = 'one' };
    if (this.active == 1) { this.type = 'two' };
    if (this.active == 3) { this.type = 'three' };
    if (this.active == 4) { this.type = 'four' };
  },
};
</script>

<style lang="scss" scoped>
/deep/ header {
  background: #f5f5f5;
  color: #000000;
  i {
    color: #000000;
  }
}
/deep/ .van-tabs__nav {
  background: transparent;
}
/deep/ .van-tab {
  font-size: 18px;
  color: #7c7c7c;
}
/deep/ .van-tab--active {
  color: #e64a33;
}
/deep/ .van-tabs__line {
  background: #e64a33;
}
/deep/ .van-tabs--line .van-tabs__wrap {
  // height: 40px;
  position: fixed;
  margin: 0 12px;
  left: 0;
  right: 0;
  // z-index: 100;
  background: #f5f5f5;
}

/deep/ .van-tabs__content {
  padding-top: 34px;
}

/deep/ .van-tab:nth-child(2) {
  border-left: none;
  border-right: none;
}
/deep/ .van-tab:nth-child(3) {
  border-right: none;
}

.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  overflow: auto;
  background: #f5f5f5;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang TC";
  padding: 0 15px;
  box-sizing: border-box;
  font-weight: 500;
  // 遮罩层样式
  .wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    .block {
      position: relative;
      width: 326px;
      height: 177px;
      border-radius: 12px;
      background-color: #ffffff;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 0 20px;
      padding-top: 40px;
      box-sizing: border-box;
      .close {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 25px;
        height: 25px;
      }
      p {
        font-size: 16px;
        color: #000000;
      }
      .warn {
        margin-top: 20px;
        font-size: 14px;
      }
      .buttonBox {
        margin-top: 20px;
        display: flex;
        align-items: center;
        .button {
          width: 61px;
          height: 31px;
          line-height: 31px;
          text-align: center;
          background: #40cffa;
          color: white;
          border-radius: 6px;
        }
        .non {
          margin-left: 100px;
          background: #ff461e;
        }
      }
    }
  }
  .lists {
    margin-top: 23px;
    height: 74px;
    background: #ff461e;
    border-radius: 12px;
    display: flex;
    justify-content: space-around;
    align-items: center;
    div {
      font-size: 18px;
      font-weight: bold;
      color: #fff;
      text-align: center;
      span {
        font-size: 12px;
        font-weight: 500;
      }
    }
  }
  .list-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    .detail {
      position: relative;
      margin-top: 10px;
      width: 100%;
      background: #ffffff;
      border-radius: 12px;
      padding: 20px;
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
          font-size: 16px;
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
          color: #ffffff;
          font-size: 14px;
          border-radius: 6px;
        }
        .t1 {
          background: #f9cc43;
          color: #ffffff;
        }
        .t2 {
          background: #bebebe;
          color: #fff;
        }
        .t3 {
          background: linear-gradient(180deg, #fff7f6 0%, #f86d4f 100%);
          color: #fff;
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
        display: flex;
        flex-direction: column;
        p {
          margin-top: 8px;
          color: #acacac;
          font-size: 14px;
          span {
            margin-left: 5px;
            color: #212121;
            font-size: 16px;
            // font-weight: bold; 
          }
          .autoTxt {
            color: #acacac;
          }
        }
      }
      .autoBox {
        position: absolute;
        bottom: 15px;
        right: 21px;
        width: 72px;
        height: 28px;
        line-height: 28px;
        text-align: center;
        background: #32b7de;
        color: white;
        border-radius: 6px;
      }
      .disable {
        background-color: #bfbfbf;
      }
    }
  }
}
.no-data {
  margin-top: 60px;
  text-align: center;
}
</style>