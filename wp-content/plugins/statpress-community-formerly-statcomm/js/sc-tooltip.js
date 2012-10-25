jQuery(document).ready(function()
{
    jQuery('.ip').each(function()
    {
        //slight trick to use this
        //And this is the link, using the attr rel as parameter.
        var myid=jQuery(this).attr("rel"); //save unique identifier to a variable for later use
        jQuery(this).qtip({
            hide:
            {
              //now the tooltip will be fixed if you hover it, so you can use maps also.
              //to test with tablets (hover concept does not exist there!)
              fixed:true, delay:500
            },
            content: {
                    text: 'Analyzing...' , //to be translated
                    ajax: {
                        url: ajaxurl, //url defined on Wordpress
                        type: 'GET', //POST or GET
                        //define data to pass to the content
                        //action:method to invoke, WP will add prefix wp_ajax_
                        //id is the data passed through
                        //References http://codex.wordpress.org/AJAX_in_Plugins
                        // and also http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
                        data: {
                            action: 'sctooltip_action', //so it would be wp_ajax_sctooltip_action
                            id: jQuery(this).attr("rel")
                        },
                        success: function(data,status)  //define what to do if we successfully get the data.
                        {
                            this.set('content.text',data); //transfer info to the tooltip

                             //Reset the element for it there was anything inside of it
                            jQuery('.sc-map-' + myid).empty();
                            //add div element to .sc-map class and return me an object pointing a that object appended.
                            //Error: return a list of coincidences of this filter, but in this case is unique
                            content=jQuery('<div style="width:200px; height:200px;"></div>').appendTo('.sc-map-'+myid);

                            //var myc=content[0].attr("style");
                            //Interesting (and annoying) thing is to find out previous line is incorrect and also
                            //it stop all processing...javascript has a long way for improvements...
                            //Get the coordinates and split it in an array
                            coords=jQuery('.sc-map-'+myid).attr('title').split(',');

                            //Set up Google Maps passing the coordinates, zoom and specifying type (roadmap)
                            var latlngPos = new google.maps.LatLng(parseFloat(coords[0]),parseFloat(coords[1]));
                            // Set up options for the Google map
                            var myOptions = {
                                zoom: 12,
                                center: latlngPos,
                                mapTypeId: google.maps.MapTypeId.ROADMAP
                            };
                            // Define a marker (for further improvements)
                            map = new google.maps.Map(content[0] , myOptions);
                            // Add the marker
                            var marker = new google.maps.Marker({
                                position: latlngPos,
                                map: map,
                                title: "StatComm"
                            });
                            map.setCenter(latlngPos);
                        }
                    }
            },

            position: {
                my: 'left center', at: 'right center'
            },
            show: {
            /*This solves the problem with the tablets*/
                event: 'click mouseenter',
                delay: 70
            },
            style: {
                classes: 'ui-tooltip-light ui-tooltip-shadow'
            }
        });
    })

    // Make sure it doesn't follow the link when we click it
    .click(function(event) { event.preventDefault(); });
});