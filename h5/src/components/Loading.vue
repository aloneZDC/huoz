<template>
  <div class="loading" v-show="$store.state.loadingFlag">
    <van-loading size="50px" vertical color="#238AE3">Loading...</van-loading>
  </div>
</template>

<script>
export default {
  name: "Loading",
  props: ["loadingFlag"],
  data () {
    return {
      toast: this.$toast
    }
  },
  created () {
    // this.toast.loading({
    //     message: '加载中...',
    //     forbidClick: true,
    //     loadingType: 'spinner',
    //     duration: 0,
    //   })
    console.log(this.$store.state.loadingFlag);
  },
  destroyed () {
    // this.toast.clear(false);
  }
}
</script>

<style lang="scss" scoped>
.loading {
  position: absolute;
  width: 100%;
  height: 100%;
  top: 0;
  bottom: 0;
  background: rgba(0,0,0,0.7);
  z-index: 2000;
  display: flex;
  justify-content: center;
  align-items: center;
}
.van-loading__text{
  color: #238AE3;
}
</style>