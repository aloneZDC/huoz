<template >
  <div class="routerList">
    <top-header :info="info" />
    <div class="content">
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
            <div class="list" v-for="(item, index) in items" :key="index">
              <div class="list-right">
                <p>{{item.name_tc}}</p>
                <p>{{item.add_time}}</p>
              </div>
              <p class="price">{{item.name}}Y令牌</p>
            </div>
          </van-list>
        </van-pull-refresh>
      </div>
    </div>
  </div>
</template>
<script>
import { ark_balance_detail } from '@/http/api.js'
export default {
  data () {
    return {
      info: {
        title: '余额明细',
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
      noDataImg: require('../../assets/rocket/fg-img.png'),
      dataOption: {
        page: 1,
        rows: 15,
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
    }
  },
  created () {
    this.onRefresh();
  },

  methods: {
    onLoad () {
      this.isUpLoading = true;
      this.$http
        .post(ark_balance_detail, this.dataOption)
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

  },
}
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
  width: auto;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang SC";
  font-size: 16px;
  color: #ffffff;
  background: #fff;
  padding: 0 15px;
  box-sizing: border-box;
  .scrollList {
    .no-data {
      text-align: center;
      display: block;
      margin: 0 auto;
      margin-top: 60px;
    }
    .list {
      margin-top: 15px;
      position: relative;
      display: flex;
      align-items: center;
      padding: 0 15px;
      box-sizing: border-box;
      height: 89px;
      // border-bottom: 1px solid #d8f3f1;
      background: #EDEDED;
      border-radius: 12px;
      .list-left {
        width: 36.88px;
        height: 36.88px;
      }
      .list-right {
        display: flex;
        flex-direction: column;
        justify-content: center;
        margin-left: 12px;
        p {
          margin-top: 10px;
          font-size: 12px;
          color: #999999;
        }
        p:first-child {
          color: #000;
          font-size: 16px;
        }
      }
      .price {
        position: absolute;
        right: 15px;
        top: 30px;
        height: 22px;
        line-height: 22px;
        color: #0058FF;
        font-size: 16px;
        font-weight: bold;
      }
    }
  }
}
</style>