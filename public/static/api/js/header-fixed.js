// ͷ���̶���λ
$(function() {
    var header = $("#header");


    $(window).scroll(function () {

        if ($(window).scrollTop() >= 40) {

            header.css("position", " fixed");
        }else{
            header.css("position", "relative");
        }
    });

})