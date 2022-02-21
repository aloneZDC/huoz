<template>
  <div class="dets">
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
                <!-- 幸运仓 -->
                <div class="d-type" v-if="$route.query.type == 1">
                  <div>
                    <div>
                      <span>{{ item.name }} </span>
                      <span
                        >{{ item.level_name
                        }}<span>{{ item.status == 2 ? "成功" : "失败" }}</span>
                      </span>
                    </div>
                    <p>{{ item.game_start_time }}</p>
                  </div>
                  <p :class="item.status == '2' ? 't2' : 't1'">
                    <span>{{ item.warehouse }}</span>
                    <span>L令牌</span>
                  </p>
                </div>
                <!-- 市值仓和工具仓 -->
                <div class="d-types" v-else>
                  <div>
                    <span>{{ item.name }} {{ item.level_name }}</span>
                    <!-- <span>1号火箭 第1期</span> -->
                    <span></span>
                  </div>
                  <p>
                    <span>{{ item.warehouse }}</span>
                    <span>U</span>
                  </p>
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
import { ark_rocket_log } from "@/http/api.js";
export default {
  name: "dets",
  components: {},
  data() {
    return {
      info: {
        title: "详情",
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
        type: this.$route.query.type,
      },
      imgList: require("../../assets/rocket/fv-img.png"),
    };
  },
  methods: {
    onLoad() {
      this.isUpLoading = true;
      this.$http
        .post(ark_rocket_log, this.dataOption)
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
  created() {
    this.onRefresh();
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
  background: #fff;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang TC";
  padding: 0 15px;
  box-sizing: border-box;
  font-weight: 500;
  .detail {
    width: 100%;
    background: #EDEDED;
    border-radius: 12px;
    padding: 20px 15px;
    box-sizing: border-box;
    margin-bottom: 10px;
    > .d-type {
      font-weight: 500;
      color: #000;
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
          font-weight: bold;
          margin-right: 5px;
        }
        > span:nth-child(2) {
          font-size: 14px;
        }
      }
    }
    > .d-types {
      font-weight: 500;
      color: #fff;
      font-size: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      > div {
        font-size: 16px;
      }
      > p {
        color: #F9CC43;
        > span:nth-child(1) {
          font-size: 18px;
          font-weight: bold;
          margin-right: 5px;
        }
        > span:nth-child(2) {
          font-size: 14px;
        }
      }
    }
    .t1 {
      color: #FF1D1D;
    }
    .t2 {
      color: #0058FF;
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