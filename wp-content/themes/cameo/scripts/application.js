//@codekit-prepend "iehtml5.js"
//@codekit-prepend "jquery.fancybox-1.3.4.js"
//@codekit-prepend "jquery-1.cycle.all.js"
//@codekit-prepend "input.js"

function page_galleries(){
    $('.page-gallery').cycle({
        fx:      'fade',
        timeout:  4000,
        pause: 1,
        cleartype:  0,
        pager:   '.page-gallery-toggler',
        pagerAnchorBuilder: pagerFactory
    });

    function pagerFactory(idx, slide) {
        var s = idx > 2 ? '' : '';
        return '<li><a href="#">'+ $(slide).attr('title') +'</a></li>';
    };
}

$(document).ready(function() {
	$(".fancybox").fancybox({

	});
	page_galleries();
	
	$("#drop-down-toggle").click(function() { $("#drop-down").toggleClass("open");});
	$(function() { var defaultText = 'Your Name (required)'; $('input[type="text"]#nameinput') .val(defaultText) .focus(function() { if ( this.value == defaultText ) this.value = ''}) .blur(function() { if ( !$.trim( this.value ) ) this.value = defaultText});});
	$(function() { var defaultText = 'Email (required)'; $('input[type="text"].emailinput') .val(defaultText) .focus(function() { if ( this.value == defaultText ) this.value = ''}) .blur(function() { if ( !$.trim( this.value ) ) this.value = defaultText});});
	$(function() { var defaultText = 'Phone'; $('input[type="text"]#phoneinput') .val(defaultText) .focus(function() { if ( this.value == defaultText ) this.value = ''}) .blur(function() { if ( !$.trim( this.value ) ) this.value = defaultText});});
});