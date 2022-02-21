
<template>
  <div class="advance">
  <top-header :info="info"></top-header>
    <div class="content">
      <div class="scroll">
        <van-tabs v-model="active" @click="tabHandler" swipeable>
          <van-tab
            v-for="(tabName, idx) in tabLabels"
            :key="idx"
            :title="tabName.label"
          ></van-tab>
          <div class="scroll-list" v-if="active == 0">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.yesterday_share_reward - 0 }}</p>
                <span>昨日分享奖 (火米)</span>
              </div>
              <div>
                <p>{{ dataConfig.s_share_reward - 0 }}</p>
                <span>累计分享奖 (火米)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.total_first_reward - 0 }}</p>
                <span>昨日1D预充 (火米)</span>
              </div>
              <div>
                <p>{{ dataConfig.total_second_reward - 0 }}</p>
                <span>昨日2D预充 (火米)</span>
              </div>
            </div>
            <van-divider>记录明细</van-divider>
            <van-pull-refresh
              v-model="oneDownLoading"
              @refresh="onRefresh('one')"
              success-text="刷新成功"
            >
              <van-list
                v-model="oneUpLoading"
                :finished="oneFinished"
                :finished-text="finishedText"
                @load="onLoad('one')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="isone" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li class="detail" v-for="(item, index) in one" :key="index">
                    <div class="detail-type">
                      <div>
                        <p>{{ item.type_name }}</p>
                        <span>{{ item.add_time }}</span>
                      </div>
                      <div>
                        <span>{{ item.reward }}</span>
                        <span>{{ item.currency_name }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div>
          <div class="scroll-list" v-if="active == 1">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.yesterday_team_reward - 0}}</p>
                <span>昨日管理奖 (火米)</span>
              </div>
              <div>
                <p>{{ dataConfig.s_team_reward - 0}}</p>
                <span>累计管理奖+辅导奖 (火米)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.yesterday_subscribe - 0 }}</p>
                <span>昨日团队预充 (火米)</span>
              </div>
 
              <div>
                <p>{{ dataConfig.total_subscribe - 0 }}</p>
                <span>累计团队预充 (火米)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.yesterday_flat_reward - 0 }}</p>
                <span>昨日辅导奖（火米）</span>
              </div>
              <div>
                <p>{{ dataConfig.yesterday_flat_subscribe - 0 }}</p>
                <span>昨日辅导预充 (火米)</span>
              </div>
            </div>
            <van-divider>记录明细</van-divider>
            <van-pull-refresh
              v-model="twoDownLoading"
              @refresh="onRefresh('two')"
              success-text="刷新成功"
            >
              <van-list
                v-model="twoUpLoading"
                :finished="twoFinished"
                :finished-text="finishedText"
                @load="onLoad('two')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="istwo" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li class="detail" v-for="(item, index) in two" :key="index">
                    <div class="detail-type">
                      <div>
                        <p>{{ item.type_name }}</p>
                        <span>{{ item.add_time }}</span>
                      </div>
                      <div>
                        <span>{{ item.reward }}</span>
                        <span>{{ item.currency_name }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div>
          <div class="scroll-list" v-if="active == 2">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.yesterday_centre_reward - 0 }}</p>
                <span>昨日服务津贴 (火米)</span>
              </div>
              <div>
                <p>{{ dataConfig.total_centre_reward - 0 }}</p>
                <span>累计服务津贴 (火米)</span>
              </div>
            </div>
            <van-divider>记录明细</van-divider>
            <van-pull-refresh
              v-model="threeDownLoading"
              @refresh="onRefresh('three')"
              success-text="刷新成功"
            >
              <van-list
                v-model="threeUpLoading"
                :finished="threeFinished"
                :finished-text="finishedText"
                @load="onLoad('three')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="isthree" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li
                    class="detail"
                    v-for="(item, index) in three"
                    :key="index"
                  >
                    <div class="detail-type">
                      <div>
                        <p>{{ item.type_name }}</p>
                        <span>{{ item.add_time }}</span>
                      </div>
                      <div>
                        <span>{{ item.reward }}</span>
                        <span>{{ item.currency_name }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div>
        </van-tabs>
      </div>
    </div>
  </div>
</template>

<script>
import { y_subscribe_info, y_subscribe_help_log, y_subscribe_power_log, y_centre_log } from "@/http/api.js";
export default {
  data () {
    return {
      info: {
        title: "预购奖励",
        isBack: true,
        exit: true,
      },
      oneUpLoading: false, //上拉加载
      oneFinished: false, //上拉加载完毕
      oneDownLoading: false, //下拉刷新
      twoUpLoading: false, //上拉加载
      twoFinished: false, //上拉加载完毕
      twoDownLoading: false, //下拉刷新
      threeUpLoading: false, //上拉加载
      threeFinished: false, //上拉加载完毕
      threeDownLoading: false, //下拉刷新
      finishedText: "沒有更多了",
      noDataImg: require("../../assets/rocket/fg-img.png"),
      offset: 100,
      isone: false,
      istwo: false,
      isthree: false,
      one: [],
      two: [],
      three: [],
      oneOption: {
        type: "0",
        page: 1,
        rows: 10,
      },
      twoOption: {
        type: "1",
        page: 1,
        rows: 10,
      },
      threeOption: {
        type: "3",
        page: 1,
        rows: 10,
      },
      tabLabels: [
        {
          label: "分享奖",
          isFirst: true,
          type: "one",
        },
        {
          label: "管理奖",
          isFirst: true,
          type: "two",
        },
        {
          label: "服务津贴",
          isFirst: true,
          type: "three",
        },
      ],
      active: Number(localStorage.getItem("inreId"))
        ? Number(localStorage.getItem("inreId"))
        : 0,
      dataConfig: {},
      allNum: "",
    }
  },
  methods: {
    _buy_info () {
      this.$http.post(y_subscribe_info).then(({ data }) => {
        if (data.code == 10000) {
          this.dataConfig = {
            ...data.result,
          };
          // this.allNum = this.cal.accAdd(this.dataConfig.max_team_total, this.dataConfig.team_total);
        }
      });
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
        let key = item.type + "Option";
        this[key].page = 1;

        // this.tabLabels[this.active].isFirst = false;
        if (index == idx) {
          this.onRefresh(item.type);
        }
      });
      this.active = idx;
      // 存儲ID
      this.$nextTick(() => {
        localStorage.setItem("inreId", idx);
      });
    },
    fetchList (obj) {
      let url = "";
      if (obj.type == "0") {
        url = y_subscribe_help_log;
      } else if (obj.type == "1") {
        url = y_subscribe_power_log;
      } else {
        url = y_centre_log;
      }
      return new Promise((resolve, reject) => {
        let option = {
          ...obj,
        };
        this.$http.post(url, option).then(({ data }) => {
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
  created () {
    this._buy_info();
  }
};
</script>

<style lang="scss" scoped>
/deep/ .van-tabs__nav {
  background: transparent;
}
/deep/ .van-tab {
  font-size: 16px;
  color: #6f6f6f;
}
/deep/ .van-tab--active {
  color: #ff461e;
}
/deep/ .van-tabs__line {
  background: #ff461e;
}
/deep/ .van-tabs__wrap {
  padding-top: 10px;
}
/deep/ .van-divider {
  color: #000000;
  border-color: #000000;
  padding: 0 50px;
  margin-bottom: 0;
  font-size: 17px;
}
/deep/ header {
  background: #f5f5f5;
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
  // overflow: auto;
  // margin-top: 44px;
  background: #f5f5f5;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang TC";
  padding: 0 15px;
  box-sizing: border-box;
  font-weight: 500;

  .detail {
    padding: 20px 15px;
    box-sizing: border-box;
    background: #ededed;
    box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.16);
    border-radius: 8px;
    margin-bottom: 10px;
    > .fisrt-type {
      color: #2c2c2c;
      font-weight: 500;
      > h4 {
        font-size: 18px;
        font-weight: 500;
      }
      > div:nth-child(2) {
        font-size: 16px;
        padding-top: 13px;
        > span:nth-child(1) {
          color: #7e7e7e;
        }
      }
      > .types {
        padding-top: 10px;
        font-size: 14px;
        display: flex;
        justify-content: space-between;
        > div {
          > span:nth-child(1) {
            color: #6f6f6f;
          }
        }
      }
      > .types-line {
        padding-top: 10px;
        font-size: 14px;
        > span:nth-child(1) {
          color: #6f6f6f;
        }
      }
      > .types-l {
        padding-top: 10px;
        border-bottom: 1px solid #6e6e6e;
      }
      > div:last-child {
        font-size: 14px;
        padding-top: 10px;
        > span:nth-child(1) {
          color: #adadad;
        }
      }
    }
    .detail-type {
      display: flex;
      justify-content: space-between;
      align-items: center;
      > div:nth-child(1) {
        font-size: 16px;
        color: #2c2c2c;
        > span {
          font-size: 12px;
          color: #6f6f6f;
        }
      }
      > div:nth-child(2) {
        font-size: 18px;
        color: #2c2c2c;
        > span:last-child {
          margin-left: 5px;
          font-size: 14px;
          color: #6f6f6f;
        }
      }
    }
  }
  .list-wrap {
    margin-top: 15px;
    .no-data {
      margin-top: 60px;
      text-align: center;
    }
    > li:last-child {
      margin-bottom: 0;
    }
  }
}
.scroll-list-type {
  width: 100%;
  height: 73px;
  background: #ededed;
  border-radius: 12px;
  margin-bottom: 10px;
  display: flex;
  justify-content: space-around;
  align-items: center;
  > div {
    font-size: 16px;
    font-weight: bold;
    color: #2c2c2c;
    text-align: center;
    > p {
      margin-bottom: 6px;
    }
    > span {
      font-size: 12px;
      font-weight: 400;
      color: #6f6f6f;
    }
  }
}
.scroll-list-type-fire {
  justify-content: left;
  padding: 0px 30px;
  box-sizing: border-box;
}
.scroll-list {
  margin-top: 30px;
}
</style>  