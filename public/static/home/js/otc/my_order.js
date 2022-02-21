$(function(){
	let data = {};
	$(".content_box_th").on("click",function(event){
		event.stopPropagation();
		data = {
			orders_id:$(this).attr("data-id"),
			type:$(this).attr("data-type")
		};
		//console.log(msg);
		if(data.type == 'buy'){
            $(".cover_cont1").show();
            $(".cover").show();
		}else{
            $(".cover_cont").show();
            $(".cover").show();
		}



	});
	$(".issue_footer_close_th").on("click",function(){
		$(".cover_cont").hide();
		$(".cover_cont1").hide();
		$(".cover").hide();
	})
	
	$(".cover_footer_button").click(function(){
		$.ajax({
			type:"POST",
			url,
			data,
			success(data){
				if(data.code==10000){
					setTimeout(()=>{
						location.reload();
					},1000);
				}
				layer.msg(data.message);
			},
			error(error){
				console.log(error);
			}
		});
	})
});

		