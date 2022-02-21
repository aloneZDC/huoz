$(document).ready(function () {

    // 点击事件
    $('.left_goods_category li a').click(function () {
        $('.left_goods_category li a').removeClass('goods_active');
        $(this).addClass('goods_active');
    })

    // 滚动事件
    $(document).scroll(function () {
        if ($(document).scrollTop() > 90) {
            $('.sort_btn').css('position', 'fixed');
            $('.sort_btn').css('top', 45);
        } else {
            $('.sort_btn').css('position', 'relative');
            $('.sort_btn').css('top', 0);
        }
    })


    // 点击图标改变样式事件
    var onf = true;
    $('.switch_style').click(function () {
        if (onf) {
            $(this).attr('src', '/Public/Api/img/classification_taby.png');
            $('.liang_module').addClass('dan_module').removeClass('liang_module');
            onf = false;
        } else {
            $(this).attr('src', '/Public/Api/img/classification_tabx.png');
            $('.dan_module').addClass('liang_module').removeClass('dan_module');
            onf = true;
        }
    })

})