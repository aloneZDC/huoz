<template>
  <div class="mask" v-if="showKeyboard">
    <div class="passwordBox">
      <div class="header">
        <h2>请输入交易密码</h2>
         <span class="close" @click="KeyboardNot"></span>
      </div>
      <van-password-input
        :value="valueBord"
        :focused="showKeyboard"
        @focus="showKeyboard = true"
      />
    </div>
    <!-- 数字键盘 -->
    <van-number-keyboard
      theme="custom"
      extra-key="."
      close-button-text="确定"
      :show="showKeyboard"
      @input="onInput"
      @delete="onDelete"
      @close="buttonNum"
    />
  </div>
</template>

<script>
export default {
  name: 'keyBord',
  data () {
    return {
      showKeyboard: false,
      valueBord: '',
      toastShow:true
    }
  },
  methods: {
    // 显示
    showKey () {
      this.showKeyboard = true;
    },
    // 关闭
    closeKey () {
       this.valueBord = "";
      this.showKeyboard = false;
    },
    // 清空
    clearKey () {
      this.valueBord = "";
    },
    onInput (key) {
      this.valueBord = (this.valueBord + key).slice(0, 6);
    },
    onDelete () {
      this.valueBord = this.valueBord.slice(0, this.valueBord.length - 1);
    },
    KeyboardNot () {
      this.showKeyboard = false;
      this.valueBord = "";
      //打开弹窗
      this.$emit('handleShow', this.toastShow);
    },
    buttonNum () {
      if (this.valueBord == "") {
        window.toast_txt("密码不能为空");
        return false;
      }
      if (this.valueBord.length < 6) {
        window.toast_txt("密码为6位数字");
        this.valueBord = "";
        return false;
      }
      this.$emit("data-password", this.valueBord);
    }
  }
};
</script>

<style lang="scss" scoped>
.mask {
  height: 100%;
  width: 100%;
  position: fixed;
  z-index: 100;
  background: rgba(0, 0, 0, 0.9);
  left: 0;
  right: 0;
  top: 0;
  > .close {
    width: 20px;
    height: 20px;
    color: #fff;
    font-size: 18px;
    text-align: center;
    position: absolute;
    right: 3%;
    top: 6%;
    border: 1px solid #fff;
    border-radius: 10px;
  }
}
/deep/ .van-progress__portion {
  height: 50%;
}
/deep/ .van-password-input {
  background: #fff;
  top: 10%;
}
.passwordBox{
  height: 200px;
  width: 90%;
  position: absolute;
  top: 60px;
  right: 5%;
  background: white;
  border-radius: 7px;
}
.header{
  position: relative;
  padding-top:30px ;
  h2{
    text-align: center;
    font-size: 16px;

  }
  >.close{
    position: absolute;
    right: 5px;
    top: 0px;
    display: inline-block;
    width: 44px;
    height: 44px;
    background: url('../assets/shop/close.png') no-repeat center;
    background-size:25px 25px  ;
  }
}
</style>