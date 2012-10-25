	var xmlHttp=null;
	var imgurl="wp-content/themes/classipress/images/";
		
	function GetXmlHttpObject(){

		var xmlHttp=null;
		try {
		 // Firefox, Opera 8.0+, Safari
		 xmlHttp=new XMLHttpRequest();
		} catch (e) {
		 // Internet Explorer
		  try  {
			 xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
		  } catch (e)  {
			 xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
		  }
		 }
		return xmlHttp;
	}
    var showin_element_id = 'dummy----';
    var ajax_time_image = new Image();
	    ajax_time_image.src = imgurl+'squares.gif';
	
	function get_response(file_url, showin) {
		var imgurlpre="wp-content/themes/classipress/images/";
	       showin_element_id = showin;
			xmlHttp=GetXmlHttpObject()
			if (xmlHttp==null){
				 alert ("Browser does not support HTTP Request")
				 return
			}
			
			if(document.getElementById(showin_element_id)){
			   document.getElementById(showin_element_id).innerHTML = "<img id='imgwait' src='"+imgurlpre+"squares.gif'>" ;
			   document.getElementById('imgwait').src=ajax_time_image.src;
			}
			var url=file_url;
			
			url +="&sid="+Math.random();
			xmlHttp.onreadystatechange=display_response;
			xmlHttp.open("GET",url,true)
			xmlHttp.send(null)
	   
	}
	
    function display_response() {
		
	  if (xmlHttp.readyState==4 || xmlHttp.readyState == "complete"){
		 var responseText = xmlHttp.responseText;
		 if(document.getElementById(showin_element_id)){
			
				document.getElementById(showin_element_id).innerHTML = responseText;
		 } else {
			 alert(responseText);
		 }
		 
	  }
	}
	
