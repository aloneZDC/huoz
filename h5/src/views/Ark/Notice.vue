<template>
  <div class="inDetails">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="data-title">
        <div>时间</div>
        <div>用户名</div>
        <div>点火/推进</div>
        <div>推进燃料</div>
      </div>
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
                 <div>{{ item.add_time }}</div>
                <div>{{ item.ename }}</div>
                <div>{{ item.title }}</div>
                <div>{{ item.money }}</div>
              </li>
            </ul>
          </van-list>
        </van-pull-refresh>
      </div>
    </div>
  </div>
</template>

<script>
import { ark_order_list } from "@/http/api.js";
export default {
  name: "inDetails",
  components: {},
  data() {
    return {
      info: {
        title: "参与名单",
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
        rows: 15,
      },
      imgList: require("../../assets/rocket/fv-img.png"),
    };
  },
  methods: {
    onLoad() {
      this.isUpLoading = true;
      this.$http
        .post(ark_order_list, this.dataOption)
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
  box-sizing: border-box;
  font-weight: 500;
  .data-title {
    width: 375px;
    height: 50px;
    background: #FF461E;
    font-size: 14px;
    font-weight: bold;
    color: #E0E0E0;
    display: flex;
    align-items: center;
    padding: 0 15px;
    box-sizing: border-box;
    > div {
      text-align: left;
      flex: 1;
    }
    > div:nth-child(1) {
      flex: 1.1;
    }
    > div:nth-child(3) {
      flex: 1.2;
    }
    > div:nth-child(4) {
      flex: 0.7;
    }
  }
  .detail {
    width: 100%;
    padding: 8px 15px 0;
    box-sizing: border-box;
    border-radius: 6px;
    margin-bottom: 15px;
    color: #4E4D4D;
    font-weight: bold;
    display: flex;
    align-items: center;
    > div {
      text-align: left;
      flex: 1;
    }
    > div:nth-child(1) {
      flex: 1.1;
    }
    > div:nth-child(3) {
      flex: 1.2;
    }
    > div:nth-child(4) {
      flex: 0.7;
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