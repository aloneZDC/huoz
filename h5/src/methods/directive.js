import Vue from 'vue';
Vue.directive('iosbugfixed', {
  inserted: function(el) {
    const childInput = el.getElementsByTagName('input');
    const childSelect = el.getElementsByTagName('select');
    for (let i = 0; i < childInput.length; i++) {
      childInput[i].onblur = function temporaryRepair() {
        setTimeout(function() {
          // 当input 失焦时，滚动一下页面就可以使页面恢复正常
          checkWxScroll();
        }, 200);
      };
    }
    for (let i = 0; i < childSelect.length; i++) {
      childSelect[i].onblur = function temporaryRepair() {
        setTimeout(function() {
          // 当input 失焦时，滚动一下页面就可以使页面恢复正常
          checkWxScroll();
        }, 200);
      };
    }
    // 正常场景
    el.onblur = function temporaryRepair() {
      setTimeout(function() {
        // 当input 失焦时，滚动一下页面就可以使页面恢复正常
        checkWxScroll();
      }, 200);
    };
    // el.onfocus = function temporaryRepair() {
    //   console.log(222);
    //   setTimeout(function() {
    //     // 当input 失焦时，滚动一下页面就可以使页面恢复正常
    //     checkWxScroll();
    //   }, 200);
    // };
    console.log(el)
  }
});

function checkWxScroll() {
  var currentPosition, timer;
  var speed = 1; //页面滚动距离
  timer = setInterval(function() {
    currentPosition=document.documentElement.scrollTop || document.body.scrollTop;
    currentPosition-=speed;
    window.scrollTo(0,currentPosition);//页面向上滚动
    currentPosition+=speed; //speed变量
    window.scrollTo(0,currentPosition);//页面向下滚动
    clearInterval(timer);

  }, 1);
}