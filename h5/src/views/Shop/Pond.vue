<template>
  <div class="notice">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="cont-list">
        <div class="money">
          <p>您的积分可申请</p>
          <p>{{  dataConfig.num - 0 }} 火米</p>
        </div>
        <div class="int">
          <div>
            <input type="text" placeholder="输入数量" v-model="listOption.num" @input="limit" />
            <span>火米</span>
          </div>
          <div @click="payEenter">确定</div>
        </div>
        
      </div>
      <div class="cont-title">
        <div>明细记录</div>
      </div>
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
                    <p>{{ item.title }}</p>
                    <p>{{ item.create_time }}</p>
                  </div>
                  <div :class="item.type == 'release'? 'fLines' : 'zLines'">{{ item.number }}</div>
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
import { get_integral, huomi_apply, huomi_log  } from "@/http/api.js";
export default {
  name: "highCont",
  inject: ['reload'],
  components: {
    TopHeader,
  },
  data() {
    return {
      info: {
        title: "已提货赠与",
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
      dataConfig: {},
      listOption: {
        num: '',
      }
    };
  },
  methods: {
    payEenter() {
      if(this.listOption.num == "" || this.listOption.num == 0) {
        this.$toast("请输入正确的额度");
        return false;
      };
      this.$http.post(huomi_apply, this.listOption).then(({ data }) => {
        if(data.code == 10000) {
          this.$toast(data.message);
          setTimeout(() => {
            this.reload();
          },2000)
        }else {
          this.$toast(data.message);
        }
      })
    },
    limit(e) {
      if(this.listOption.num == "00" || this.listOption.num.substring(0, 1) == ".") {
        this.listOption.num = "";
      }else {
        this.listOption.num = this.listOption.num.replace(/[^\d.]/g,'');
      }
      if(Number(this.listOption.num) >= Number(this.dataConfig.num)) {
        this.listOption.num = this.dataConfig.num - 0;
      };
    },
    onLoad() {
      this.isUpLoading = true;
      this.$http
        .post(huomi_log, this.dataOption)
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
    _get_num() {
      this.$http.post(get_integral).then(({ data }) => {
        if(data.code == 10000) {
          this.dataConfig = {
            ...data.result
          }
        }
      })
    }
  },
  created() {
    this.onRefresh();
    this._get_num();
  },
};
</script>

<style lang="scss" scoped>
/deep/ header {
  background: url("../../assets/shop/shop/efhg-img-mi.png") no-repeat center;
  background-size: 100% 100%;
  color: #fff;
  i {
    color: #fff;
  }
}
/deep/ .van-list{
  border: 1px solid #E1E1E1;
  border-radius: 8px;
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
  font-size: "PingFang TC";
  // padding: 0 12px;
  // box-sizing: border-box;
  z-index: 2;
  .scroll-list {
    padding: 0 12px;
    box-sizing: border-box;
  }
  .cont-list {
    height: 230px;
    width: 100%;
    background: url("../../assets/shop/shop/eg-img-mit.png") no-repeat center;
    background-size: 100% 100%;
    .money {
      width: 144px;
      height: 144px;
      border: 5px solid rgba(255, 255, 255, 0.72);
      border-radius: 50%;
      font-size: 12px;
      color: #fff;
      font-weight: 500;
      margin: 0 auto;
      text-align: center;
      > p:nth-child(1) {
        padding-top: 50px;
      }
      > p:nth-child(2) {
        font-size: 16px;
        font-weight: bold;
        padding-top: 3px;
      }
    }
    .int {
      margin-top: 10px;
      display: flex;
      justify-content: center;
      align-items: center;
      > div:nth-child(1) {
        display: flex;
        align-items: center;
        width: 189px;
        height: 34px;
        background: #FFFFFF;
        border-radius: 12px;

        > input {
          width: 70%;
          height: 34px;
          border-radius: 12px;
          font-size: 14px;
          border: 0;
          color: #000;
          padding-left: 8px;
          box-sizing: border-box;
        }
        > span {
          font-size: 16px;
          font-family: "PingFang SC";
          margin-right: 5px;
        }
      }
      > div:nth-child(2) {
        width: 68px;
        height: 34px;
        background: #051036;
        opacity: 1;
        border-radius: 12px;
        color: #fff;
        font-size: 16px;
        font-weight: 500;
        margin-left: 10px;
        display: flex;
        justify-content: center;
        align-items: center;
      }
    }
  }
  .cont-title {
    font-size: 17px;
    font-family: "PingFang SC";
    font-weight: 500;
    color: #292929;
    padding: 20px;
    > div {
      width: 75px;
      border-bottom: 5px solid #FF461E;
    }
  }
  .list-wrap {
    padding-top: 14px;
    text-align: center;
    padding: 0 14px;
    box-sizing: border-box;
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
    margin: 15px 0;
    border-bottom: 1px solid #E1E1E1;
    > .quantity {
      display: flex;
      align-items: center;
      width: 100%;
      justify-content: space-between;
      > div:first-child {
        > p:nth-child(1) {
          text-align: left;
          color: #1C1C1C;
          font-size: 14px;
        }
        > p:nth-child(2) {
          color: #999999;
          font-size: 12px;
          margin-top: 10px;
        }
      }
      > div:nth-child(2) {
        > p {
          padding-top: 4px;
          font-size: 16px;
          
        }
      }
    }
    .zLines {
      color: #FF461E;
    }
    .fLines {
      color: #1EFF63;
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