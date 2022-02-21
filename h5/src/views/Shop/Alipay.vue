<template>
  <div class="content">
    <div v-html="apply"></div>
  </div>  
</template>
<script>
import { Toast } from 'vant';
export default {
  data() {
    return {
      apply: '',
    }
  },
  mounted() {
    Toast.loading({
      duration: 0, // 持续展示 toast
      forbidClick: true,
      loadingType: 'spinner',
      overlay: true,
      message: "Loading..."
    });
    let form = sessionStorage.getItem('html');
    this.apply = form;
    this.$nextTick(() =>{
      // this.$toast.clear();
      document.forms[0].submit();
    });
  }
}
</script>