// 遮罩淡入层函数
function theMaskLayer_fadeIn() {
    $('.theMaskLayer').fadeIn(400);
    $('body,html').css('overflow', 'hidden');
    $('.offside_detail_page').animate({right: '0'}, 400);
}
// 遮罩淡出层函数
function theMaskLayer_fadeOut() {
    $('.theMaskLayer').fadeOut(400);
    $('body,html').css('overflow', 'auto');
    $('.offside_detail_page').animate({right: '-85%'}, 400);
}


// 全部
$(document).ready(function () {
    // 属性点击事件
    $('.goods_attribute').click(function () {
        $('.JoinShoppingCity_btn').css('display', 'none');
        $('.buyNow_btn').css('display', 'none');
        theMaskLayer_fadeIn();
    })

    //取消事件冒泡
    $('.offside_detail_page').click(function () {
        return false;
    })

    //点击遮罩层事件
    $('.theMaskLayer').click(function () {
        theMaskLayer_fadeOut();
    })

    //型号点击变色事件
    $('.theMaskLayer_model_btn').click(function () {
        $(this).css({"color": 'rgba(254,203,133,1)', "border": "1px solid rgba(254,203,133,1)"});
    })
    // 回到顶部
    $('.gotop').click(function () {
        $('html,body').animate({'scrollTop': 0}, 600);
    })

    $(window).scroll(function () {
        if ($(this).scrollTop() > 40) {
            $('.gotop').fadeIn(600);
        } else {
            $('.gotop').fadeOut(300);
        }
    })


    // 遮罩层顶按钮

    // 点击加
    $('.btn_reduce').click(function () {
        var val = parseInt($('.input_number').val());
        if (val > 1) {
            $('.input_number').val(val - 1);
        } else {
            $('.input_number').val(1);
            $('.Choose_at_least').stop(true);

            // ........
            $('.Choose_at_least').fadeIn(600).delay(800).fadeOut(600);
        }
    })
    // 点击减
    $('.btn_add').click(function () {
        var val = parseInt($('.input_number').val());
        if (val < number) {
            $('.input_number').val(val + 1);
        } else {
            $('.input_number').val(number);
        }
    })

    // 加入购物车点击事件
    $('.JoinShoppingCity').click(function () {
        $('.buyNow_btn').css('display', 'none');
        theMaskLayer_fadeIn();
    })
    // 立即购买点击事件
    $('.buyNow').click(function () {
        $('.JoinShoppingCity_btn').css('display', 'none');
        theMaskLayer_fadeIn();
    })

    // 分享点击
    $('.sharebtn').click(function () {
        $('.theMaskLayer_share_box').fadeIn(400);
        $('body,html').css('overflow', 'hidden');
        $('.share_box').animate({'bottom': 0}, 400);
        $('.share_box_tishi').fadeIn(800).delay(900).fadeOut(600);
    })

    // 点击隐藏
    $('.theMaskLayer_share_box').click(function () {
        $('.share_box').animate({'bottom': -75}, 400);
        $('.theMaskLayer_share_box').fadeOut(400);
        $('body,html').css('overflow', 'auto');
    })

    // 阻止事件冒泡
    $(".share_box").click(function () {
        return false;
    });
});

