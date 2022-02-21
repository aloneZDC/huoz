<template>
  <div class="notice">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="title">提示:出仓已审核则不可撤销,未审核则可申请撤销。</div>
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
              >
                <div class="quantity">
                  <div>
                    <p>{{ item.title }}{{ item.amount }}{{ item.currency_name }}</p>
                    <p>{{ item.add_time }}</p>
                  </div>
                  <div>
                    <button v-if="item.check_status == 0" class="btn-o" @click="btnCancel(item.id)">撤销</button>
                    <button v-else-if="item.check_status == 1" class="btn-t">出仓成功</button>
                    <button v-else-if="item.check_status == 2" class="btn-th">撤销成功</button>
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
import { WithdrawLog, WithdrawCancel  } from "@/http/api.js";
export default {
  name: "highCont",
  inject: ['reload'],
  components: {
    TopHeader,
  },
  data() {
    return {
      info: {
        title: "记录",
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
      noDataImg: require("../../assets/shop/no_data.png"),
      dataOption: {
        page: 1,
        rows: 10,
      },
      dataConfig: {},
      listOption: {
        num: '',
      }
    };
  },
  methods: {
    onLoad() {
      this.isUpLoading = true;
      this.$http
        .post(WithdrawLog, this.dataOption)
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
    btnCancel(_id) {
      let obj = {
        id: _id,
      };
      this.$http.post(WithdrawCancel, obj).then(({ data }) => {
        if(data.code == 10000) {
          this.$toast(data.message);
          setTimeout(() => {
            this.reload();
          },2000);
        }else {
          this.$toast(data.message);
        }
      })
    }
  },
  created() {
    this.onRefresh();
  },
};
</script>

<style lang="scss" scoped>
/deep/ header {
  
  background-size: 100% 100%;
  color: #333;
  i {
    color: #333;
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
  font-size: "PingFang TC";
  // padding: 0 12px;
  // box-sizing: border-box;
  z-index: 2;
  .title {
    font-size: 14px;
    font-family: "PingFang SC";
    font-weight: 500;
    color: #A2A2A2;
    margin-top: 10px;
    margin-left: 15px;
  }
  .scroll-list {
    padding: 0 12px;
    box-sizing: border-box;
  }
  .list-wrap {
    padding-top: 4px;
    text-align: center;
  }
  .detail {
    width: 100%;
    background: #EDEDED;
    border-radius: 6px;
    font-size: 14px;
    font-family: "PingFang SC";
    font-weight: 500;
    color: #212121;
    display: flex;
    align-items: center;
    margin: 15px 0;
    padding: 20px 15px;
    box-sizing: border-box;
    border-radius: 8px;
    > .quantity {
      display: flex;
      align-items: center;
      width: 100%;
      justify-content: space-between;
      text-align: left;
      > div:first-child {
        > p:nth-child(1) {
          text-align: left;
          font-size: 16px;
          color: #4E4D4D;
        }
        > p:nth-child(2) {
          color: #7E7E7E;
          font-size: 12px;
          margin-top: 10px;
        }
      }
      > div:nth-child(2) {
        font-size: 14px;
        font-weight: bold;
        > button {
          outline: none;
          border-radius: 12px;
          min-width: 72px;
          border: none;
          font-size: 14px;
          font-family: "PingFang SC";
          font-weight: 500;
          padding: 5px 0;
          border-radius: 6px;
          color: #fff;
        }
        .btn-o {
          background: linear-gradient(180deg, #FFF7F6 0%, #F86D4F 100%);
        }
        .btn-t {
          background: rgba(162, 162, 162, 0.39);
        }
        .btn-th {
          background: linear-gradient(180deg, #F6FDFF 0%, #4FA3F8 100%);
        }
      }
    }
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