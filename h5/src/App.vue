<template>
  <div id="app">
    <router-view v-if="isRouterAlive"></router-view>
    <!-- <Loading ></Loading> -->
  </div>
</template>
<script>
export default {
  name: "App",
  provide () {
    return {
      reload: this.reload
    }
  },
  data () {
    return {
      isRouterAlive: true,
      message: '',
    };
  },
  created () {
    window.toast_txt = this.toast_txt;
  },
  methods: {
    toast_txt (str) {
      this.message = str;
        this.$toast({
        forbidClick: true,
        message: this.message,
        overlay: true
      });
    },
    reload () {
      this.isRouterAlive = false
      this.$nextTick(function () {
        this.isRouterAlive = true
      })
    }
  },
};
</script>

<style lang="scss">
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: #2c3e50;
}

#nav {
  padding: 30px;
  a {
    font-weight: bold;
    color: #2c3e50;

    &.router-link-exact-active {
      color: #42b983;
    }
  }
}
input::placeholder {
  color: #cbcbcb;
}
</style>
