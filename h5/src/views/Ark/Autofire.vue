<template>
  <div class="autofire">
    <top-header :info="info" />
    <div class="content">
      <div class="switchBox box">
        <div class="title">自动点火&推进</div>
        <van-switch
          v-model="checked"
          active-color="#F9CC43"
          inactive-color="#C6C6C6"
        />
      </div>
      <div class="linesBox box">
        <div class="title">自动最大额度</div>
        <div class="tableBox">
          <div
            class="tab"
            :class="tabActive == 1 ? 'active' : ''"
            @click="handleTab(1)"
          >
            每期最大额
          </div>
          <div
            class="tab"
            :class="tabActive == 2 ? 'active' : ''"
            @click="handleTab(2)"
          >
            自定义额度
          </div>
        </div>
      </div>
      <div class="customBox box" v-show="tabActive == 2">
        <div class="title">自定义额度</div>
        <div class="inputBox">
          <input type="text" placeholder="输入数量" v-model="num" />
        </div>
      </div>
      <p class="prompt">
        开启后将自动点火或推进,未设置自定义额度,将默认每期最大额去点火;开启自定义设置后,最大燃料不超过设定的最大额度。
      </p>
      <div class="button" @click="handleSubmit">提交修改</div>
    </div>
  </div>
</template>

<script>
import { ark_fire_info, ark_set_fire } from "@/http/api.js";
export default {
  data () {
    return {
      info: {
        title: "自动点火&推进",
        isBack: true,
        exit: true,
      },
      fire_info: '',
      checked: false,
      tabActive: 1,
      num: '',
    };
  },
  methods: {
    handleTab (type) {
      if (this.tabActive == type) { return false }
      this.tabActive = type;
    },
    getFireInfo () {
      this.$http.post(ark_fire_info)
        .then(({ data }) => {
          this.tabActive = data.result.quota_type;
          this.num = data.result.quota_price - 0;
          this.fire_info = data.result;
          if (data.result.is_fire == 1) {
            this.checked = true;
          } else {
            this.checked = false;
          }
        })
    },
    handleSubmit () {
      if (this.tabActive == 2 && this.num == '') {
        this.$toast.fail('请输入自定义额度');
        return;
      }
      let obj = {
        is_fire: this.checked ? 1 : 0,
        quota_type: this.tabActive,
        quota_price: this.num
      }
      // console.log(obj);
      this.$http.post(ark_set_fire, obj)
        .then(({ data }) => {
          if (data.code == '10000') {
            this.$toast.success(data.message)
          } else {
            this.$toast.fail(data.message)
          }
        })
    },
  },
  created () {
    this.getFireInfo();
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
  font-size: 18px;
  .box {
    margin-top: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 345px;
    height: 50px;
    background: #454443;
    border-radius: 8px;
    padding: 0 15px;
    box-sizing: border-box;
    .title {
      font-size: 18px;
      color: #e0e0e0;
      font-weight: bolder;
    }
    .tableBox {
      display: flex;
      align-items: center;
      .tab {
        width: 86px;
        height: 28px;
        line-height: 28px;
        text-align: center;
        border: 1px solid #f9cc43;
        border-radius: 6px;
        color: #f9cc43;
        font-size: 13px;
        margin-left: 10px;
      }
      .active {
        color: #010101;
        background-color: #f9cc43;
        border: none;
      }
    }
    .inputBox {
      position: relative;
      width: 75px;
      overflow: hidden;
      input {
        // 清除默认样式
        -webkit-appearance: none;
        -moz-appearance: none;
        outline: 0;
        border: none;
        z-index: 3;
        width: 75px;
        height: 25px;
        line-height: 25px;
        // padding-left: 15px;
        // padding-right: 100px;
        box-sizing: border-box;
        color: #fff;
        font-size: 18px;
        font-weight: bolder;
        background: #454443;
      }
      input::-webkit-input-placeholder {
        color: #a3a3a3;
        font-size: 18px;
        font-weight: bold;
      }
    }
  }
  .switchBox {
    margin-top: 30px;
  }
  .prompt {
    margin-top: 35px;
    display: inline-block;
    font-size: 13px;
    color: #ffb764;
  }
  .button {
    margin-top: 85px;
    width: 345px;
    height: 50px;
    line-height: 50px;
    text-align: center;
    background: linear-gradient(90deg, #ead9a9 0%, #b69865 100%);
    border-radius: 8px;
    color: #fff;
    font-size: 17px;
    font-weight: normal;
  }
}
</style>