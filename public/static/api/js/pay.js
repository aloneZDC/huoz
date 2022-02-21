$(window).ready(function(){

	// 任意切换
	$('.click_zhifu_btn').click(function(){
		$(this).children("input").prop('checked',true).siblings().children("input").prop('checked',false);
	})

	// 点击弹出遮罩层
	$('.currency_Box').click(function(){
		$('.The_mask_layer').fadeIn(200);
	})

	// 积分类型选择任意切换
	$('.Currency_option_box').click(function(){
		if($(this).index() !== 1) {
            $(this).children("input").prop('checked', true).siblings().children("input").prop('checked', false);
            $('.currency_Box span').text($(this).children("span").text());
            $('.pay_way sapn').text($(this).children("span").text());
            $('.The_mask_layer').fadeOut(200);
            $('#currency_id').val($(this).children("input").val());
        }
	})








///////
})