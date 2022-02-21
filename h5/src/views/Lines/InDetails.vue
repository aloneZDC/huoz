<template>
  <div class="inDetails">
    <top-header :info="info"></top-header>
    <div class="content">
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
                <div class="d-first">
                  <div>
                    <img :src="imgList" alt="" />
                    <span>{{ item.name }}</span>
                  </div>
                  <div>{{ item.level_name }}</div>
                  <div
                    :class="{
                      't1': Number(item.status) == 0,
                      't2': Number(item.status) == 1,
                      't3': Number(item.status) == 2,
                    }"
                  >{{ item.status | getStatus(item.status) }}</div>
                </div>
                <!-- 待结算 -->
                <div class="d-type" v-if="item.status == 0">
                  <div>
                    <span>已投燃料: </span>
                    <span>{{ item.money - 0 }} U</span>
                  </div>
                  <div>
                    <span>预估固态燃料: </span>
                    <span>{{ item.estimate_statics_reward - 0 }} U</span>
                  </div>
                  <div>
                    <span>参与时间: </span>
                    <span>{{ item.add_time }}</span>
                  </div>
                </div>
                <!-- 已结算 -->
                <div class="d-type" v-else-if="item.status == 1">
                  <div>
                    <span>已投燃料: </span>
                    <span>{{ item.money - 0 }} U</span>
                  </div>
                  <div>
                    <span>已退回燃料: </span>
                    <span>{{ item.capital - 0 }} U</span>
                  </div>
                  <div>
                    <span>释放积分燃料: </span>
                    <span>{{ item.integral - 0 }}积分</span>
                  </div>
                  <div>
                    <span>已获固态燃料: </span>
                    <span>{{ item.statics_reward - 0 }} U</span>
                  </div>
                  <div>
                    <span>参与时间: </span>
                    <span>{{ item.add_time }}</span>
                  </div>
                  <div>
                    <span>结算时间: </span>
                    <span>{{ item.settlement_time }}</span>
                  </div>
                </div>

                <!-- 已清算 -->
                <div class="d-type" v-else-if="item.status == 2">
                  <div>
                    <span>已投燃料: </span>
                    <span>{{ item.money - 0 }} U</span>
                  </div>
                  <div>
                    <span>已退回燃料: </span>
                    <span>{{ item.capital - 0 }} U</span>
                  </div>
                  <div>
                    <span>释放积分燃料: </span>
                    <span>{{ item.integral - 0 }}积分</span>
                  </div>
                  <div>
                    <span>已获固态燃料: </span>
                    <span>{{ item.statics_reward - 0 }} U</span>
                  </div>
                  <div>
                    <span>参与时间: </span>
                    <span>{{ item.add_time }}</span>
                  </div>
                  <div>
                    <span>结算时间: </span>
                    <span>{{ item.settlement_time }}</span>
                  </div>
                </div>
              </li>
            </ul>
          </van-list>
        </van-pull-refresh>
      </div>
    </div>
  </div>
</template>

<script>
import { buy_detail } from "@/http/api.js";
export default {
  name: "inDetails",
  components: {},
  data() {
    return {
      info: {
        title: "推进明细",
        isBack: true,
        exit: false,
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
        goods_id: this.$route.query.id,
        page: 1,
        rows: 10,
      },
      imgList: require("../../assets/rocket/fv-img.png"),
    };
  },
  methods: {
    onLoad() {
      this.isUpLoading = true;
      this.$http
        .post(buy_detail, this.dataOption)
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
    onRefresh() {
      this.dataOption.page = 1;
      // 清空列表数据
      this.items = [];
      this.finished = false;
      // 重新加载数据
      // 将 loading 设置为 true，表示处于加载状态
      // this.loading = false;
      this.onLoad();
    },
  },
  filters: {
    getStatus(key) {
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

  created() {
    this.onRefresh();
  },
};
</script>

<style lang="scss" scoped>
/deep/ header {
  background: #0f0f0f;
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
  width: 100%;
  overflow: auto;
  background: #0f0f0f;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang TC";
  padding: 0 15px;
  box-sizing: border-box;
  font-weight: 500;
  .detail {
    width: 100%;
    background: #191919;
    border: 1px solid #f9cc43;
    padding: 25px 22px;
    box-sizing: border-box;
    border-radius: 6px;
    margin-bottom: 15px;
    > .d-first {
      display: flex;
      align-items: center;
      > div:first-child {
        font-size: 16px;
        color: #fff;
        font-weight: bold;
        display: flex;
        > img {
          width: 23px;
        }
      }
      > div:nth-child(2) {
        min-width: 64px;
        width: fit-content;
        height: 26px;
        border: 1px solid #f9cc43;
        border-radius: 6px;
        color: #f9cc43;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 0 5px;
        margin-left: 20px;
        font-size: 14px;
      }
      > div:nth-child(3) {
        margin-left: auto;
        width: 72px;
        height: 28px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 6px;
        display: flex;
        justify-content: center;
        align-items: center;
      }
      > .t1 {
        background: #f9cc43;
        color: #010101;
      }
      > .t2 {
        background: #a5a5a5;
        color: #fff;
      }
      > .t3 {
        background: #fa7d00;
        color: #fff;
      }
    }
    > .d-type {
      margin-top: 23px;
      font-size: 14px;
      font-weight: 400;
      color: #fff;
      > div {
        margin-bottom: 7px;
        > span:last-child {
          font-size: 16px;
          font-weight: 500;
        }
      }
      > div:last-child {
        margin-bottom: 0;
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
  
}
</style>