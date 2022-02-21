<template>
  <div class="notice">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="scroll-list">
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
            <ul class="list-wrap">
              <div v-show="isNo" class="no-data">
                <img :src="noDataImg" />
              </div>
              <li
                class="detail"
                v-for="(item, index) in items"
                :key="index"
                @click="jumpNews(item.article_id)"
              >
                <div class="quantity">
                  <div>
                    <img src="../../assets/shop/lab-img.png" alt="" />
                  </div>
                  <div class="van-ellipsis">
                    {{ item.title }}
                    <p>{{ item.add_time }}</p>
                  </div>
                  <div>
                    <i class="iconfont icon-return"></i>
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
import TopHeader from "@/components/TopHeader";
import { shop_new_list } from "@/http/api.js";
export default {
  name: "highCont",
  components: {
    TopHeader,
  },
  data() {
    return {
      info: {
        title: "公告",
        isBack: true,
        exit: true,
      },
      items: [],
      isUpLoading: false, //上拉加载
      finished: false, //上拉加载完毕
      isDownLoading: false, //下拉刷新
      isNo: false,
      offset: 100,
      finishedText: "没有更多了",
      noDataImg: require("../../assets/shop/no_data.png"),
      dataOption: {
        page: 1,
        rows: 10,
      },
    };
  },
  methods: {
    onLoad() {
      this.isUpLoading = true;
      this.$http
        .post(shop_new_list, this.dataOption)
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
    jumpNews(_id) {
      window.location.href = this.$mobileFrom + "/mobile/News/detail/id/" + _id + "/refer/helps";
    },
  },
  created() {
    this.onRefresh();
  },
};
</script>

<style lang="scss" scoped>
.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  overflow: auto;
  background: #ededed;
  -webkit-overflow-scrolling: touch;
  font-size: "PingFang TC";
  padding: 0 12px;
  box-sizing: border-box;
  z-index: 2;
  .list-wrap {
    padding-top: 14px;
    text-align: center;
  }
  .detail {
    width: 100%;
    height: 60px;
    background: #ffffff;
    border-radius: 6px;
    font-size: 14px;
    font-family: "PingFang SC";
    font-weight: 500;
    color: #212121;
    display: flex;
    align-items: center;
    padding: 0 6px;
    box-sizing: border-box;
    margin-bottom: 15px;
    > .quantity {
      display: flex;
      align-items: center;
      width: 100%;
      > div:first-child {
        > img {
          width: 14px;
          height: 12px;
          margin-right: 8px;
        }
      }
      > div:nth-child(2) {
        > p {
          padding-top: 4px;
          font-size: 12px;
          color: #b4b4b4;
          text-align: left;
        }
      }

      > div:last-child {
        margin-left: auto;
        transform: rotate(180deg);
        > i {
          font-size: 20px;
          color: #fc5479;
          font-weight: bold;
        }
      }
    }
  }
  .van-ellipsis {
    text-align: left;
  }
}
.no-data {
  margin-top: 40px;
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
</style>