<template>
  <div class="alert" :class="{ active: cancleFlag }">
    <div class="shade" @click.capture="colseAlert"></div>
    <div class="pop">
      <slot></slot>
    </div>
  </div>
</template>
<script>
export default {
  name: "pop-Solt",
  props: ["popData"],
  data () {
    return {
      cancleFlag: true,
    };
  },
  computed: {},
  created () { },
  methods: {
    cancleEvent () {
      this.colseAlert();
    },
    colseAlert () {
      this.cancleFlag = true;
    },
    showAlert () {
      this.cancleFlag = false;
    },
  }
};
</script>
<style lang="scss" scoped>
.shade {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 98;
  background: black;
  opacity: 0.7;
}
.alert {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  display: flex;
  justify-content: center;
  align-items: center;
  &.active {
    display: none;
  }
}
.pop {
  box-sizing: border-box;
  box-shadow: 0px 4px 10px 0px rgba(0, 0, 0, 0.1);
  background: rgba(255, 255, 255, 1);
  border-radius: 7px;
  z-index: 99;
}
</style>
