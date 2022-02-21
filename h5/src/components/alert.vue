<template>
  <div class="alert" :class="{active:cancleFlag}">
    <div class="shade" @click.capture="colseAlert"></div>
    <div class="pop">
      <h2>{{alertTitle}}</h2>
      <slot></slot>
      <div class="submitBtn">
        <div @click="cancleEvent">取消</div>
        <div @click="submitEvent">確定</div>
      </div>
    </div>
  </div>
</template>
<script>
export default {
  name: "alert",
  props: ["alertTitle"],
  data() {
    return {
      cancleFlag: true,
    };
  },
  computed: {},
  created() {},
  methods: {
    cancleEvent() {
      this.colseAlert();
    },
    colseAlert() {
      this.cancleFlag = true;
    },
    showAlert() {
      this.cancleFlag = false;
    },
    submitEvent() {
      this.$emit("submitEvent", {});
    }
  }
};
</script>
<style lang="scss" scoped>
.alert {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999999;
  &.active {
    display: none;
  }
}

.pop {
  position: fixed;
  top: 50%;
  transform: translateY(-50%);
  left: 57px;
  vertical-align: middle;
  width: 260px;
  padding: 20px 0 0 0;
  box-sizing: border-box;
  box-shadow: 0px 4px 10px 0px rgba(0, 0, 0, 0.1);
  width: 260px;
  background: rgba(255, 255, 255, 1);
  border-radius: 10px;
  z-index: 9999;
  h2 {
    font-size: 16px;
    font-weight: bold!important;
    color: rgba(0, 0, 0, 0.87);
    line-height: 22px;
    text-align: center;
  }
  .submitBtn {
    margin-top: 24px;
    display: flex;
    height: 52px;
    border-top: 1px solid rgba(0, 0, 0, 0.2);
    > div {
      flex: 1;
      text-align: center;
      height: 52px;
      line-height: 52px;
    }
    :nth-child(1) {
      font-size: 16px;
      font-weight: normal;
      color: rgba(0, 0, 0, 0.65);
      border-right: 1px solid rgba(0, 0, 0, 0.2);
    }
    :nth-child(2) {
      font-size: 16px;
      font-weight: normal;
      color: rgba(244, 33, 45, 1);
    }
  }
}
</style>
