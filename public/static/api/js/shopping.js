$(window).ready(function () {

    // 显示隐藏删除按钮
    var delete_value = true;
    $('.the_editor').click(function () {
        if (delete_value) {
            $('.goods_delete').animate({right: 0}, 200);
            delete_value = false;
        } else {
            $('.goods_delete').animate({right: '-50px'}, 200);
            delete_value = true;
        }
    })

    // 删除按钮删除本身nei
    $('.goods_delete').click(function () {
        var s = $('.goods_delete').index(this);
        $('.goods_boxs').eq(s).remove();

        // 重新计算价格
        unitprice_zong = 0;
        if ($('.goods_boxs').length > 0) {
            $('.checkbox_child').each(function () {
                if ($(this).is(':checked')) {
                    var zzyxx = parseInt($(this).parent().find('.goods_parameter').find('div').find('.c_unitprice').text());
                    var xxyzz = parseInt($(this).parent().find('.goods_parameter').find('div').find('.the_number_of').text());
                    unitprice_zong += zzyxx*xxyzz;
                }
                $('.jianqiangzong').text(unitprice_zong);
            })
        } else {
            unitprice_zong = 0;
            $('.jianqiangzong').text(unitprice_zong);
            $('.checkbox_all').prop('checked', false);
        }

    })

    // 一键全选/全不选
    // .prop("checked", true);
    var unitprice_zong = 0;  //总价
    var childL = 0;  //个数
    $('.checkbox_all').click(function () {
        if ($(this).is(":checked")) {
            // 全选
            $('.checkbox_child').prop("checked", true);
            // 全选改变结算背景色
            $('.settlement_a').css('background', 'red');
            // // 总个数
            // childL = $('.checkbox_child').length;
            // $('.number_geshu').text(childL);
            // 计算总价
            unitprice_zong = 0;
            $('.checkbox_child').each(function () {
                var hh = parseInt($(this).parent().find('.goods_parameter').find('div').find('.c_unitprice').text());
                var zz = parseInt($(this).parent().find('.goods_parameter').find('div').find('.the_number_of').text());
                unitprice_zong += hh*zz;
            })
            $('.jianqiangzong').text(unitprice_zong);

        } else {
            // 全不选
            $('.checkbox_child').prop("checked", false);
            // 按钮变色
            $('.settlement_a').css('background', 'rgba(232,170,59,1)');
            // 总个数
            // childL = 0;
            // $('.number_geshu').text(childL);
            // 计算总价
            unitprice_zong = 0;
            $('.jianqiangzong').text(unitprice_zong);
        }
    })

    var all_btn = 0;  //被打勾的复选框个数
    //点击子按钮事件
    $('.checkbox_child').click(function () {
        // 按钮变色
        if ($('.checkbox_child').is(':checked')) {
            $('.settlement_a').css('background', 'red');
        } else {
            // 按钮变色
            $('.settlement_a').css('background', 'rgba(232,170,59,1)');
        }

        // 少一个不全选 全在就群选
        var chang_child = $('.checkbox_child').length;
        $('.checkbox_child').each(function () {
            if ($(this).is(':checked')) {
                all_btn++;
            } else {
                all_btn--;
            }
        })
        // 6666666666666666666666666
        if (all_btn == chang_child) {
            $('.checkbox_all').prop('checked', true);
            all_btn = 0;
        } else {
            $('.checkbox_all').prop('checked', false);
            all_btn = 0;
        }

        //点击是从新计算总价
        unitprice_zong = 0;
        $('.checkbox_child').each(function () {
            if ($(this).is(':checked')) {
                var zzxx = parseInt($(this).parent().find('.goods_parameter').find('div').find('.c_unitprice').text());
                var xxzz = parseInt($(this).parent().find('.goods_parameter').find('div').find('.the_number_of').text());
                unitprice_zong += zzxx*xxzz;
            }
            $('.jianqiangzong').text(unitprice_zong);
        })
    })


})
