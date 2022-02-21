<template >
  <div class="between">
    <top-header :info="info" />
    <div class="content">
      <div class="formBox">
        <div class="inputBox first">
          <input type="text" placeholder="输入好友用户名" v-model="name" />
        </div>
        <div class="inputBox">
          <input type="text" placeholder="输入好友ID" v-model="id" />
        </div>
        <div class="inputBox">
          <input type="text" placeholder="输入互转数量" v-model="num" />
          <span>Y令牌</span>
        </div>
        <p class="balance">账户可用:{{ kmtNum - 0 }} Y令牌</p>
        <div class="button" @click="Submit">确定互转</div>
      </div>
      <div class="kmtImg"></div>
      <div class="scrollBox">
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
            <div class="listBox" v-for="(item, index) in items" :key="index">
              <div class="listLeft">
                <p>{{item.type_name}}</p>
                <p>{{item.add_time}}</p>
              </div>
              <div class="listRight">{{item.num}}<span>{{item.currency_name}}</span></div>
            </div>
          </van-list>
        </van-pull-refresh>
      </div>
    </div>
  </div>
</template>
<script>
import { ark_user_info, ark_transfer, ark_kmt_log } from '@/http/api.js'

export default {
  inject: ["reload"],
  data () {
    return {
      info: {
        title: "Y令牌互转",
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
      noDataImg: require('../../assets/rocket/fg-img.png'),
      dataOption: {
        page: 1,
        rows: 10,
      },
      name: '',
      id: '',
      num: '',
      kmtNum: ''
    }
  },
  created () {
    this.getPayInfo();
    this.onRefresh();
  },
  methods: {
    getPayInfo () {
      this.$http.post(ark_user_info, { currency_id: '103' })
        .then(({ data }) => {
          if (data.code == '10000') {
            this.kmtNum = data.result.num;
          }
        })
    },
    onLoad () {
      this.isUpLoading = true;
      this.$http
        .post(ark_kmt_log, this.dataOption)
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
          // this.items = this.items.concat(data.result);
          this.items = data.result;
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
    Submit () {
      if (this.name == '') {
        this.$toast.fail('请输入好友用户名');
        return;
      }
      let patten = /^[+-]?(0|([1-9]\d*))(\.\d+)?$/g;
      if (this.id == '' || !patten.test(this.id)) {
        this.$toast.fail('请输入好友ID');
        return;
      }
      if (this.num == '') {
        this.$toast.fail('请输入互转数量');
        return;
      }
      let obj = {
        currency_id: '103',
        target_account: this.id,
        num: this.num
      }
      this.$http.post(ark_transfer, obj)
        .then(({ data }) => {
          if (data.code == '10000') {
            this.$toast.success(data.message);
            setTimeout(() => {
              this.reload();//页面刷新
            }, 500)
          } else {
            this.$toast.fail(data.message);
          }
        })
    }
  },
}
</script>
<style lang="scss" scoped>
/deep/ header {
  background-color: #0f0f0f;
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
  width: auto;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang SC";
  font-size: 16px;
  color: #ffffff;
  background: #0f0f0f;
  padding: 0 15px;
  box-sizing: border-box;
  .formBox {
    display: flex;
    align-items: center;
    flex-direction: column;
    width: 345px;
    height: 356px;
    background: #2c281c;
    border-radius: 8px;
    .inputBox {
      position: relative;
      margin-top: 20px;
      width: 286px;
      border: 1px solid #f9cc43;
      border-radius: 8px;
      overflow: hidden;
      input {
        // 清除默认样式
        -webkit-appearance: none;
        -moz-appearance: none;
        outline: 0;
        border: none;
        z-index: 3;
        width: 100%;
        height: 46px;
        line-height: 46px;
        padding-left: 15px;
        padding-right: 100px;
        box-sizing: border-box;
        color: #232323;
        font-size: 18px;
        font-weight: bolder;
        background: #ffffff;
      }
      input::-webkit-input-placeholder {
        color: #bcbcbc;
        font-size: 16px;
        // font-weight: bold;
      }
      span {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 16px;
        color: #232323;
        font-weight: bolder;
      }
    }
    .first {
      margin-top: 25px;
    }
    .balance {
      margin-top: 15px;
      width: 286px;
      text-align: left;
      color: #c3c3c3;
      font-size: 12px;
    }
    .button {
      margin-top: 35px;
      width: 286px;
      height: 46px;
      line-height: 46px;
      text-align: center;
      background: linear-gradient(90deg, #ebdaaa 0%, #b59764 100%);
      border-radius: 8px;
    }
  }
  .kmtImg {
    margin: 0 auto;
    margin-top: 20px;
    width: 255px;
    height: 33px;
    background: url("../../assets/rocket/hz-img.png") no-repeat center;
    background-size: 100% 100%;
  }
  .scrollBox {
    display: flex;
    flex-direction: column;
        .no-data {
      margin-top: 30px;
      text-align: center;
      font-size: 14px;
      color: #eaeaeb;
      font-size: 20px;
    }
    .listBox {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 15px;
      width: 345px;
      height: 89px;
      border: 1px solid #f9cc43;
      border-radius: 12px;
      padding: 0 15px;
      box-sizing: border-box;
      .listLeft {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        p:last-child {
          margin-top: 10px;
          font-size: 12px;
          color: #999999;
        }
      }
      .listRight {
        font-size: 18px;
        span {
          margin-left: 5px;
          color: #999999;
          font-size: 14px;
        }
      }
    }
  }
}
</style>